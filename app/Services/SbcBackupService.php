<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Cold DR backup for Filament — wraps pbx3sbc/scripts/sbc-backup-panel.sh.
 * Restore stays CLI-only. Warm sync is Fleet Edge HA.
 */
class SbcBackupService
{
    public function scriptPath(): string
    {
        $candidates = [
            '/home/ubuntu/pbx3sbc/scripts/sbc-backup-panel.sh',
            '/opt/pbx3sbc/scripts/sbc-backup-panel.sh',
            config('pbx3sbc.backup_panel_script', ''),
        ];
        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException(
            'sbc-backup-panel.sh not found — deploy pbx3sbc scripts and re-run setup-admin-panel-sudoers.sh'
        );
    }

    /**
     * @return array{vip_holder: bool, advertised_address: string, local_ips: list<string>}
     */
    public function vipRole(): array
    {
        $decoded = $this->runJson(['vip-role']);
        $ips = $decoded['local_ips'] ?? [];
        if (! is_array($ips)) {
            $ips = [];
        }

        return [
            'vip_holder' => (bool) ($decoded['vip_holder'] ?? true),
            'advertised_address' => (string) ($decoded['advertised_address'] ?? ''),
            'local_ips' => array_values(array_filter(array_map('strval', $ips))),
        ];
    }

    /**
     * @return list<array{name: string, path: string, backup_stamp: string, created_at: string, epoch: int, bytes: int, on_s3: bool}>
     */
    public function listLocal(): array
    {
        $decoded = $this->runJson(['list']);
        $raw = $decoded['backups'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'name' => (string) ($row['name'] ?? ''),
                'path' => (string) ($row['path'] ?? ''),
                'backup_stamp' => (string) ($row['backup_stamp'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'epoch' => (int) ($row['epoch'] ?? 0),
                'bytes' => (int) ($row['bytes'] ?? 0),
                'on_s3' => (bool) ($row['on_s3'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @return array{zip: string, backup_stamp: string, epoch: int, uploaded: bool}
     */
    public function create(bool $upload = true): array
    {
        $args = ['create'];
        if ($upload) {
            $args[] = '--upload';
        }
        $decoded = $this->runJson($args, timeout: 300);

        return [
            'zip' => (string) ($decoded['zip'] ?? ''),
            'backup_stamp' => (string) ($decoded['backup_stamp'] ?? ''),
            'epoch' => (int) ($decoded['epoch'] ?? 0),
            'uploaded' => (bool) ($decoded['uploaded'] ?? false),
        ];
    }

    /**
     * Standby warmth: pull S3 stamp and restore --db-only.
     *
     * @return array{ok: bool, backup_stamp: string, zip: string, epoch: int, restarted: bool}
     */
    public function warmPull(?string $stamp = null, bool $restart = true): array
    {
        $args = ['warm-pull'];
        if ($stamp !== null && $stamp !== '') {
            $args[] = '--stamp';
            $args[] = $stamp;
        }
        if (! $restart) {
            $args[] = '--no-restart';
        }
        $decoded = $this->runJson($args, timeout: 600);

        return [
            'ok' => (bool) ($decoded['ok'] ?? true),
            'backup_stamp' => (string) ($decoded['backup_stamp'] ?? ''),
            'zip' => (string) ($decoded['zip'] ?? ''),
            'epoch' => (int) ($decoded['epoch'] ?? 0),
            'restarted' => (bool) ($decoded['restarted'] ?? false),
        ];
    }

    /**
     * @param  list<string>  $args
     * @return array<string, mixed>
     */
    private function runJson(array $args, int $timeout = 60): array
    {
        $cmd = array_merge(['sudo', '-n', $this->scriptPath()], $args);
        $result = Process::timeout($timeout)->run($cmd);
        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output()) ?: 'command failed';
            throw new RuntimeException($err);
        }
        $out = trim($result->output());
        // Prefer last complete JSON object (scripts may log to stdout before jq -c)
        $jsonLine = $out;
        if ($out !== '' && ($out[0] ?? '') !== '{') {
            $pos = strrpos($out, '{');
            if ($pos !== false) {
                $jsonLine = substr($out, $pos);
            }
        }
        $decoded = json_decode($jsonLine, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('sbc-backup-panel: invalid JSON: '.substr($jsonLine, 0, 120));
        }

        return $decoded;
    }
}
