<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Edge admin TLS — Let's Encrypt + purchased cert (SPA Certificates panel kinship).
 */
class LetsEncryptService
{
    public function scriptPath(): string
    {
        $path = base_path('scripts/le-admin-cert.sh');
        if (! is_file($path)) {
            throw new RuntimeException("LE helper not found at {$path}");
        }

        return $path;
    }

    public function fqdn(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : (string) request()->getHost();
    }

    public function webroot(): string
    {
        return public_path();
    }

    /**
     * @return array{configured: bool, domain?: string, expires_at?: string|null, issuer?: string|null}
     */
    public function status(?string $fqdn = null): array
    {
        $fqdn ??= $this->fqdn();
        $result = $this->run(['status', $fqdn]);
        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : ['configured' => false, 'domain' => $fqdn];
    }

    /**
     * @return array{configured: bool, domain?: string, expires_at?: string|null, issuer?: string|null}
     */
    public function setup(string $email, ?string $fqdn = null): array
    {
        $fqdn ??= $this->fqdn();
        $result = $this->run(['setup', $fqdn, $email, $this->webroot()], timeout: 180);
        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : ['configured' => true, 'domain' => $fqdn];
    }

    /**
     * @return array{configured: bool, domain?: string, expires_at?: string|null, issuer?: string|null}
     */
    public function renew(?string $fqdn = null): array
    {
        $fqdn ??= $this->fqdn();
        $result = $this->run(['renew', $fqdn], timeout: 180);
        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : ['configured' => true, 'domain' => $fqdn];
    }

    /**
     * @return 'letsencrypt'|'custom'|'none'
     */
    public function activeSource(): string
    {
        $nginx = @file_get_contents('/etc/nginx/sites-available/pbx3sbc-admin') ?: '';
        if (str_contains($nginx, '/var/lib/pbx3sbc-admin/custom-tls/')) {
            return 'custom';
        }
        $fqdn = $this->fqdn();
        if (is_file("/etc/letsencrypt/live/{$fqdn}/fullchain.pem") || str_contains($nginx, '/etc/letsencrypt/live/')) {
            return 'letsencrypt';
        }

        return 'none';
    }

    public function customInstalled(): bool
    {
        return is_file('/var/lib/pbx3sbc-admin/custom-tls/fullchain.pem')
            && is_file('/var/lib/pbx3sbc-admin/custom-tls/privkey.pem');
    }

    public function installCustom(string $certPath, string $keyPath): void
    {
        $this->run(['custom-install', $this->fqdn(), $certPath, $keyPath, $this->webroot()], timeout: 60);
    }

    public function removeCustom(): void
    {
        $this->run(['custom-remove', $this->fqdn(), $this->webroot()], timeout: 60);
    }

    /**
     * @param  list<string>  $args
     */
    private function run(array $args, int $timeout = 30): string
    {
        $cmd = array_merge(['sudo', $this->scriptPath()], $args);
        $result = Process::timeout($timeout)->run($cmd);
        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output()) ?: 'command failed';
            throw new RuntimeException($err);
        }

        return trim($result->output());
    }
}
