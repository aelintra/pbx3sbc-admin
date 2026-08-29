<?php

namespace Tests\Unit;

use App\Support\FleetMode;
use Tests\TestCase;

class FleetModeTest extends TestCase
{
    public function test_joined_when_service_token_set(): void
    {
        config(['fleet.service_token' => 'abc']);
        $this->assertTrue(FleetMode::joined());
    }

    public function test_not_joined_when_token_blank(): void
    {
        config(['fleet.service_token' => '']);
        $this->assertFalse(FleetMode::joined());

        config(['fleet.service_token' => '   ']);
        $this->assertFalse(FleetMode::joined());
    }
}
