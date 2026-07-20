<?php

namespace App\Services\Retention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Batched DELETE by time cutoff + primary key ranges.
 * Spec: SBC_DATA_RETENTION_REQUIREMENTS.md — never long transactions on hot tables.
 */
class BatchedTimePurge
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return array{table: string, cutoff: string, eligible: int, deleted: int, batches: int, dry_run: bool}
     */
    public function purge(
        string $modelClass,
        string $timeColumn,
        \DateTimeInterface $cutoff,
        int $batchSize,
        bool $dryRun = false,
    ): array {
        $batchSize = max(1, min(10000, $batchSize));
        $cutoffStr = $cutoff->format('Y-m-d H:i:s');
        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();
        $key = $model->getKeyName();

        $eligible = $modelClass::query()
            ->where($timeColumn, '<', $cutoffStr)
            ->count();

        if ($dryRun || $eligible === 0) {
            return [
                'table' => $table,
                'cutoff' => $cutoffStr,
                'eligible' => $eligible,
                'deleted' => 0,
                'batches' => 0,
                'dry_run' => $dryRun,
            ];
        }

        $deleted = 0;
        $batches = 0;

        while (true) {
            $ids = $modelClass::query()
                ->where($timeColumn, '<', $cutoffStr)
                ->orderBy($key)
                ->limit($batchSize)
                ->pluck($key);

            if ($ids->isEmpty()) {
                break;
            }

            $n = $modelClass::query()->whereIn($key, $ids)->delete();
            $deleted += $n;
            $batches++;

            if ($ids->count() < $batchSize) {
                break;
            }
        }

        Log::info('pbx3sbc retention purge', [
            'table' => $table,
            'cutoff' => $cutoffStr,
            'deleted' => $deleted,
            'batches' => $batches,
        ]);

        return [
            'table' => $table,
            'cutoff' => $cutoffStr,
            'eligible' => $eligible,
            'deleted' => $deleted,
            'batches' => $batches,
            'dry_run' => false,
        ];
    }
}
