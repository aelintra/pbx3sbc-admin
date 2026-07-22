<?php

namespace Tests\Unit;

use App\Models\DrGateway;
use App\Services\NumberDialect;
use PHPUnit\Framework\TestCase;

class DrGatewayDialectAttrsTest extends TestCase
{
    public function test_set_carrier_attrs_writes_dialect(): void
    {
        $gw = new DrGateway;
        $gw->setCarrierAttrs('Magrathea', DrGateway::ROLE_INBOUND, NumberDialect::PRESET_UK_MAGRATHEA);
        $this->assertSame('magrathea', $gw->carrierSlug());
        $this->assertSame(DrGateway::ROLE_INBOUND, $gw->peerRole());
        $this->assertSame(NumberDialect::PRESET_UK_MAGRATHEA, $gw->numberDialect());
        $this->assertStringContainsString('dialect=uk-magrathea', (string) $gw->attrs);
    }

    public function test_asterisk_role_clears_dialect(): void
    {
        $gw = new DrGateway;
        $gw->attrs = 'carrier=magrathea;role=inbound;dialect=uk-magrathea';
        $gw->setCarrierAttrs('magrathea', DrGateway::ROLE_ASTERISK, NumberDialect::PRESET_UK_GAMMA);
        $this->assertSame(DrGateway::ROLE_ASTERISK, $gw->peerRole());
        $this->assertSame('', $gw->numberDialect());
        $this->assertStringNotContainsString('dialect=', (string) $gw->attrs);
    }

    public function test_none_removes_dialect_key(): void
    {
        $gw = new DrGateway;
        $gw->attrs = 'carrier=gamma;role=outbound;dialect=uk-gamma';
        $gw->setCarrierAttrs('gamma', DrGateway::ROLE_OUTBOUND, NumberDialect::PRESET_NONE);
        $this->assertSame('', $gw->numberDialect());
        $this->assertStringNotContainsString('dialect=', (string) $gw->attrs);
    }
}
