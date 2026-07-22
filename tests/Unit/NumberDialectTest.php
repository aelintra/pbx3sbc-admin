<?php

namespace Tests\Unit;

use App\Services\NumberDialect;
use PHPUnit\Framework\TestCase;

class NumberDialectTest extends TestCase
{
    public function test_magrathea_inbound_matrix(): void
    {
        $p = NumberDialect::PRESET_UK_MAGRATHEA;
        $this->assertSame('+441924918076', NumberDialect::normalizeToPlusE164('01924918076', $p));
        $this->assertSame('+441924918076', NumberDialect::normalizeToPlusE164('+441924918076', $p));
        $this->assertSame('+441924918076', NumberDialect::normalizeToPlusE164('441924918076', $p));
        $this->assertSame('+441924918076', NumberDialect::normalizeToPlusE164('00441924918076', $p));
        $this->assertSame('441924918076', NumberDialect::toE164Key('01924918076', $p));
    }

    public function test_gamma_inbound_matrix(): void
    {
        $p = NumberDialect::PRESET_UK_GAMMA;
        $this->assertSame('+442071234567', NumberDialect::normalizeToPlusE164('02071234567', $p));
        $this->assertSame('+442071234567', NumberDialect::normalizeToPlusE164('+442071234567', $p));
    }

    public function test_strict_plus_rejects_national(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NumberDialect::normalizeToPlusE164('01924918076', NumberDialect::PRESET_STRICT_PLUS_E164);
    }

    public function test_magrathea_outbound_render(): void
    {
        $p = NumberDialect::PRESET_UK_MAGRATHEA;
        $this->assertSame('+441924918076', NumberDialect::renderDial('+441924918076', $p));
        $cli = NumberDialect::renderCliNetwork('+441924918076', $p);
        $this->assertSame('+441924918076', $cli['user']);
        $this->assertSame('paid', $cli['header']);
        $pres = NumberDialect::renderCliPresentation('+442071111111', '+441924918076', $p);
        $this->assertSame('+442071111111', $pres['user']);
        $this->assertSame('rpid', $pres['header']);
    }

    public function test_gamma_cli_presentation_same(): void
    {
        $p = NumberDialect::PRESET_UK_GAMMA;
        $pres = NumberDialect::renderCliPresentation('+442071111111', '+441924918076', $p);
        $this->assertSame('+441924918076', $pres['user']);
        $this->assertSame('from', $pres['header']);
    }

    public function test_cross_carrier_normalize_then_render(): void
    {
        // DID delivered Magrathea national → canonical → Gamma egress dial
        $canon = NumberDialect::normalizeToPlusE164('01924918076', NumberDialect::PRESET_UK_MAGRATHEA);
        $this->assertSame('+441924918076', $canon);
        $this->assertSame(
            '+441924918076',
            NumberDialect::renderDial($canon, NumberDialect::PRESET_UK_GAMMA)
        );
        $cli = NumberDialect::renderCliNetwork($canon, NumberDialect::PRESET_UK_GAMMA);
        $this->assertSame('paid', $cli['header']);
        $this->assertSame('+441924918076', $cli['user']);
    }

    public function test_preset_options_include_uk_carriers(): void
    {
        $opts = NumberDialect::presetOptions();
        $this->assertArrayHasKey(NumberDialect::PRESET_UK_MAGRATHEA, $opts);
        $this->assertArrayHasKey(NumberDialect::PRESET_UK_GAMMA, $opts);
        $this->assertArrayHasKey(NumberDialect::PRESET_STRICT_PLUS_E164, $opts);
    }

    public function test_sanitize_sip_uri(): void
    {
        $this->assertSame(
            '+441924918076',
            NumberDialect::normalizeToPlusE164('sip:+441924918076@sbc.example.com', NumberDialect::PRESET_STRICT_PLUS_E164)
        );
    }
}
