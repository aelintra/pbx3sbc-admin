<?php

namespace Tests\Unit;

use App\Services\FleetDidProjector;
use PHPUnit\Framework\TestCase;

class FleetDidProjectorTest extends TestCase
{
    public function test_normalize_address(): void
    {
        $this->assertSame('54.236.153.81:5060', FleetDidProjector::normalizeAddress('sip:54.236.153.81:5060'));
        $this->assertSame('54.236.153.81:5060', FleetDidProjector::normalizeAddress('SIP:54.236.153.81:5060'));
    }

    public function test_fleet_attrs(): void
    {
        $attrs = FleetDidProjector::fleetAttrs('9wvvnb', '441924918076');
        $this->assertStringContainsString('fleet=did', $attrs);
        $this->assertStringContainsString('tenant=9wvvnb', $attrs);
        $this->assertTrue(FleetDidProjector::isFleetOwned($attrs));
        $this->assertFalse(FleetDidProjector::isFleetOwned('carrier=magrathea'));
    }
}
