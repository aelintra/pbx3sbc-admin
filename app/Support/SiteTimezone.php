<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeZone;
use Throwable;

/**
 * Node site timezone for CDR presentation / day buckets (OS /etc/timezone).
 * OpenSIPS acc `created` is compared as UTC wall when the host clock is UTC
 * (typical AWS image); Filament displays via site TZ.
 *
 * @see pbx3/workingdocs/CDR_TIMEZONE_POLICY.md
 */
class SiteTimezone
{
    public static function id(): string
    {
        $override = config('pbx3_ops.site_timezone');
        if (is_string($override) && trim($override) !== '') {
            return self::normalize(trim($override));
        }

        $path = (string) config('pbx3_ops.timezone_file', '/etc/timezone');
        if (is_readable($path)) {
            $raw = trim((string) @file_get_contents($path));
            if ($raw !== '') {
                return self::normalize($raw);
            }
        }

        return 'UTC';
    }

    public static function zone(): DateTimeZone
    {
        try {
            return new DateTimeZone(self::id());
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Start of site-local "today" as a UTC wall string for SQL vs acc.created.
     */
    public static function todayStartUtc(): string
    {
        return Carbon::now(self::zone())
            ->startOfDay()
            ->utc()
            ->format('Y-m-d H:i:s');
    }

    /**
     * Calendar day + optional HH:MM in site TZ → UTC wall string for SQL.
     */
    public static function siteLocalToUtc(string $date, string $time = '00:00:00', bool $endOfMinute = false): string
    {
        $date = trim($date);
        $time = trim($time);
        if ($time === '') {
            $time = '00:00:00';
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= $endOfMinute ? ':59' : ':00';
        }

        return Carbon::parse($date.' '.$time, self::zone())
            ->utc()
            ->format('Y-m-d H:i:s');
    }

    private static function normalize(string $id): string
    {
        try {
            new DateTimeZone($id);

            return $id;
        } catch (Throwable) {
            return 'UTC';
        }
    }
}
