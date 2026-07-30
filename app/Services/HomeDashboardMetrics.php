<?php

namespace App\Services;

use App\Models\Cdr;
use App\Models\Dialog;
use App\Models\Domain;
use App\Models\DoorKnockAttempt;
use App\Models\FailedRegistration;
use App\Models\Location;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cached aggregates for SBC Filament Home.
 *
 * Keeps admin polls off the hot path: OpenSIPS owns dialog/location/acc writes;
 * Home reads short-TTL cache so Livewire poll ≠ full SQL every tick.
 * Door-knock / failed-reg are the costly tables — longer TTL there.
 */
class HomeDashboardMetrics
{
    public const TTL_LIVE = 20;

    public const TTL_SYSTEM = 45;

    public const TTL_CDR = 90;

    public const TTL_SECURITY = 60;

    public const TTL_SECURITY_TREND = 180;

    /**
     * @return array{dialogs: int, aors: int, domains: int}
     */
    public function livePosture(): array
    {
        return Cache::remember('home:live_posture', self::TTL_LIVE, function () {
            return [
                'dialogs' => Dialog::query()->whereIn('state', [1, 2, 3, 4])->count(),
                'aors' => Location::query()->where('expires', '>', time())->count(),
                'domains' => Domain::query()->count(),
            ];
        });
    }

