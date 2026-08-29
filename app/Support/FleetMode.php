<?php

namespace App\Support;

/**
 * Fleet-joined Magrathea: Gatekeeper adapter token configured.
 * Standalone / general-SBC SKU leaves PBX3_FLEET_SERVICE_TOKEN empty.
 */
final class FleetMode
{
    public static function joined(): bool
    {
        return trim((string) config('fleet.service_token', '')) !== '';
    }
}
