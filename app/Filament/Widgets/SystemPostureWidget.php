<?php

namespace App\Filament\Widgets;

use App\Services\HomeDashboardMetrics;
use Filament\Widgets\Widget;

/**
 * Thin host pulse for Home — /proc + disk space only, with SPA-kinship usage meters.
 * No CPU% sampling sleep, no iostat. See workingdocs/HOME_SYSTEM_AND_FLEET_SCRAPE.md.
 */
class SystemPostureWidget extends Widget
{
    protected static string $view = 'filament.widgets.system-posture';

    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    /**
     * Continuous green → amber → red (SPA homePulse.usedPctSwatchColor kinship).
     */
    public static function usedPctColor(?float $pct): string
    {
        if ($pct === null || ! is_finite($pct)) {
            return '#94a3b8';
        }
        $t = min(100.0, max(0.0, $pct)) / 100.0;
        $green = [22, 163, 74];
        $amber = [234, 179, 8];
        $red = [220, 38, 38];
        if ($t <= 0.5) {
            $rgb = self::lerpRgb($green, $amber, $t / 0.5);

            return sprintf('rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2]);
        }
        $rgb = self::lerpRgb($amber, $red, ($t - 0.5) / 0.5);

        return sprintf('rgb(%d, %d, %d)', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     * @return array{0:int,1:int,2:int}
     */
    private static function lerpRgb(array $a, array $b, float $t): array
    {
        $u = min(1.0, max(0.0, $t));

        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $u),
            (int) round($a[1] + ($b[1] - $a[1]) * $u),
            (int) round($a[2] + ($b[2] - $a[2]) * $u),
        ];
    }

    /**
     * @return array{
     *     cards: list<array{
     *         label: string,
     *         value: string,
     *         meta: string,
     *         meter_pct: ?float,
     *         meter_color: string
     *     }>
     * }
     */
    protected function getViewData(): array
    {
        $s = app(HomeDashboardMetrics::class)->systemPosture();

        $cpus = max(1, (int) ($s['cpus'] ?? 1));
        $load1 = (float) ($s['load1'] ?? 0);
        $loadPct = min(100.0, max(0.0, ($load1 / $cpus) * 100.0));

        $mem = $s['mem_used_pct'];
        $memPct = $mem !== null ? min(100.0, max(0.0, (float) $mem)) : null;

        $disk = $s['disk_used_pct'];
        $diskPct = $disk !== null ? min(100.0, max(0.0, (float) $disk)) : null;

        $memMeta = $s['mem_total_mb'] !== null
            ? 'of '.round(((int) $s['mem_total_mb']) / 1024, 1).' GB'
            : '';
        $diskMeta = $s['disk_total_gb'] !== null
            ? 'of '.$s['disk_total_gb'].' GB'
            : '';

        return [
            'cards' => [
                [
                    'label' => 'Load',
                    'value' => sprintf('%.2f', $load1),
                    'meta' => '/ '.$cpus.' CPU',
                    'meter_pct' => $loadPct,
                    'meter_color' => self::usedPctColor($loadPct),
                ],
                [
                    'label' => 'Memory',
                    'value' => $memPct !== null ? ((int) round($memPct)).'%' : '—',
                    'meta' => $memMeta,
                    'meter_pct' => $memPct,
                    'meter_color' => self::usedPctColor($memPct),
                ],
                [
                    'label' => 'Disk',
                    'value' => $diskPct !== null ? ((int) round($diskPct)).'%' : '—',
                    'meta' => $diskMeta,
                    'meter_pct' => $diskPct,
                    'meter_color' => self::usedPctColor($diskPct),
                ],
            ],
        ];
    }
}
