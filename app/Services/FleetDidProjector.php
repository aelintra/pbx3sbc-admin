<?php

namespace App\Services;

use App\Models\Dispatcher;
use App\Models\DrGateway;
use App\Models\DrRule;

/**
 * Catalog DID → SBC dr_rules projection (inbound groupid 1).
 * Fleet-owned rows carry attrs fleet=did;tenant=…;e164_key=…
 */
class FleetDidProjector
{
    public const INBOUND_GROUP = '1';

    /**
     * Resolve Asterisk gwid for a dispatcher setid.
     * Prefer role=asterisk gateway with attrs setid=N; else match dispatcher destination address.
     */
    public static function resolveAsteriskGwid(int $setid): ?string
    {
        if ($setid < 1) {
            return null;
        }

        $asterisk = DrGateway::query()->get()->filter(function (DrGateway $gw) use ($setid) {
            if ($gw->peerRole() !== DrGateway::ROLE_ASTERISK) {
                return false;
            }
            $parsed = DrGateway::parseAttrs($gw->attrs);

            return isset($parsed['setid']) && (int) $parsed['setid'] === $setid;
        })->first();

        if ($asterisk !== null) {
            return (string) $asterisk->gwid;
        }

        $destinations = Dispatcher::query()->where('setid', $setid)->pluck('destination');
        if ($destinations->isEmpty()) {
            return null;
        }

        $normDests = $destinations->map(fn ($d) => self::normalizeAddress((string) $d))->all();

        $match = DrGateway::query()->get()->first(function (DrGateway $gw) use ($normDests) {
            if ($gw->peerRole() !== DrGateway::ROLE_ASTERISK && $gw->peerRole() !== '') {
                // allow blank role for legacy rows that only match address
            }
            $role = $gw->peerRole();
            if ($role !== '' && $role !== DrGateway::ROLE_ASTERISK) {
                return false;
            }

            return in_array(self::normalizeAddress((string) $gw->address), $normDests, true);
        });

        return $match !== null ? (string) $match->gwid : null;
    }

    /**
     * sip:host:port / sip:ip:port → host:port lowercased (strip sip: and brackets).
     */
    public static function normalizeAddress(string $uri): string
    {
        $s = strtolower(trim($uri));
        $s = preg_replace('#^sip:#', '', $s) ?? $s;
        $s = trim($s, '[]');

        return $s;
    }

    public static function fleetAttrs(string $tenantShortuid, string $e164Key): string
    {
        return DrGateway::formatAttrs([
            'fleet' => 'did',
            'tenant' => $tenantShortuid,
            'e164_key' => $e164Key,
        ]);
    }

    public static function isFleetOwned(?string $attrs): bool
    {
        $parsed = DrGateway::parseAttrs($attrs);

        return ($parsed['fleet'] ?? '') === 'did';
    }

