<?php

namespace App\Services\Retention;

use App\Models\Cdr;
use Carbon\Carbon;

/**
 * WS2 — purge OpenSIPS acc (edge CDR) older than local_days. Purge-only; edge ops only.
 */
class AccPurgeService
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
        $days = max(1, min(3650, $days ?? $this->settings->accDays()));
        $batch = max(1, $batchSize ?? $this->settings->batchSize());
        $cutoff = Carbon::now()->subDays($days);

        $tables = [
            $this->purge->purge(Cdr::class, 'time', $cutoff, $batch, $dryRun),
        ];

        $result = [
            'days' => $days,
            'dry_run' => $dryRun,
            'tables' => $tables,
        ];

        $this->settings->recordPurge(RetentionSettingsService::JOB_ACC, $result);

        return $result;
    }
}
