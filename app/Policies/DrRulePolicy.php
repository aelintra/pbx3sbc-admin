<?php

namespace App\Policies;

use App\Models\DrRule;
use App\Models\User;
use App\Services\FleetDidProjector;

/**
 * Fleet-owned dr_rules (attrs fleet=did;...) are projected by FleetDidProjector
 * from the catalog and must not be hand-edited in Filament — the projector
 * will just overwrite/remove them on the next sync, and manual edits can
 * silently desync catalog vs SBC routing. Standalone (non-fleet) rules are
 * unrestricted.
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