    /**
     * Thin host pulse: /proc + disk_* only (no shell, no iostat).
     *
     * @return array{
     *     load1: float,
     *     load5: float,
     *     load15: float,
     *     cpus: int,
     *     mem_used_pct: ?float,
     *     mem_total_mb: ?int,
     *     disk_used_pct: ?float,
     *     disk_total_gb: ?float,
     *     disk_path: string
     * }
     */
    public function systemPosture(): array
    {
        return Cache::remember('home:system_posture', self::TTL_SYSTEM, function () {
            $load1 = 0.0;
            $load5 = 0.0;
            $load15 = 0.0;
            if (is_readable('/proc/loadavg')) {
                $parts = preg_split('/\s+/', trim((string) file_get_contents('/proc/loadavg'))) ?: [];
                $load1 = (float) ($parts[0] ?? 0);
                $load5 = (float) ($parts[1] ?? 0);
                $load15 = (float) ($parts[2] ?? 0);
            } elseif (function_exists('sys_getloadavg')) {
                $avg = sys_getloadavg();
                if (is_array($avg)) {
                    $load1 = (float) ($avg[0] ?? 0);
                    $load5 = (float) ($avg[1] ?? 0);
                    $load15 = (float) ($avg[2] ?? 0);
                }
            }

            $cpus = 1;
            if (is_readable('/proc/cpuinfo')) {
                $cpus = max(1, substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor'));
            }

            $memTotalKb = 0;
            $memAvailKb = 0;
            if (is_readable('/proc/meminfo')) {
                foreach (file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                    if (str_starts_with($line, 'MemTotal:')) {
                        $memTotalKb = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                    } elseif (str_starts_with($line, 'MemAvailable:')) {
                        $memAvailKb = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                    }
                }
            }

            $memUsedPct = null;
            $memTotalMb = null;
            if ($memTotalKb > 0) {
                $memTotalMb = (int) round($memTotalKb / 1024);
                $usedKb = max(0, $memTotalKb - $memAvailKb);
                $memUsedPct = round(($usedKb / $memTotalKb) * 100, 1);
            }

            $diskPath = '/';
            $diskTotal = @disk_total_space($diskPath);
            $diskFree = @disk_free_space($diskPath);
            $diskUsedPct = null;
            $diskTotalGb = null;
            if (is_float($diskTotal) && $diskTotal > 0 && is_float($diskFree)) {
                $diskTotalGb = round($diskTotal / (1024 ** 3), 1);
                $diskUsedPct = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
            }

            return [
                'load1' => round($load1, 2),
                'load5' => round($load5, 2),
                'load15' => round($load15, 2),
                'cpus' => $cpus,
                'mem_used_pct' => $memUsedPct,
                'mem_total_mb' => $memTotalMb,
                'disk_used_pct' => $diskUsedPct,
                'disk_total_gb' => $diskTotalGb,
                'disk_path' => $diskPath,
            ];
        });
    }

    /**
     * @return array{labels: list<string>, completed: list<int>, failed: list<int>}
     */
    public function callVolumeLast24h(): array
    {
        return Cache::remember('home:cdr_volume_24h', self::TTL_CDR, function () {
            $now = Carbon::now()->startOfHour();
            $start = $now->copy()->subHours(23);

            $rows = Cdr::query()
                ->where('created', '>=', $start)
                ->selectRaw("DATE_FORMAT(created, '%Y-%m-%d %H:00:00') as bucket")
                ->selectRaw('SUM(CASE WHEN sip_code = 200 THEN 1 ELSE 0 END) as completed')
                ->selectRaw('SUM(CASE WHEN sip_code != 200 OR sip_code IS NULL THEN 1 ELSE 0 END) as failed')
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->keyBy('bucket');

            $labels = [];
            $completed = [];
            $failed = [];

            for ($i = 0; $i < 24; $i++) {
                $hour = $start->copy()->addHours($i);
                $key = $hour->format('Y-m-d H:00:00');
                $labels[] = $hour->format('H:00');
                $row = $rows->get($key);
                $completed[] = (int) ($row->completed ?? 0);
                $failed[] = (int) ($row->failed ?? 0);
            }

            return compact('labels', 'completed', 'failed');
        });
    }

    /**
     * @return array{answered: int, no_answer: int, busy: int, other: int}
     */
    public function callOutcomeToday(): array
    {
        return Cache::remember('home:cdr_outcome_today', self::TTL_CDR, function () {
            $todayStart = Carbon::now()->startOfDay();

            $rows = Cdr::query()
                ->where('created', '>=', $todayStart)
                ->selectRaw('sip_code, COUNT(*) as c')
                ->groupBy('sip_code')
                ->pluck('c', 'sip_code');

            $answered = 0;
            $noAnswer = 0;
            $busy = 0;
            $other = 0;

            foreach ($rows as $code => $count) {
                $count = (int) $count;
                $code = (int) $code;

                if ($code === 200) {
                    $answered += $count;
                } elseif (in_array($code, [486, 600, 603], true)) {
                    $busy += $count;
                } elseif (in_array($code, [408, 480, 487, 484, 404], true)) {
                    $noAnswer += $count;
                } else {
                    $other += $count;
                }
            }

            return [
                'answered' => $answered,
                'no_answer' => $noAnswer,
                'busy' => $busy,
                'other' => $other,
            ];
        });
    }

    /**
     * @return array{
     *     door_knocks: int,
     *     scanners: int,
     *     failed_regs: int,
     *     forbidden: int,
     *     high_risk_count: int,
     *     top_risk: ?string
     * }
     */
    public function securityPulse24h(): array
    {
        return Cache::remember('home:security_pulse_24h', self::TTL_SECURITY, function () {
            $since = Carbon::now()->subHours(24);

            $door = DoorKnockAttempt::query()
                ->where('attempt_time', '>=', $since)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN reason = 'scanner_detected' THEN 1 ELSE 0 END) as scanners")
                ->first();

            $fail = FailedRegistration::query()
                ->where('attempt_time', '>=', $since)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN response_code = 403 THEN 1 ELSE 0 END) as forbidden')
                ->first();

            $highRisk = FailedRegistration::query()
                ->where('attempt_time', '>=', $since)
                ->selectRaw('source_ip, COUNT(*) as count')
                ->groupBy('source_ip')
                ->havingRaw('COUNT(*) >= 10')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            $top = $highRisk->first();

            return [
                'door_knocks' => (int) ($door->total ?? 0),
                'scanners' => (int) ($door->scanners ?? 0),
                'failed_regs' => (int) ($fail->total ?? 0),
                'forbidden' => (int) ($fail->forbidden ?? 0),
                'high_risk_count' => $highRisk->count(),
                'top_risk' => $top ? "{$top->source_ip} ({$top->count})" : null,
            ];
        });
    }

    /**
     * @return array{labels: list<string>, door: list<int>, fail: list<int>}
     */
    public function securityTrend7d(): array
    {
        return Cache::remember('home:security_trend_7d', self::TTL_SECURITY_TREND, function () {
            $start = Carbon::now()->subDays(6)->startOfDay();

            $doorRows = DoorKnockAttempt::query()
                ->where('attempt_time', '>=', $start)
                ->selectRaw('DATE(attempt_time) as day, COUNT(*) as c')
                ->groupBy('day')
                ->pluck('c', 'day');

            $failRows = FailedRegistration::query()
                ->where('attempt_time', '>=', $start)
                ->selectRaw('DATE(attempt_time) as day, COUNT(*) as c')
                ->groupBy('day')
                ->pluck('c', 'day');

            $labels = [];
            $door = [];
            $fail = [];

            for ($i = 0; $i < 7; $i++) {
                $day = $start->copy()->addDays($i);
                $key = $day->toDateString();
                $labels[] = $day->format('D');
                $door[] = (int) ($doorRows[$key] ?? 0);
                $fail[] = (int) ($failRows[$key] ?? 0);
            }

            return compact('labels', 'door', 'fail');
        });
    }
}