    /**
     * @param  list<array{e164: string, e164_key?: string, sip_prefix?: string, tenant_shortuid: string, status: string, sbc_dispatcher_setid: int|null}>  $dids
     * @param  list<string>  $ensureTenants  Always reconcile (purge stale) these tenants even if $dids empty for them
     * @return array{ok: bool, upserted: list<array<string,mixed>>, removed: list<array<string,mixed>>, errors: list<string>}
     */
    public static function project(array $dids, bool $dryRun = false, array $ensureTenants = []): array
    {
        $upserted = [];
        $removed = [];
        $errors = [];
        $keepPrefixesByTenant = [];

        foreach ($ensureTenants as $t) {
            $t = (string) $t;
            if ($t !== '') {
                $keepPrefixesByTenant[$t] = $keepPrefixesByTenant[$t] ?? [];
            }
        }

        foreach ($dids as $row) {
            $status = (string) ($row['status'] ?? '');
            $tenant = (string) ($row['tenant_shortuid'] ?? '');
            $e164 = (string) ($row['e164'] ?? '');
            $e164Key = (string) ($row['e164_key'] ?? ltrim($e164, '+'));
            $prefix = (string) ($row['sip_prefix'] ?? $e164Key);
            $prefix = preg_replace('/\D+/', '', $prefix) ?? '';
            $setid = isset($row['sbc_dispatcher_setid']) ? (int) $row['sbc_dispatcher_setid'] : 0;

            if ($tenant === '' || $prefix === '') {
                $errors[] = "Invalid DID row (tenant/prefix): {$e164}";
                continue;
            }

            if (! in_array($status, ['active', 'porting'], true)) {
                // non-deliverable — ensure fleet row removed if present
                $existing = DrRule::query()
                    ->where('groupid', self::INBOUND_GROUP)
                    ->where('prefix', $prefix)
                    ->get()
                    ->first(fn (DrRule $r) => self::isFleetOwned($r->attrs));
                if ($existing) {
                    if (! $dryRun) {
                        $existing->delete();
                    }
                    $removed[] = [
                        'prefix' => $prefix,
                        'tenant_shortuid' => $tenant,
                        'e164' => $e164,
                        'reason' => 'status_'.$status,
                    ];
                }
                continue;
            }

            if ($setid < 1) {
                $errors[] = "No sbc_dispatcher_setid for {$e164} tenant {$tenant}";
                continue;
            }

            $gwid = self::resolveAsteriskGwid($setid);
            if ($gwid === null) {
                $errors[] = "No Asterisk gateway for setid {$setid} ({$e164})";
                continue;
            }

            $attrs = self::fleetAttrs($tenant, $e164Key);
            $description = "fleet-did {$e164} → tenant {$tenant} setid {$setid}";

            $keepPrefixesByTenant[$tenant][$prefix] = true;

            $rule = DrRule::query()
                ->where('groupid', self::INBOUND_GROUP)
                ->where('prefix', $prefix)
                ->first();

            if ($rule !== null && ! self::isFleetOwned($rule->attrs) && filled($rule->attrs)) {
                $errors[] = "Prefix {$prefix} owned by non-fleet rule (ruleid {$rule->ruleid}) — skip";
                continue;
            }

            if (! $dryRun) {
                if ($rule === null) {
                    $rule = new DrRule([
                        'groupid' => self::INBOUND_GROUP,
                        'prefix' => $prefix,
                        'timerec' => null,
                        'priority' => 10,
                        'routeid' => null,
                        'gwlist' => $gwid,
                        'sort_alg' => 'N',
                        'sort_profile' => null,
                        'attrs' => $attrs,
                        'description' => $description,
                    ]);
                    $rule->save();
                } else {
                    $rule->gwlist = $gwid;
                    $rule->attrs = $attrs;
                    $rule->description = $description;
                    $rule->priority = 10;
                    $rule->save();
                }
            }

            $upserted[] = [
                'prefix' => $prefix,
                'e164' => $e164,
                'tenant_shortuid' => $tenant,
                'setid' => $setid,
                'gwid' => $gwid,
                'ruleid' => $rule?->ruleid,
            ];
        }

        // Remove fleet-owned rules for tenants touched by dids or ensureTenants
        $tenantsTouched = array_keys($keepPrefixesByTenant);
        foreach ($dids as $row) {
            $t = (string) ($row['tenant_shortuid'] ?? '');
            if ($t !== '' && ! in_array($t, $tenantsTouched, true)) {
                $tenantsTouched[] = $t;
            }
        }

        foreach ($tenantsTouched as $tenant) {
            $keep = $keepPrefixesByTenant[$tenant] ?? [];
            $fleetRules = DrRule::query()
                ->where('groupid', self::INBOUND_GROUP)
                ->get()
                ->filter(function (DrRule $r) use ($tenant) {
                    if (! self::isFleetOwned($r->attrs)) {
                        return false;
                    }
                    $parsed = DrGateway::parseAttrs($r->attrs);

                    return ($parsed['tenant'] ?? '') === $tenant;
                });

            foreach ($fleetRules as $rule) {
                $p = (string) $rule->prefix;
                if (isset($keep[$p])) {
                    continue;
                }
                if (! $dryRun) {
                    $rule->delete();
                }
                $removed[] = [
                    'prefix' => $p,
                    'tenant_shortuid' => $tenant,
                    'reason' => 'stale_fleet_row',
                    'ruleid' => $rule->ruleid,
                ];
            }
        }

        return [
            'ok' => $errors === [],
            'upserted' => $upserted,
            'removed' => $removed,
            'errors' => $errors,
        ];
    }
}
