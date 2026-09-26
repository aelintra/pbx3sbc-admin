<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Filament HTTPS (:443) management-access lockdown — host UFW SoT.
 * Spec: pbx3-directory/docs/SBC_MANAGEMENT_ACCESS_REQUIREMENTS.md
 */
class ManagementAccessService
{
    public const MARKER = 'pbx3sbc-mgmt';

    public function __construct(
        private readonly ?string $statePathOverride = null,
        private readonly ?string $applyScriptOverride = null,
    ) {}

    /**
     * @return array{
     *   lockdown: bool,
     *   allows: list<array{cidr: string, comment: string}>,
     *   updated_at: string|null,
     *   last_apply: array<string, mixed>|null,
     *   state_path: string
     * }
     */
    public function get(): array
    {
        $path = $this->statePath();
        $defaults = [
            'lockdown' => false,
            'allows' => [],
            'updated_at' => null,
            'last_apply' => null,
            'state_path' => $path,
        ];

        if (! is_file($path)) {
            return $defaults;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $defaults;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return $defaults;
        }

        $allows = [];
        foreach ($data['allows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cidr = trim((string) ($row['cidr'] ?? $row['ip_or_cidr'] ?? ''));
            if ($cidr === '' || ! $this->isValidCidr($cidr)) {
                continue;
            }
            $allows[] = [
                'cidr' => $cidr,
                'comment' => trim((string) ($row['comment'] ?? '')),
            ];
        }

        return [
            'lockdown' => (bool) ($data['lockdown'] ?? false),
            'allows' => $allows,
            'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            'last_apply' => isset($data['last_apply']) && is_array($data['last_apply'])
                ? $data['last_apply']
                : null,
            'state_path' => $path,
        ];
    }

    /**
     * @param  array{
     *   lockdown?: bool,
     *   allows?: list<array{cidr?: string, comment?: string}>,
     *   last_apply?: array<string, mixed>|null
     * }  $payload
     * @return array{lockdown: bool, allows: list<array{cidr: string, comment: string}>, updated_at: string|null, last_apply: array<string, mixed>|null, state_path: string}
     */
    public function put(array $payload): array
    {
        $lockdown = (bool) ($payload['lockdown'] ?? false);
        $allows = [];
        foreach ($payload['allows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cidr = trim((string) ($row['cidr'] ?? ''));
            if ($cidr === '') {
                continue;
            }
            if (! $this->isValidCidr($cidr)) {
                throw new \InvalidArgumentException("Invalid IP/CIDR: {$cidr}");
            }
            $allows[] = [
                'cidr' => $cidr,
                'comment' => trim((string) ($row['comment'] ?? '')),
            ];
        }

        // Dedupe by cidr
        $seen = [];
        $unique = [];
        foreach ($allows as $row) {
            if (isset($seen[$row['cidr']])) {
                continue;
            }
            $seen[$row['cidr']] = true;
            $unique[] = $row;
        }

        $existing = $this->get();
        $lastApply = array_key_exists('last_apply', $payload)
            ? $payload['last_apply']
            : $existing['last_apply'];

        $out = [
            'schema_version' => 1,
            'lockdown' => $lockdown,
            'allows' => $unique,
            'updated_at' => gmdate('c'),
            'last_apply' => $lastApply,
        ];

        $path = $this->statePath();
        $dir = dirname($path);
        if ($dir !== '' && ! is_dir($dir)) {
            if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new \RuntimeException("Cannot create state dir: {$dir}");
            }
        }

        $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($path, $json."\n") === false) {
            throw new \RuntimeException("Cannot write state: {$path}");
        }

        return $this->get();
    }

