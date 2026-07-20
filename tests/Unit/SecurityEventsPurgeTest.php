<?php

namespace Tests\Unit;

use App\Models\DoorKnockAttempt;
use App\Models\FailedRegistration;
use App\Services\Retention\BatchedTimePurge;
use App\Services\Retention\RetentionSettingsService;
use App\Services\Retention\SecurityEventsPurgeService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityEventsPurgeTest extends TestCase
{
    private string $overridePath;

    private string $statusPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overridePath = sys_get_temp_dir().'/pbx3-ret-ov-'.uniqid('', true).'.json';
        $this->statusPath = sys_get_temp_dir().'/pbx3-ret-st-'.uniqid('', true).'.json';
        config([
            'pbx3_retention.override_path' => $this->overridePath,
            'pbx3_retention.status_path' => $this->statusPath,
            'pbx3_retention.security_events.local_days' => 30,
            'pbx3_retention.security_events.batch_size' => 1000,
        ]);

        Schema::create('door_knock_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain')->nullable();
            $table->string('source_ip');
            $table->integer('source_port');
            $table->string('user_agent')->nullable();
            $table->string('method', 16);
            $table->string('request_uri')->nullable();
            $table->string('reason');
            $table->dateTime('attempt_time');
        });

        Schema::create('failed_registrations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username');
            $table->string('domain');
            $table->string('source_ip');
            $table->integer('source_port');
            $table->string('user_agent')->nullable();
            $table->integer('response_code');
            $table->string('response_reason')->nullable();
            $table->dateTime('attempt_time');
            $table->integer('expires_header')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->overridePath);
        @unlink($this->statusPath);
        parent::tearDown();
    }

    public function test_dry_run_counts_without_deleting(): void
    {
        $this->seedRows();

        $service = new SecurityEventsPurgeService(new BatchedTimePurge, new RetentionSettingsService);
        $result = $service->run(null, 10, true);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(2, DoorKnockAttempt::count());
        $this->assertSame(2, FailedRegistration::count());

        $byTable = collect($result['tables'])->keyBy('table');
        $this->assertSame(1, $byTable['door_knock_attempts']['eligible']);
        $this->assertSame(0, $byTable['door_knock_attempts']['deleted']);
        $this->assertSame(1, $byTable['failed_registrations']['eligible']);
        $this->assertFileExists($this->statusPath);
    }

    public function test_purge_deletes_in_batches(): void
    {
        $this->seedRows();

        $service = new SecurityEventsPurgeService(new BatchedTimePurge, new RetentionSettingsService);
        $result = $service->run(30, 1, false);

        $this->assertFalse($result['dry_run']);
        $this->assertSame(1, DoorKnockAttempt::count());
        $this->assertSame(1, FailedRegistration::count());
        $this->assertSame(
            'keep.example',
            DoorKnockAttempt::first()->domain
        );
    }

    public function test_override_days_are_used(): void
    {
        $this->seedRows();
        $settings = new RetentionSettingsService;
        $settings->put(['security_events_days' => 10, 'acc_days' => 90, 'batch_size' => 100]);

        $service = new SecurityEventsPurgeService(new BatchedTimePurge, $settings);
        $result = $service->run(null, null, true);

        // Both seed rows are older than 10d? keep is 5d — only old.example (45d) eligible for door_knock
        $this->assertSame(10, $result['days']);
        $byTable = collect($result['tables'])->keyBy('table');
        $this->assertSame(1, $byTable['door_knock_attempts']['eligible']);
    }

    public function test_batched_time_purge_respects_cutoff(): void
    {
        DoorKnockAttempt::query()->insert([
            [
                'domain' => 'old',
                'source_ip' => '1.1.1.1',
                'source_port' => 5060,
                'method' => 'INVITE',
                'reason' => 'scanner_detected',
                'attempt_time' => Carbon::now()->subDays(40)->format('Y-m-d H:i:s'),
            ],
            [
                'domain' => 'new',
                'source_ip' => '1.1.1.1',
                'source_port' => 5060,
                'method' => 'INVITE',
                'reason' => 'scanner_detected',
                'attempt_time' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s'),
            ],
        ]);

        $purge = new BatchedTimePurge;
        $out = $purge->purge(
            DoorKnockAttempt::class,
            'attempt_time',
            Carbon::now()->subDays(30),
            100,
            false,
        );

        $this->assertSame(1, $out['deleted']);
        $this->assertSame(1, DoorKnockAttempt::count());
        $this->assertSame('new', DoorKnockAttempt::first()->domain);
    }

    private function seedRows(): void
    {
        DoorKnockAttempt::query()->insert([
            [
                'domain' => 'old.example',
                'source_ip' => '1.2.3.4',
                'source_port' => 5060,
                'method' => 'INVITE',
                'reason' => 'scanner_detected',
                'attempt_time' => Carbon::now()->subDays(45)->format('Y-m-d H:i:s'),
            ],
            [
                'domain' => 'keep.example',
                'source_ip' => '1.2.3.4',
                'source_port' => 5060,
                'method' => 'INVITE',
                'reason' => 'domain_not_found',
                'attempt_time' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s'),
            ],
        ]);

        FailedRegistration::query()->insert([
            [
                'username' => 'old',
                'domain' => 'x.com',
                'source_ip' => '5.6.7.8',
                'source_port' => 5060,
                'response_code' => 403,
                'attempt_time' => Carbon::now()->subDays(60)->format('Y-m-d H:i:s'),
            ],
            [
                'username' => 'new',
                'domain' => 'x.com',
                'source_ip' => '5.6.7.8',
                'source_port' => 5060,
                'response_code' => 403,
                'attempt_time' => Carbon::now()->subDays(2)->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
