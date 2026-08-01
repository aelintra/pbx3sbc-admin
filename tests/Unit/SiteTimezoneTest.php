<?php

namespace Tests\Unit;

use App\Support\SiteTimezone;
use Tests\TestCase;

class SiteTimezoneTest extends TestCase
{
    public function test_calendar_bound_eastern_summer(): void
    {
        config(['pbx3_ops.site_timezone' => 'America/New_York']);

        // 2026-08-01 00:00 Eastern = 04:00 UTC (EDT)
        $this->assertSame('2026-08-01 04:00:00', SiteTimezone::siteLocalToUtc('2026-08-01', '00:00:00'));
        $this->assertSame('America/New_York', SiteTimezone::id());
    }

    public function test_invalid_override_falls_back_to_utc(): void
    {
        config(['pbx3_ops.site_timezone' => 'Not/A_Zone']);

        $this->assertSame('UTC', SiteTimezone::id());
    }
}
