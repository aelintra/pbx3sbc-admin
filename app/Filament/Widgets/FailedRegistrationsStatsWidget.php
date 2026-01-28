<?php

namespace App\Filament\Widgets;

use App\Models\FailedRegistration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class FailedRegistrationsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $yesterdayStart = $now->copy()->subDay()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();
        $last24Hours = $now->copy()->subHours(24);

        // Last 24 hours
        $last24HoursTotal = FailedRegistration::where('attempt_time', '>=', $last24Hours)->count();
        $last24HoursBy403 = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->where('response_code', 403)
            ->count();
        $last24HoursBy4xx = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->whereBetween('response_code', [400, 499])
            ->count();
        $last24HoursBy5xx = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->whereBetween('response_code', [500, 599])
            ->count();

        // Today
        $todayTotal = FailedRegistration::where('attempt_time', '>=', $todayStart)->count();
        $todayBy403 = FailedRegistration::where('attempt_time', '>=', $todayStart)
            ->where('response_code', 403)
            ->count();

        // This week
        $weekTotal = FailedRegistration::where('attempt_time', '>=', $weekStart)->count();

        // This month
        $monthTotal = FailedRegistration::where('attempt_time', '>=', $monthStart)->count();

        // All-time
        $allTimeTotal = FailedRegistration::count();
        $allTimeBy403 = FailedRegistration::where('response_code', 403)->count();
        $allTimeBy4xx = FailedRegistration::whereBetween('response_code', [400, 499])->count();
        $allTimeBy5xx = FailedRegistration::whereBetween('response_code', [500, 599])->count();

        // Top IPs (last 24 hours)
        $topIps = FailedRegistration::where('attempt_time', '>=', $last24Hours)
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
            Stat::make('Failed Registrations (Last 24h)', number_format($last24HoursTotal))
                ->description("{$last24HoursBy403} with 403, {$last24HoursBy4xx} 4xx, {$last24HoursBy5xx} 5xx")
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($last24HoursTotal > 100 ? 'danger' : ($last24HoursTotal > 50 ? 'warning' : 'info'))
                ->chart([$last24HoursTotal]),

            Stat::make('Failed Registrations (Today)', number_format($todayTotal))
                ->description("{$todayBy403} with 403 Forbidden")
                ->descriptionIcon('heroicon-m-calendar')
                ->color($todayTotal > 50 ? 'warning' : 'info')
                ->chart([$todayTotal]),

            Stat::make('Failed Registrations (This Week)', number_format($weekTotal))
                ->description($topIpText)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart([$weekTotal]),

            Stat::make('Failed Registrations (This Month)', number_format($monthTotal))
                ->description("Total attempts this month")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary')
                ->chart([$monthTotal]),

            Stat::make('All-Time Total', number_format($allTimeTotal))
                ->description("{$allTimeBy403} 403, {$allTimeBy4xx} 4xx, {$allTimeBy5xx} 5xx")
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('success')
                ->chart([$allTimeTotal]),
        ];
    }
}
