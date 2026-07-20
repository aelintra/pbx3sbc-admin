<?php

namespace App\Services\Retention;

use App\Models\DoorKnockAttempt;
use App\Models\FailedRegistration;
use Carbon\Carbon;

/**
 * WS1 — purge door_knock_attempts + failed_registrations older than local_days.
 */
class SecurityEventsPurgeService
{
    public function __construct(
        private BatchedTimePurge $purge,
        private RetentionSettingsService $settings,
    ) {}

    /**
     * @return array{days: int, dry_run: bool, tables: list<array<string, mixed>>}
     */
    public function run(?int $days = null, ?int $batchSize = null, bool $dryRun = false): array
    {
        $days = max(1, min(3650, $days ?? $this->settings->securityEventsDays()));
        $batch = max(1, $batchSize ?? $this->settings->batchSize());
        $cutoff = Carbon::now()->subDays($days);

        $tables = [
            $this->purge->purge(DoorKnockAttempt::class, 'attempt_time', $cutoff, $batch, $dryRun),
            $this->purge->purge(FailedRegistration::class, 'attempt_time', $cutoff, $batch, $dryRun),
        ];

        $result = [
            'days' => $days,
            'dry_run' => $dryRun,
            'tables' => $tables,
        ];

        $this->settings->recordPurge(RetentionSettingsService::JOB_SECURITY, $result);

        return $result;
    }
}
