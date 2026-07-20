<?php

namespace App\Services\Ops;

use App\Services\Fail2banService;
use Illuminate\Support\Facades\Log;

/**
 * Diff Fail2ban banned IPs vs last poll; emit new bans to Gatekeeper.
 * First run seeds state without mailing (avoids flooding existing bans).
 */
final class Fail2banBanNotifyScanner
{
    public function __construct(
        private readonly Fail2banService $fail2ban,
        private readonly GatekeeperOpsClient $gatekeeper,
    ) {
    }

    /**
     * @return array{
     *   enabled: bool,
     *   seeded: bool,
     *   current: int,
     *   new: int,
     *   emitted: int,
     *   skipped_cap: int,
     *   errors: list<string>
     * }
     */
    public function run(): array
    {
        $result = [
            'enabled' => false,
            'seeded' => false,
            'current' => 0,
            'new' => 0,
            'emitted' => 0,
            'skipped_cap' => 0,
            'errors' => [],
        ];

        if (! filter_var(config('pbx3_ops.fail2ban_ban_notify_enabled', false), FILTER_VALIDATE_BOOL)) {
            return $result;
        }
        $result['enabled'] = true;

        if (! $this->gatekeeper->isConfigured()) {
            $result['errors'][] = 'Gatekeeper URL/token not configured';

            return $result;
        }

        try {
            $status = $this->fail2ban->getStatus();
        } catch (\Throwable $e) {
            $result['errors'][] = 'fail2ban status: '.$e->getMessage();

            return $result;
        }

        if (! empty($status['error']) || empty($status['service_running'])) {
            $result['errors'][] = (string) ($status['error'] ?? 'Fail2ban not running');

            return $result;
        }

        $jail = (string) ($status['jail_name'] ?? 'opensips-brute-force');
        $current = array_values(array_unique(array_filter(
            array_map('strval', $status['banned_ips'] ?? []),
            static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false
        )));
        sort($current);
        $result['current'] = count($current);

        $statePath = (string) config('pbx3_ops.state_path', storage_path('app/ops-fail2ban-ban.json'));
        $prev = $this->readState($statePath);
        $known = $prev['banned'] ?? null;

        if (! is_array($known)) {
            $this->writeState($statePath, $current);
            $result['seeded'] = true;

            return $result;
        }

        $known = array_values(array_unique(array_map('strval', $known)));
        $new = array_values(array_diff($current, $known));
        $result['new'] = count($new);

        $maxEmits = max(1, (int) config('pbx3_ops.max_emits_per_run', 10));
        $fqdn = $this->sbcFqdn();
        $toEmit = array_slice($new, 0, $maxEmits);
        $result['skipped_cap'] = max(0, count($new) - count($toEmit));

        foreach ($toEmit as $ip) {
            try {
                $this->gatekeeper->postEvent([
                    'type' => 'fail2ban_ban',
                    'source_ip' => $ip,
                    'jail' => $jail,
                    'sbc_fqdn' => $fqdn,
                    'currently_banned' => (int) ($status['currently_banned'] ?? count($current)),
                ]);
                $result['emitted']++;
            } catch (\Throwable $e) {
                $result['errors'][] = "{$ip}: ".$e->getMessage();
                Log::warning('fail2ban ban notify emit failed', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Persist full current set even if emit failed — avoids re-storm on next tick
        // for IPs we already attempted (Gatekeeper throttle covers true duplicates).
        $this->writeState($statePath, $current);

        return $result;
    }

    private function sbcFqdn(): string
    {
        $raw = trim((string) config('pbx3_ops.sbc_fqdn', ''));
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $host = parse_url($raw, PHP_URL_HOST);

            return is_string($host) ? $host : $raw;
        }

        return $raw;
    }

    /**
     * @return array{banned?: list<string>}
     */
    private function readState(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $json = json_decode($raw, true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param  list<string>  $banned
     */
    private function writeState(string $path, array $banned): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payload = json_encode([
            'banned' => array_values($banned),
            'updated_at' => gmdate('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $payload === false ? "{}\n" : $payload."\n");
    }
}
