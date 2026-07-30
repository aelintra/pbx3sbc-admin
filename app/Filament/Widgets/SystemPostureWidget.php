<?php

namespace App\Filament\Widgets;

use App\Services\HomeDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Thin host pulse for Home — /proc + disk space only.
 * No CPU% sampling sleep, no iostat. See workingdocs/HOME_SYSTEM_AND_FLEET_SCRAPE.md.
 */
class SystemPostureWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $s = app(HomeDashboardMetrics::class)->systemPosture();

        $loadHot = $s['load1'] >= max(1, $s['cpus']);
        $loadWarn = $s['load1'] >= max(1, $s['cpus']) * 0.7;

        $mem = $s['mem_used_pct'];
        $memHot = $mem !== null && $mem >= 90;
        $memWarn = $mem !== null && $mem >= 75;

        $disk = $s['disk_used_pct'];
        $diskHot = $disk !== null && $disk >= 90;
        $diskWarn = $disk !== null && $disk >= 80;

        return [
            Stat::make('Load average', sprintf('%.2f / %.2f / %.2f', $s['load1'], $s['load5'], $s['load15']))
                ->description("1 / 5 / 15 min · {$s['cpus']} CPU(s)")
                ->descriptionIcon($loadHot ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-cpu-chip')
                ->color($loadHot ? 'danger' : ($loadWarn ? 'warning' : 'success')),

            Stat::make('Memory', $mem !== null ? $mem.'%' : '—')
                ->description($s['mem_total_mb'] !== null
                    ? "Used of {$s['mem_total_mb']} MiB (MemAvailable)"
                    : 'Meminfo unavailable')
                ->descriptionIcon($memHot ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-circle-stack')
                ->color($mem === null ? 'gray' : ($memHot ? 'danger' : ($memWarn ? 'warning' : 'success'))),

            Stat::make('Disk '.$s['disk_path'], $disk !== null ? $disk.'%' : '—')
                ->description($s['disk_total_gb'] !== null
                    ? "Used of {$s['disk_total_gb']} GiB"
                    : 'Disk stats unavailable')
                ->descriptionIcon($diskHot ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-server-stack')
                ->color($disk === null ? 'gray' : ($diskHot ? 'danger' : ($diskWarn ? 'warning' : 'success'))),
        ];
    }
}
