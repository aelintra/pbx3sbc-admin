<?php

namespace App\Policies;

use App\Models\DrRule;
use App\Models\User;
use App\Services\FleetDidProjector;

/**
 * Fleet-owned dr_rules (attrs fleet=did;...) are projected from the catalog.
 * Magrathea must not offer edit/delete — retarget via Fleet DIDs only
 * (FLEET_DID_HOP1_LOCK.md / Rule 13). Standalone (non-fleet) rules unrestricted.
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
        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }

    public function delete(?User $user, DrRule $drRule): bool
    {
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
        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }

    public function reorder(?User $user): bool
    {
        return true;
    }

    public function replicate(?User $user, DrRule $drRule): bool
    {
        return ! FleetDidProjector::isFleetOwned($drRule->attrs);
    }
}
