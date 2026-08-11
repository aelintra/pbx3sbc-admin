<?php

namespace App\Policies;

use App\Models\Dispatcher;
use App\Models\User;
use App\Services\FleetDomainOwnership;

/**
 * Destinations on fleet-locked setids (or fleet=node rows) are catalog/node projections.
 * Magrathea must not offer create/edit/delete — use Fleet Instances / node provision.
 */
class DispatcherPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Dispatcher $dispatcher): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(?User $user, Dispatcher $dispatcher): bool
    {
        return FleetDomainOwnership::destinationMutateAllowed($dispatcher);
    }

    public function delete(?User $user, Dispatcher $dispatcher): bool
    {
        return FleetDomainOwnership::destinationMutateAllowed($dispatcher);
    }

    public function deleteAny(?User $user): bool
    {
        return true;
    }

    public function restore(?User $user, Dispatcher $dispatcher): bool
    {
        return true;
    }

    public function forceDelete(?User $user, Dispatcher $dispatcher): bool
    {
        return FleetDomainOwnership::destinationMutateAllowed($dispatcher);
    }

    public function replicate(?User $user, Dispatcher $dispatcher): bool
    {
        return FleetDomainOwnership::destinationMutateAllowed($dispatcher);
    }
}
