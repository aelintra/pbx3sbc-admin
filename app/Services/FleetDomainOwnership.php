<?php

namespace App\Services;

use App\Models\Dispatcher;
use App\Models\Domain;
use App\Models\DrGateway;

/**
 * Fleet-owned domain rows: attrs fleet=domain (catalog → project / Rule 13).
 * Magrathea must not offer edit/delete — retarget via Fleet move / Repair / reconcile.
 * Dispatcher destinations for a setid used by any fleet domain are the same class
 * (instance backends — Fleet node provision / catalog, not Filament).
 *
 * @see docs lock FLEET_DOMAIN_SETID_LOCK.md (pbx3-directory)
 */
class FleetDomainOwnership
{
    public const FLEET_VALUE = 'domain';

    public static function isFleetOwned(?string $attrs): bool
    {
        $parsed = DrGateway::parseAttrs($attrs);

        return ($parsed['fleet'] ?? '') === self::FLEET_VALUE;
    }

    /** Destination row tagged by FleetNodeProvisioner. */
    public static function isFleetNodeDestination(?string $attrs): bool
    {
        $parsed = DrGateway::parseAttrs($attrs);

        return ($parsed['fleet'] ?? '') === 'node';
    }

    /**
     * Setid is Magrathea-locked when any fleet=domain tenant homes there,
     * or any destination in the set is fleet=node.
     */
    public static function setidIsFleetLocked(int $setid): bool
    {
        if ($setid < 1) {
            return false;
        }

        $domains = Domain::query()->where('setid', $setid)->get(['attrs']);
        foreach ($domains as $domain) {
            if (self::isFleetOwned($domain->attrs)) {
                return true;
            }
        }

        $destinations = Dispatcher::query()->where('setid', $setid)->get(['attrs']);
        foreach ($destinations as $row) {
            if (self::isFleetNodeDestination($row->attrs)) {
                return true;
            }
        }

        return false;
    }

    public static function destinationMutateAllowed(?Dispatcher $dispatcher, ?int $setid = null): bool
    {
        if ($dispatcher !== null) {
            if (self::isFleetNodeDestination($dispatcher->attrs)) {
                return false;
            }
            $setid = (int) $dispatcher->setid;
        }
        if ($setid === null || $setid < 1) {
            return true;
        }

        return ! self::setidIsFleetLocked($setid);
    }

    /**
     * Merge fleet=domain (+ optional tenant) onto attrs; always keep setid=N in sync.
     */
    public static function stamp(Domain $domain, ?string $tenantShortuid = null): void
    {
        $parsed = DrGateway::parseAttrs($domain->attrs);
        $parsed['fleet'] = self::FLEET_VALUE;
        if ($tenantShortuid !== null && $tenantShortuid !== '') {
            $parsed['tenant'] = $tenantShortuid;
        }
        if ($domain->setid !== null && $domain->setid !== '') {
            $parsed['setid'] = (string) $domain->setid;
        }
        $domain->attrs = DrGateway::formatAttrs($parsed);
    }

    /**
     * Rebuild attrs on save: preserve fleet/tenant/… keys; sync setid from column.
     */
    public static function attrsForSave(?string $existingAttrs, mixed $setid): ?string
    {
        $parsed = DrGateway::parseAttrs($existingAttrs);
        if ($setid !== null && $setid !== '') {
            $parsed['setid'] = (string) $setid;
        }
        $formatted = DrGateway::formatAttrs($parsed);

        return $formatted !== '' ? $formatted : null;
    }
}
