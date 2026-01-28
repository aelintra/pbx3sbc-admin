<?php

namespace App\Filament\Widgets;

use App\Models\DoorKnockAttempt;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class DoorKnockAttemptsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();
        $last24Hours = $now->copy()->subHours(24);

        // Last 24 hours
        $last24HoursTotal = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)->count();
        $last24HoursScanners = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)
            ->where('reason', 'scanner_detected')
            ->count();
        $last24HoursDomainNotFound = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)
            ->where('reason', 'domain_not_found')
            ->count();

        // Today
        $todayTotal = DoorKnockAttempt::where('attempt_time', '>=', $todayStart)->count();
        $todayScanners = DoorKnockAttempt::where('attempt_time', '>=', $todayStart)
            ->where('reason', 'scanner_detected')
            ->count();

        // This week
        $weekTotal = DoorKnockAttempt::where('attempt_time', '>=', $weekStart)->count();

        // This month
        $monthTotal = DoorKnockAttempt::where('attempt_time', '>=', $monthStart)->count();

        // All-time
        $allTimeTotal = DoorKnockAttempt::count();
        $allTimeScanners = DoorKnockAttempt::where('reason', 'scanner_detected')->count();
        $allTimeDomainNotFound = DoorKnockAttempt::where('reason', 'domain_not_found')->count();

        // Top IPs (last 24 hours)
        $topIps = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)
            ->selectRaw('source_ip, COUNT(*) as count')
            ->groupBy('source_ip')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'source_ip')
            ->toArray();

        $topIpText = !empty($topIps) 
            ? 'Top: ' . array_key_first($topIps) . ' (' . reset($topIps) . ')' 
            : 'No attempts';

        return [
            Stat::make('Door-Knock Attempts (Last 24h)', number_format($last24HoursTotal))
                ->description("{$last24HoursScanners} scanners, {$last24HoursDomainNotFound} domain not found")
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($last24HoursTotal > 100 ? 'danger' : ($last24HoursTotal > 50 ? 'warning' : 'info'))
                ->chart([$last24HoursTotal]),

            Stat::make('Door-Knock Attempts (Today)', number_format($todayTotal))
                ->description("{$todayScanners} scanner detections")
                ->descriptionIcon('heroicon-m-calendar')
                ->color($todayTotal > 50 ? 'warning' : 'info')
                ->chart([$todayTotal]),

            Stat::make('Door-Knock Attempts (This Week)', number_format($weekTotal))
                ->description($topIpText)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart([$weekTotal]),

            Stat::make('Door-Knock Attempts (This Month)', number_format($monthTotal))
                ->description("Total attempts this month")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary')
                ->chart([$monthTotal]),

            Stat::make('All-Time Total', number_format($allTimeTotal))
                ->description("{$allTimeScanners} scanners, {$allTimeDomainNotFound} domain not found")
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('success')
                ->chart([$allTimeTotal]),
        ];
    }
}
