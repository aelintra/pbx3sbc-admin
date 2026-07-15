<?php

namespace Tests\Unit;

use App\Services\FleetNodeProvisioner;
use PHPUnit\Framework\TestCase;

class FleetNodeProvisionerTest extends TestCase
{
    public function test_normalize_sip_uri(): void
    {
        $this->assertSame('sip:08jzwn.pbx3.com:5060', FleetNodeProvisioner::normalizeSipUri('sip:08jzwn.pbx3.com:5060'));
        $this->assertSame('sip:08jzwn.pbx3.com:5060', FleetNodeProvisioner::normalizeSipUri('08jzwn.pbx3.com'));
        $this->assertSame('sip:54.236.153.81:5060', FleetNodeProvisioner::normalizeSipUri('54.236.153.81:5060'));
    }

    public function test_fleet_attrs(): void
    {
        $d = FleetNodeProvisioner::fleetDispatcherAttrs('abcKSUID', '1.2.3.4');
        $this->assertStringContainsString('fleet=node', $d);
        $this->assertStringContainsString('instance=abcKSUID', $d);
        $this->assertStringContainsString('source_ip=1.2.3.4', $d);

        $p = FleetNodeProvisioner::fleetPeerAttrs('abcKSUID', 2);
        $this->assertStringContainsString('role=asterisk', $p);
        $this->assertStringContainsString('setid=2', $p);
    }
}
