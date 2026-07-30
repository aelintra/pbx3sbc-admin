<?php

namespace Tests\Unit;

use App\Models\DrGateway;
use PHPUnit\Framework\TestCase;

class DrGatewayRouteDestinationRoleTest extends TestCase
{
    public function test_destination_role_for_groupid(): void
    {
        $this->assertSame(DrGateway::ROLE_OUTBOUND, DrGateway::destinationRoleForGroupid(0));
        $this->assertSame(DrGateway::ROLE_OUTBOUND, DrGateway::destinationRoleForGroupid('0'));
        $this->assertSame(DrGateway::ROLE_ASTERISK, DrGateway::destinationRoleForGroupid(1));
        $this->assertSame(DrGateway::ROLE_ASTERISK, DrGateway::destinationRoleForGroupid('1'));
        $this->assertNull(DrGateway::destinationRoleForGroupid(null));
        $this->assertNull(DrGateway::destinationRoleForGroupid(''));
    }

    public function test_gateway_allowed_on_groupid(): void
    {
        $asterisk = new DrGateway(['attrs' => 'role=asterisk']);
        $outbound = new DrGateway(['attrs' => 'role=outbound;carrier=magrathea']);
        $inbound = new DrGateway(['attrs' => 'role=inbound']);

        $this->assertTrue(DrGateway::gatewayAllowedOnGroupid($asterisk, '1'));
        $this->assertFalse(DrGateway::gatewayAllowedOnGroupid($outbound, '1'));
        $this->assertFalse(DrGateway::gatewayAllowedOnGroupid($inbound, '1'));

        $this->assertTrue(DrGateway::gatewayAllowedOnGroupid($outbound, '0'));
        $this->assertFalse(DrGateway::gatewayAllowedOnGroupid($asterisk, '0'));
        $this->assertFalse(DrGateway::gatewayAllowedOnGroupid($inbound, '0'));
    }
}
