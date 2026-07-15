<?php

namespace App\Http\Controllers;

use App\Models\Dispatcher;
use App\Models\Domain;
use App\Services\OpenSIPSMIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SbcFleetAdapter HTTP surface for the fleet gatekeeper (S8.10 §2.4).
 */
class FleetSbcController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'pbx3sbc-admin',
        ]);
    }

    /**
     * Read-only projection for catalog reconcile (S10.4).
     * Intent: listTenantDomains — domain name → dispatcher setid.
     */
    public function listDomains(): JsonResponse
    {
        $domains = Domain::query()
            ->orderBy('domain')
            ->get(['domain', 'setid'])
            ->map(static fn (Domain $d): array => [
                'domain' => (string) $d->domain,
                'setid' => (int) $d->setid,
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'domains' => $domains,
        ]);
    }

    /**
     * Live dispatcher setids (sets with ≥1 destination) — catalog setid must be one of these.
     */
    public function listDispatcherSets(): JsonResponse
    {
        $rows = Dispatcher::query()
            ->selectRaw('setid, COUNT(*) as destinations')
            ->groupBy('setid')
            ->orderBy('setid')
            ->get();

        $sets = $rows->map(static fn ($row): array => [
            'setid' => (int) $row->setid,
            'destinations' => (int) $row->destinations,
        ])->values()->all();

        return response()->json([
            'ok' => true,
            'sets' => $sets,
        ]);
    }

    public function preflight(Request $request): JsonResponse
    {
        $domainName = (string) $request->input('tenant_domain', '');
        $destSetid = (int) $request->input('dest_dispatcher_setid', 0);
        if ($domainName === '' || $destSetid < 1) {
            return response()->json([
                'message' => 'tenant_domain and dest_dispatcher_setid are required',
            ], 422);
        }

        $domain = Domain::query()->where('domain', $domainName)->first();
        $dispatchers = Dispatcher::query()->where('setid', $destSetid)->get();

        $checks = [
            [
                'name' => 'domain_row',
                'ok' => $domain !== null,
                'detail' => $domain
                    ? "domain={$domainName} setid={$domain->setid}"
                    : "No domain row for {$domainName}",
            ],
            [
                'name' => 'dest_dispatcher_set',
                'ok' => $dispatchers->isNotEmpty(),
                'detail' => $dispatchers->isNotEmpty()
                    ? $dispatchers->count().' destination(s) in setid '.$destSetid
                    : "No dispatcher rows for setid {$destSetid}",
            ],
        ];

        $ok = collect($checks)->every(fn (array $c) => $c['ok']);

        return response()->json([
            'ok' => $ok,
            'checks' => $checks,
            'current_setid' => $domain?->setid,
        ]);
    }

    public function repoint(Request $request, OpenSIPSMIService $mi): JsonResponse
    {
        $domainName = (string) $request->input('tenant_domain', '');
        $destSetid = (int) $request->input('dest_dispatcher_setid', 0);
        if ($domainName === '' || $destSetid < 1) {
            return response()->json([
                'message' => 'tenant_domain and dest_dispatcher_setid are required',
            ], 422);
        }

        $domain = Domain::query()->where('domain', $domainName)->first();
        if ($domain === null) {
            return response()->json(['message' => "Domain not found: {$domainName}"], 404);
        }

        $destCount = Dispatcher::query()->where('setid', $destSetid)->count();
        if ($destCount < 1) {
            return response()->json([
                'message' => "No dispatcher destinations for setid {$destSetid}",
            ], 422);
        }

        $previous = (int) $domain->setid;
        $domain->setid = $destSetid;
        $domain->save();
        $mi->domainReload();

        return response()->json([
            'ok' => true,
            'tenant_domain' => $domainName,
            'previous_setid' => $previous,
            'dest_setid' => $destSetid,
        ]);
    }

    public function rollbackRepoint(Request $request, OpenSIPSMIService $mi): JsonResponse
    {
        $domainName = (string) $request->input('tenant_domain', '');
        $previousSetid = (int) $request->input('previous_setid', 0);
        if ($domainName === '' || $previousSetid < 1) {
            return response()->json([
                'message' => 'tenant_domain and previous_setid are required',
            ], 422);
        }

        $domain = Domain::query()->where('domain', $domainName)->first();
        if ($domain === null) {
            return response()->json(['message' => "Domain not found: {$domainName}"], 404);
        }

        $domain->setid = $previousSetid;
        $domain->save();
        $mi->domainReload();

        return response()->json([
            'ok' => true,
            'tenant_domain' => $domainName,
            'restored_setid' => $previousSetid,
        ]);
    }

    /**
     * S10.5 — project catalog DID ownership onto inbound dr_rules (groupid 1).
     * Body: { dids: [...], dry_run?: bool }
     */
    public function projectDids(Request $request, OpenSIPSMIService $mi): JsonResponse
    {
        $dids = $request->input('dids', []);
        if (! is_array($dids)) {
            return response()->json(['message' => 'dids array required'], 422);
        }
        $dryRun = (bool) $request->boolean('dry_run');
        $ensure = $request->input('ensure_tenants', []);
        if (! is_array($ensure)) {
            $ensure = [];
        }

        $result = \App\Services\FleetDidProjector::project($dids, $dryRun, $ensure);
        if (! $dryRun && ($result['upserted'] !== [] || $result['removed'] !== [])) {
            $mi->drReload();
        }

        return response()->json(array_merge(['dry_run' => $dryRun], $result), $result['ok'] ? 200 : 422);
    }

    /**
     * S10.5 — ensure tenant SIP domain row exists (catalog → edge).
     * Body: { domain, setid, description? }
     */
    public function registerDomain(Request $request, OpenSIPSMIService $mi): JsonResponse
    {
        $domainName = strtolower(trim((string) $request->input('domain', '')));
        $setid = (int) $request->input('setid', 0);
        if ($domainName === '' || $setid < 1) {
            return response()->json(['message' => 'domain and setid (>=1) required'], 422);
        }

        $destCount = Dispatcher::query()->where('setid', $setid)->count();
        if ($destCount < 1) {
            return response()->json([
                'message' => "No dispatcher destinations for setid {$setid}",
            ], 422);
        }

        $domain = Domain::query()->where('domain', $domainName)->first();
        $created = false;
        if ($domain === null) {
            $domain = new Domain([
                'domain' => $domainName,
                'setid' => $setid,
            ]);
            $domain->save();
            $created = true;
        } else {
            $domain->setid = $setid;
            $domain->save();
        }
        $mi->domainReload();

        return response()->json([
            'ok' => true,
            'created' => $created,
            'domain' => $domainName,
            'setid' => $setid,
        ]);
    }
}
