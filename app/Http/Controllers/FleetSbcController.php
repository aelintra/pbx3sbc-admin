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
}
