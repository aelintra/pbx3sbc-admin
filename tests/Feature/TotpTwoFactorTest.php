<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotpTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_confirm_and_disable_totp(): void
    {
        $user = User::factory()->create([
            'email' => 'ops@example.com',
        ]);

        $this->assertFalse($user->hasEnabledTwoFactor());
        $this->assertFalse($user->hasConfirmedTwoFactor());

        $user->enableTwoFactorAuthentication();
        $user->refresh();
        $user->load('breezySessions');

        $this->assertTrue($user->hasEnabledTwoFactor());
        $this->assertFalse($user->hasConfirmedTwoFactor());

        $qr = $user->getTwoFactorQrCodeUrl();
        $this->assertStringContainsString('Aelintra%20SBC', $qr);
        $this->assertStringContainsString(rawurlencode('ops@example.com'), $qr);

        $user->confirmTwoFactorAuthentication();
        $user->refresh();
        $user->load('breezySessions');

        $this->assertTrue($user->hasConfirmedTwoFactor());
        $this->assertNotEmpty($user->two_factor_recovery_codes);

        $user->disableTwoFactorAuthentication();
        $user->refresh();
        $user->load('breezySessions');

        $this->assertFalse($user->hasEnabledTwoFactor());
        $this->assertFalse($user->hasConfirmedTwoFactor());
    }

    public function test_fleet_health_uses_bearer_not_filament_session(): void
    {
        config(['fleet.service_token' => 'lab-fleet-token']);

        $denied = $this->getJson('/api/fleet/health');
        $denied->assertUnauthorized();

        $ok = $this->withToken('lab-fleet-token')->getJson('/api/fleet/health');
        $ok->assertOk()->assertJson([
            'ok' => true,
            'service' => 'pbx3sbc-admin',
        ]);
    }

    public function test_profile_page_requires_authentication(): void
    {
        $this->get('/admin/my-profile')->assertRedirect();
    }
}
