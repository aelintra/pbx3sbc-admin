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

    /**
     * S10.5 residue — provision node dispatcher set + Asterisk Peer (adapter registerNode).
     * Body: { instance_id, backend_uri, setid?, confirm?, source_ip?, description?, dry_run? }
     */
    public function provisionNode(Request $request, OpenSIPSMIService $mi): JsonResponse
    {
        $instanceId = trim((string) $request->input('instance_id', ''));
        $backendUri = (string) $request->input('backend_uri', '');
        $rawSetid = $request->input('setid');
        $setid = ($rawSetid !== null && $rawSetid !== '') ? (int) $rawSetid : null;
        if ($setid !== null && $setid < 1) {
            $setid = null;
        }
        $confirm = (bool) $request->boolean('confirm');
        $dryRun = (bool) $request->boolean('dry_run');
        $sourceIp = $request->input('source_ip');
        $description = $request->input('description');

        $result = \App\Services\FleetNodeProvisioner::provision(
            $instanceId,
            $backendUri,
            $setid,
            $confirm,
            is_string($description) ? $description : null,
            is_string($sourceIp) ? $sourceIp : null,
            $dryRun
        );

        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        if (! $dryRun) {
            $mi->dispatcherReload();
            if (! empty($result['peer_created']) || ! empty($result['peer_updated'])) {
                $mi->drReload();
            }
        }

        return response()->json($result);
    }

    /**
     * Cold DR + warm-sync step 1: create local zip and upload to S3 (active VIP).
     * Body: { upload?: bool } default true
     */
    public function backup(Request $request): JsonResponse
    {
        $upload = $request->has('upload') ? (bool) $request->boolean('upload') : true;
        try {
            $svc = app(\App\Services\SbcBackupService::class);
            $role = $svc->vipRole();
            if (! ($role['vip_holder'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Backup via fleet API refused on standby — call the VIP / in-service member',
                ], 409);
            }
            $result = $svc->create($upload);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(array_merge(['ok' => true], $result));
    }

    /**
     * Warm-sync step 2: pull S3 stamp and restore --db-only (standby only).
     * Body: { stamp?: string, restart?: bool }
     */
    public function warmPull(Request $request): JsonResponse
    {
        $stamp = trim((string) $request->input('stamp', ''));
        $restart = $request->has('restart') ? (bool) $request->boolean('restart') : true;
        try {
            $svc = app(\App\Services\SbcBackupService::class);
            $result = $svc->warmPull($stamp !== '' ? $stamp : null, $restart);
        } catch (\Throwable $e) {
            $code = str_contains($e->getMessage(), 'warm-pull refused') ? 409 : 500;

            return response()->json(['ok' => false, 'message' => $e->getMessage()], $code);
        }

        return response()->json($result);
    }

    /**
     * Phase D after EIP promote: issue/renew Let's Encrypt on the VIP holder.
     * Body: { email?: string, fqdn?: string } — email falls back to PBX3_LE_EMAIL.
     */
    public function leSetup(Request $request): JsonResponse
    {
        try {
            $svc = app(\App\Services\SbcBackupService::class);
            $role = $svc->vipRole();
            if (! ($role['vip_holder'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'LE setup refused on standby — call the VIP / in-service member',
                ], 409);
            }

            $email = trim((string) $request->input('email', ''));
            if ($email === '') {
                $email = trim((string) env('PBX3_LE_EMAIL', ''));
            }
            if ($email === '' || ! str_contains($email, '@')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'email required (body.email or PBX3_LE_EMAIL)',
                ], 422);
            }

            $fqdn = trim((string) $request->input('fqdn', ''));
            $le = app(\App\Services\LetsEncryptService::class);
            if ($fqdn === '') {
                $fqdn = $le->fqdn();
            }

            $status = $le->setup($email, $fqdn !== '' ? $fqdn : null);

            return response()->json(array_merge(['ok' => true], $status));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
