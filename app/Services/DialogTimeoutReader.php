<?php

namespace App\Services;

/**
 * Read OpenSIPS dialog default_timeout from opensips.cfg (HoR).
 * Filament shows this read-only — no Laravel store; change via cfg + restart.
 */
class DialogTimeoutReader
{
    /** OpenSIPS module default when modparam is omitted (12 hours). */
    public const OPENSIPS_DEFAULT_SECONDS = 43200;

    /**
     * @return array{
     *     seconds: int,
     *     human: string,
     *     source: 'cfg'|'opensips_default'|'unreadable',
     *     cfg_path: string,
     *     error: string|null
     * }
     */
    public function read(): array
    {
        $path = (string) config('opensips.cfg_path', '/etc/opensips/opensips.cfg');

        if (! is_readable($path)) {
            return [
                'seconds' => self::OPENSIPS_DEFAULT_SECONDS,
                'human' => $this->human(self::OPENSIPS_DEFAULT_SECONDS),
                'source' => 'unreadable',
                'cfg_path' => $path,
                'error' => "Cannot read {$path}",
            ];
        }

        $text = @file_get_contents($path);
        if ($text === false) {
            return [
                'seconds' => self::OPENSIPS_DEFAULT_SECONDS,
                'human' => $this->human(self::OPENSIPS_DEFAULT_SECONDS),
                'source' => 'unreadable',
                'cfg_path' => $path,
                'error' => "Failed to read {$path}",
            ];
        }

        if (preg_match('/^\s*modparam\s*\(\s*"dialog"\s*,\s*"default_timeout"\s*,\s*(\d+)\s*\)/m', $text, $m)) {
            $seconds = (int) $m[1];

            return [
                'seconds' => $seconds,
                'human' => $this->human($seconds),
                'source' => 'cfg',
                'cfg_path' => $path,
                'error' => null,
            ];
        }

        return [
            'seconds' => self::OPENSIPS_DEFAULT_SECONDS,
            'human' => $this->human(self::OPENSIPS_DEFAULT_SECONDS),
            'source' => 'opensips_default',
            'cfg_path' => $path,
            'error' => null,
        ];
    }

    public function human(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];
        if ($h > 0) {
            $parts[] = $h.'h';
        }
        if ($m > 0) {
            $parts[] = $m.'m';
        }
        if ($s > 0 && $h === 0) {
            $parts[] = $s.'s';
        }

        return $parts !== [] ? implode(' ', $parts) : '0s';
    }
}
