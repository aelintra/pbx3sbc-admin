<?php

namespace Tests\Unit;

use App\Services\Fail2banService;
use App\Services\Ops\Fail2banBanNotifyScanner;
use App\Services\Ops\GatekeeperOpsClient;
use Mockery;
use Tests\TestCase;

class Fail2banBanNotifyScannerTest extends TestCase
{
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = sys_get_temp_dir().'/pbx3-f2b-ban-'.uniqid('', true).'.json';
        config([
            'pbx3_ops.fail2ban_ban_notify_enabled' => true,
            'pbx3_ops.state_path' => $this->statePath,
            'pbx3_ops.max_emits_per_run' => 10,
            'pbx3_ops.sbc_fqdn' => 'sbc.pbx3.com',
            'pbx3_ops.gatekeeper_url' => 'https://control.example',
            'pbx3_ops.gatekeeper_token' => 'tok',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_first_run_seeds_without_emit(): void
    {
        $fail2ban = Mockery::mock(Fail2banService::class);
        $fail2ban->shouldReceive('getStatus')->once()->andReturn([
            'jail_name' => 'opensips-brute-force',
            'service_running' => true,
            'banned_ips' => ['203.0.113.1'],
            'currently_banned' => 1,
        ]);

        $gk = Mockery::mock(GatekeeperOpsClient::class);
        $gk->shouldReceive('isConfigured')->once()->andReturn(true);
        $gk->shouldNotReceive('postEvent');

        $result = (new Fail2banBanNotifyScanner($fail2ban, $gk))->run();

        $this->assertTrue($result['seeded']);
        $this->assertSame(0, $result['emitted']);
        $this->assertSame(1, $result['current']);
        $this->assertFileExists($this->statePath);
    }

    public function test_new_ban_emits_event(): void
    {
        file_put_contents($this->statePath, json_encode([
            'banned' => ['203.0.113.1'],
            'updated_at' => gmdate('c'),
        ]));

        $fail2ban = Mockery::mock(Fail2banService::class);
        $fail2ban->shouldReceive('getStatus')->once()->andReturn([
            'jail_name' => 'opensips-brute-force',
            'service_running' => true,
            'banned_ips' => ['203.0.113.1', '198.51.100.9'],
            'currently_banned' => 2,
        ]);

        $gk = Mockery::mock(GatekeeperOpsClient::class);
        $gk->shouldReceive('isConfigured')->once()->andReturn(true);
        $gk->shouldReceive('postEvent')->once()->with(Mockery::on(function (array $event): bool {
            return ($event['type'] ?? '') === 'fail2ban_ban'
                && ($event['source_ip'] ?? '') === '198.51.100.9'
                && ($event['sbc_fqdn'] ?? '') === 'sbc.pbx3.com';
        }))->andReturn(['accepted' => true, 'notified' => true]);

        $result = (new Fail2banBanNotifyScanner($fail2ban, $gk))->run();

        $this->assertFalse($result['seeded']);
        $this->assertSame(1, $result['new']);
        $this->assertSame(1, $result['emitted']);
    }

    public function test_disabled_skips(): void
    {
        config(['pbx3_ops.fail2ban_ban_notify_enabled' => false]);
        $fail2ban = Mockery::mock(Fail2banService::class);
        $gk = Mockery::mock(GatekeeperOpsClient::class);
        $fail2ban->shouldNotReceive('getStatus');
        $gk->shouldNotReceive('isConfigured');

        $result = (new Fail2banBanNotifyScanner($fail2ban, $gk))->run();
        $this->assertFalse($result['enabled']);
    }
}
