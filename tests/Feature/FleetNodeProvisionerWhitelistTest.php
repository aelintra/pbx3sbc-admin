<?php

namespace Tests\Feature;

use App\Models\Fail2banWhitelist;
use App\Services\FleetNodeProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FleetNodeProvisionerWhitelistTest extends TestCase
{
    use RefreshDatabase;

    private ?string $fakeScriptPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeScriptPath = tempnam(sys_get_temp_dir(), 'fake-sync-script');
        file_put_contents($this->fakeScriptPath, "#!/bin/bash\nexit 0\n");
        chmod($this->fakeScriptPath, 0755);
        putenv('FAIL2BAN_SYNC_SCRIPT_PATH='.$this->fakeScriptPath);
        Process::fake();
    }

    protected function tearDown(): void
    {
        putenv('FAIL2BAN_SYNC_SCRIPT_PATH');
        if ($this->fakeScriptPath !== null && file_exists($this->fakeScriptPath)) {
            unlink($this->fakeScriptPath);
        }

        parent::tearDown();
    }

    public function test_retire_removes_fleet_home_rows_except_keep_cidr(): void
    {
        Fail2banWhitelist::query()->create([
            'ip_or_cidr' => '1.2.3.4/32',
            'comment' => 'Fleet home kid1',
        ]);
        Fail2banWhitelist::query()->create([
            'ip_or_cidr' => '5.6.7.8/32',
            'comment' => 'Fleet home kid1',
        ]);
        Fail2banWhitelist::query()->create([
            'ip_or_cidr' => '9.9.9.9/32',
            'comment' => 'Office NAT',
        ]);

        $result = FleetNodeProvisioner::retireFail2banWhitelistForInstance('kid1', '5.6.7.8/32');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseHas('fail2ban_whitelist', ['ip_or_cidr' => '5.6.7.8/32']);
        $this->assertDatabaseMissing('fail2ban_whitelist', ['ip_or_cidr' => '1.2.3.4/32']);
        $this->assertDatabaseHas('fail2ban_whitelist', ['ip_or_cidr' => '9.9.9.9/32']);
    }

    public function test_retire_on_decommission_removes_all_fleet_home_rows(): void
    {
        Fail2banWhitelist::query()->create([
            'ip_or_cidr' => '54.236.153.81/32',
            'comment' => 'Fleet home tol123',
        ]);

        $result = FleetNodeProvisioner::retireFail2banWhitelistForInstance('tol123');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseMissing('fail2ban_whitelist', ['ip_or_cidr' => '54.236.153.81/32']);
    }
}
