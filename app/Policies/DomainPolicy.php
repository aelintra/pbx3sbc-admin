<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;
use App\Services\FleetDomainOwnership;

/**
 * Fleet-owned domain rows (attrs fleet=domain) are projected from the catalog.
 * Magrathea must not offer rename / setid edit / delete — use Fleet
 * (FLEET_DOMAIN_SETID_LOCK.md / Rule 13). Standalone domains unrestricted.
 */
class DomainPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Domain $domain): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(?User $user, Domain $domain): bool
    {
        return ! FleetDomainOwnership::isFleetOwned($domain->attrs);
    }

    public function delete(?User $user, Domain $domain): bool
    {
        return ! FleetDomainOwnership::isFleetOwned($domain->attrs);
    }

    public function deleteAny(?User $user): bool
    {
        return true;
    }

    public function restore(?User $user, Domain $domain): bool
    {
        return true;
    }

    public function forceDelete(?User $user, Domain $domain): bool
    {
        return ! FleetDomainOwnership::isFleetOwned($domain->attrs);
    }

    public function replicate(?User $user, Domain $domain): bool
    {
        return ! FleetDomainOwnership::isFleetOwned($domain->attrs);
    }
}