    /**
     * Persist + run UFW apply. Enforces break-glass rules.
     *
     * @param  array{lockdown: bool, allows: list<array{cidr?: string, comment?: string}>}  $payload
     * @return array{ok: bool, message: string, state: array<string, mixed>, ufw_output: string}
     */
    public function apply(array $payload, string $clientIp, bool $confirmIncludeMyIp = true): array
    {
        $lockdown = (bool) ($payload['lockdown'] ?? false);
        $allows = $payload['allows'] ?? [];
        $autoAdded = false;

        if ($lockdown) {
            $normalized = [];
            foreach ($allows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $cidr = trim((string) ($row['cidr'] ?? ''));
                if ($cidr === '') {
                    continue;
                }
                $normalized[] = [
                    'cidr' => $cidr,
                    'comment' => trim((string) ($row['comment'] ?? '')),
                ];
            }

            if ($clientIp !== '' && ! $this->ipCoveredByAllows($clientIp, $normalized)) {
                // Always keep the applying operator — no confirm checkbox.
                array_unshift($normalized, [
                    'cidr' => $this->hostCidr($clientIp),
                    'comment' => 'Added on apply (session)',
                ]);
                $autoAdded = true;
            }

            if ($normalized === []) {
                return [
                    'ok' => false,
                    'message' => 'Cannot enable lockdown with an empty allow list. Add at least one CIDR (or use Add my IP).',
                    'state' => $this->get(),
                    'ufw_output' => '',
                ];
            }

            $payload['allows'] = $normalized;
        }

        // unused — kept for call-site compat; always auto-include when needed
        unset($confirmIncludeMyIp);

        $previous = $this->get();

        $state = $this->put($payload);

        $script = $this->resolveApplyScript();
        if ($script === null) {
            $this->put([
                'lockdown' => $previous['lockdown'],
                'allows' => $state['allows'],
                'last_apply' => [
                    'at' => gmdate('c'),
                    'ok' => false,
                    'exit_code' => null,
                    'lockdown' => $state['lockdown'],
                    'allow_count' => count($state['allows']),
                    'error' => 'apply script missing',
                ],
            ]);

            return [
                'ok' => false,
                'message' => 'apply-management-access-ufw.sh not found — deploy pbx3sbc scripts and re-run setup-admin-panel-sudoers.sh',
                'state' => $this->get(),
                'ufw_output' => '',
            ];
        }

        $result = Process::run([
            '/usr/bin/sudo',
            '-n',
            $script,
            $state['state_path'],
        ]);
        $output = trim($result->output()."\n".$result->errorOutput());

        $lastApply = [
            'at' => gmdate('c'),
            'ok' => $result->successful(),
            'exit_code' => $result->exitCode(),
            'lockdown' => $state['lockdown'],
            'allow_count' => count($state['allows']),
        ];

        if (! $result->successful()) {
            Log::error('Management access UFW apply failed', [
                'exit' => $result->exitCode(),
                'output' => $output,
            ]);

            // Keep allow list; revert lockdown so UI matches host firewall
            $state = $this->put([
                'lockdown' => $previous['lockdown'],
                'allows' => $state['allows'],
                'last_apply' => $lastApply + ['error' => $output],
            ]);

            return [
                'ok' => false,
                'message' => 'UFW apply failed: '.($output !== '' ? $output : 'exit '.$result->exitCode()),
                'state' => $state,
                'ufw_output' => $output,
            ];
        }

        $state = $this->put([
            'lockdown' => $state['lockdown'],
            'allows' => $state['allows'],
            'last_apply' => $lastApply,
        ]);

        $message = $state['lockdown']
            ? 'Lockdown applied — admin HTTPS limited to listed sources.'
            : 'Lockdown off — admin HTTPS open (UFW Anywhere on 443). Allow list is kept for when you turn lockdown on.';
        if ($autoAdded) {
            $message .= ' Added your IP ('.$this->hostCidr($clientIp).') so you stay connected.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'state' => $state,
            'ufw_output' => $output,
        ];
    }

    public function statusOutput(): string
    {
        $script = $this->resolveApplyScript();
        if ($script === null) {
            return 'apply script not found';
        }
        $result = Process::run([
            '/usr/bin/sudo',
            '-n',
            $script,
            '--status',
        ]);

        return trim($result->output()."\n".$result->errorOutput());
    }

    public function statePath(): string
    {
        if ($this->statePathOverride !== null && $this->statePathOverride !== '') {
            return $this->statePathOverride;
        }
        $configured = function_exists('config') ? config('pbx3_ops.management_access_state_path') : null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return function_exists('storage_path')
            ? storage_path('app/management-access.json')
            : sys_get_temp_dir().'/management-access.json';
    }

    public function isValidCidr(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }
        if (! str_contains($value, '/')) {
            return false;
        }
        [$ip, $prefix] = explode('/', $value, 2);
        if (! ctype_digit($prefix)) {
            return false;
        }
        $bits = (int) $prefix;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $bits >= 0 && $bits <= 32;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $bits >= 0 && $bits <= 128;
        }

        return false;
    }

    /**
     * @param  list<array{cidr: string, comment?: string}>  $allows
     */
    public function ipCoveredByAllows(string $ip, array $allows): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach ($allows as $row) {
            $cidr = $row['cidr'] ?? '';
            if ($cidr === '') {
                continue;
            }
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function hostCidr(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip.'/32';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip.'/128';
        }

        return $ip;
    }

    public function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subLong = ip2long($subnet);
            if ($ipLong === false || $subLong === false) {
                return false;
            }
            $mask = $bits === 0 ? 0 : (~((1 << (32 - $bits)) - 1) & 0xFFFFFFFF);

            return ($ipLong & $mask) === ($subLong & $mask);
        }
        // IPv6: coarse equality on full address when /128, else skip deep match
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($bits === 128) {
                return inet_pton($ip) === inet_pton($subnet);
            }
            $ipBin = inet_pton($ip);
            $subBin = inet_pton($subnet);
            if ($ipBin === false || $subBin === false) {
                return false;
            }
            $bytes = intdiv($bits, 8);
            $rem = $bits % 8;
            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
                return false;
            }
            if ($rem === 0) {
                return true;
            }
            $mask = (~((1 << (8 - $rem)) - 1)) & 0xFF;

            return (ord($ipBin[$bytes]) & $mask) === (ord($subBin[$bytes]) & $mask);
        }

        return false;
    }

    public function resolveApplyScript(): ?string
    {
        if ($this->applyScriptOverride !== null) {
            return is_file($this->applyScriptOverride) && is_executable($this->applyScriptOverride)
                ? $this->applyScriptOverride
                : null;
        }
        $candidates = [
            '/home/ubuntu/pbx3sbc/scripts/apply-management-access-ufw.sh',
            '/home/tech/pbx3sbc/scripts/apply-management-access-ufw.sh',
            '/opt/pbx3sbc/scripts/apply-management-access-ufw.sh',
            '/usr/local/pbx3sbc/scripts/apply-management-access-ufw.sh',
            function_exists('base_path') ? base_path('../pbx3sbc/scripts/apply-management-access-ufw.sh') : '',
            function_exists('config') ? (string) config('pbx3_ops.management_access_apply_script', '') : '',
        ];
        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
