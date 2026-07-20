<?php

namespace App\Services\Retention;

/**
 * Effective retention knobs (config/env + optional JSON override) and last-purge status.
 * Spec: SBC_DATA_RETENTION_REQUIREMENTS.md WS3 — no Filament-triggered delete.
 */
class RetentionSettingsService
{
    public const JOB_SECURITY = 'security_events';

    public const JOB_ACC = 'acc';

    /**
     * @return array{
     *   security_events_days: int,
     *   acc_days: int,
     *   batch_size: int,
     *   override_path: string,
     *   has_override: bool,
     *   status_path: string,
     *   last_purge: array<string, mixed>|null
     * }
     */
    public function get(): array
    {
        return [
            'security_events_days' => $this->securityEventsDays(),
            'acc_days' => $this->accDays(),
            'batch_size' => $this->batchSize(),
            'override_path' => $this->overridePath(),
            'has_override' => is_file($this->overridePath()),
            'status_path' => $this->statusPath(),
            'last_purge' => $this->loadStatus(),
        ];
    }

    public function securityEventsDays(): int
    {
        $override = $this->overrideSection();
        if (isset($override['security_events_days'])) {
            return $this->clampDays((int) $override['security_events_days']);
        }

        return $this->clampDays((int) config('pbx3_retention.security_events.local_days', 30));
    }

    public function accDays(): int
    {
        $override = $this->overrideSection();
        if (isset($override['acc_days'])) {
            return $this->clampDays((int) $override['acc_days']);
        }

        return $this->clampDays((int) config('pbx3_retention.acc.local_days', 90));
    }

    public function batchSize(): int
    {
        $override = $this->overrideSection();
        if (isset($override['batch_size'])) {
            return max(1, min(10000, (int) $override['batch_size']));
        }

        return max(1, min(10000, (int) config('pbx3_retention.security_events.batch_size', 1000)));
    }

    /**
     * @param  array{security_events_days?: mixed, acc_days?: mixed, batch_size?: mixed}  $knobs
     * @return array{security_events_days: int, acc_days: int, batch_size: int, override_path: string, has_override: bool, status_path: string, last_purge: array<string, mixed>|null}
     */
    public function put(array $knobs): array
    {
        $payload = [
            'schema_version' => 1,
            'security_events_days' => $this->clampDays((int) ($knobs['security_events_days'] ?? $this->securityEventsDays())),
            'acc_days' => $this->clampDays((int) ($knobs['acc_days'] ?? $this->accDays())),
            'batch_size' => max(1, min(10000, (int) ($knobs['batch_size'] ?? $this->batchSize()))),
            'updated_at' => gmdate('c'),
        ];

        $path = $this->overridePath();
        $dir = dirname($path);
        if ($dir !== '' && ! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new \RuntimeException("Cannot create retention override dir: {$dir}");
            }
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($path, $json."\n") === false) {
            throw new \RuntimeException("Cannot write retention override: {$path}");
        }

        return $this->get();
    }

    /**
     * @param  array<string, mixed>  $result  service run() result
     */
    public function recordPurge(string $job, array $result): void
    {
        $status = $this->loadStatus() ?? [];
        $status[$job] = [
            'at' => gmdate('c'),
            'dry_run' => (bool) ($result['dry_run'] ?? false),
            'days' => $result['days'] ?? null,
            'tables' => $result['tables'] ?? [],
        ];
        $status['updated_at'] = gmdate('c');

        $path = $this->statusPath();
        $dir = dirname($path);
        if ($dir !== '' && ! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            @file_put_contents($path, $json."\n");
        }
    }

    public function overridePath(): string
    {
        $path = config('pbx3_retention.override_path');

        return is_string($path) && $path !== ''
            ? $path
            : storage_path('app/pbx3-retention-override.json');
    }

    public function statusPath(): string
    {
        $path = config('pbx3_retention.status_path');

        return is_string($path) && $path !== ''
            ? $path
            : storage_path('app/pbx3-retention-status.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadStatus(): ?array
    {
        $path = $this->statusPath();
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function overrideSection(): array
    {
        $path = $this->overridePath();
        if (! is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function clampDays(int $days): int
    {
        return max(1, min(3650, $days));
    }
}
