<?php

namespace Tests\Unit;

use App\Services\Retention\RetentionSettingsService;
use Tests\TestCase;

class RetentionSettingsServiceTest extends TestCase
{
    private string $overridePath;

    private string $statusPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overridePath = sys_get_temp_dir().'/pbx3-set-ov-'.uniqid('', true).'.json';
        $this->statusPath = sys_get_temp_dir().'/pbx3-set-st-'.uniqid('', true).'.json';
        config([
            'pbx3_retention.override_path' => $this->overridePath,
            'pbx3_retention.status_path' => $this->statusPath,
            'pbx3_retention.security_events.local_days' => 30,
            'pbx3_retention.acc.local_days' => 90,
            'pbx3_retention.security_events.batch_size' => 1000,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->overridePath);
        @unlink($this->statusPath);
        parent::tearDown();
    }

    public function test_defaults_from_config(): void
    {
        $s = new RetentionSettingsService;
        $this->assertSame(30, $s->securityEventsDays());
        $this->assertSame(90, $s->accDays());
        $this->assertFalse($s->get()['has_override']);
    }

    public function test_put_writes_override(): void
    {
        $s = new RetentionSettingsService;
        $out = $s->put([
            'security_events_days' => 14,
            'acc_days' => 60,
            'batch_size' => 500,
        ]);

        $this->assertTrue($out['has_override']);
        $this->assertSame(14, $out['security_events_days']);
        $this->assertSame(60, $out['acc_days']);
        $this->assertSame(500, $out['batch_size']);
        $this->assertFileExists($this->overridePath);
    }

    public function test_record_purge_writes_status(): void
    {
        $s = new RetentionSettingsService;
        $s->recordPurge('security_events', [
            'dry_run' => true,
            'days' => 30,
            'tables' => [['table' => 'door_knock_attempts', 'eligible' => 1, 'deleted' => 0]],
        ]);

        $status = $s->loadStatus();
        $this->assertNotNull($status);
        $this->assertTrue($status['security_events']['dry_run']);
        $this->assertSame(30, $status['security_events']['days']);
    }
}
