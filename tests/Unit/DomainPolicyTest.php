<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Policies\DomainPolicy;
use App\Services\FleetDomainOwnership;
use PHPUnit\Framework\TestCase;

class DomainPolicyTest extends TestCase
{
    private function fleetOwnedDomain(): Domain
    {
        return new Domain([
            'domain' => 'affcot.pbx3.com',
            'setid' => 2,
            'attrs' => 'fleet=domain;setid=2;tenant=9wvvnb',
        ]);
    }

    private function standaloneDomain(): Domain
    {
        return new Domain([
            'domain' => 'foreign.example.com',
            'setid' => 9,
            'attrs' => 'setid=9',
        ]);
    }

    public function test_fleet_owned_domain_cannot_be_edited(): void
    {
        $policy = new DomainPolicy;

        $this->assertFalse($policy->update(null, $this->fleetOwnedDomain()));
    }

    public function test_fleet_owned_domain_cannot_be_deleted(): void
    {
        $policy = new DomainPolicy;

        $this->assertFalse($policy->delete(null, $this->fleetOwnedDomain()));
    }

    public function test_standalone_domain_stays_editable_and_deletable(): void
    {
        $policy = new DomainPolicy;
        $domain = $this->standaloneDomain();

        $this->assertTrue($policy->update(null, $domain));
        $this->assertTrue($policy->delete(null, $domain));
    }

    public function test_viewing_and_creating_always_allowed(): void
    {
        $policy = new DomainPolicy;

        $this->assertTrue($policy->viewAny(null));
        $this->assertTrue($policy->create(null));
        $this->assertTrue($policy->view(null, $this->fleetOwnedDomain()));
    }

    public function test_attrs_for_save_preserves_fleet_tag(): void
    {
        $attrs = FleetDomainOwnership::attrsForSave('fleet=domain;tenant=abc;setid=1', 3);
        $this->assertTrue(FleetDomainOwnership::isFleetOwned($attrs));
        $this->assertStringContainsString('setid=3', (string) $attrs);
        $this->assertStringContainsString('tenant=abc', (string) $attrs);
    }

    public function test_stamp_sets_fleet_domain(): void
    {
        $domain = new Domain(['domain' => 'x.pbx3.com', 'setid' => 2, 'attrs' => 'setid=2']);
        FleetDomainOwnership::stamp($domain, '9wvvnb');
        $this->assertTrue(FleetDomainOwnership::isFleetOwned($domain->attrs));
        $this->assertStringContainsString('tenant=9wvvnb', (string) $domain->attrs);
    }

    public function test_fleet_node_destination_detected(): void
    {
        $this->assertTrue(FleetDomainOwnership::isFleetNodeDestination('fleet=node;instance=08jzwn'));
        $this->assertFalse(FleetDomainOwnership::isFleetNodeDestination('setid=2'));
    }

    public function test_destination_mutate_denied_for_fleet_node_row(): void
    {
        $row = new \App\Models\Dispatcher([
            'setid' => 2,
            'destination' => 'sip:10.0.0.1:5060',
            'attrs' => 'fleet=node;instance=08jzwn',
        ]);
        $this->assertFalse(FleetDomainOwnership::destinationMutateAllowed($row));
    }
}
