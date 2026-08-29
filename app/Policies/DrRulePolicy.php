<?php

namespace App\Policies;

use App\Models\DrRule;
use App\Models\User;
use App\Services\FleetDidProjector;
use App\Support\FleetMode;

/**
 * Fleet-owned dr_rules (attrs fleet=did;...) are projected from the catalog.
 * Magrathea must not offer edit/delete — retarget via Fleet DIDs only
 * (FLEET_DID_HOP1_LOCK.md / Rule 13). Standalone (non-fleet) rules unrestricted.
 *
 * Fleet-joined Magrathea: inbound (groupid 1) Filament mutate is hidden/denied —
 * footgun vs Fleet DIDs. Code + projector remain; reopen later for non-fleet backends.
 */
class DrRulePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, DrRule $drRule): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(?User $user, DrRule $drRule): bool
    {
        if (self::inboundLockedOnFleet($drRule)) {
            return false;
        }

        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }

    public function delete(?User $user, DrRule $drRule): bool
    {
        if (self::inboundLockedOnFleet($drRule)) {
            return false;
        }

        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }

    public function deleteAny(?User $user): bool
    {
        return true;
    }

    public function restore(?User $user, DrRule $drRule): bool
    {
        return true;
    }

    public function forceDelete(?User $user, DrRule $drRule): bool
    {
        if (self::inboundLockedOnFleet($drRule)) {
            return false;
        }

        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }

    public function reorder(?User $user): bool
    {
        return true;
    }

    public function replicate(?User $user, DrRule $drRule): bool
    {
        if (self::inboundLockedOnFleet($drRule)) {
            return false;
        }

        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }

    /** Inbound Number routes — Filament mutate denied when fleet-joined. */
    public static function inboundLockedOnFleet(DrRule $drRule): bool
    {
        return FleetMode::joined() && (string) $drRule->groupid === FleetDidProjector::INBOUND_GROUP;
    }
}
