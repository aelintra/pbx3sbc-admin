<?php

namespace App\Filament\Widgets;

use App\Models\Cdr;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class CdrStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        // Today's statistics
        $todayTotal = Cdr::where('created', '>=', $todayStart)->count();
        $todaySuccessful = Cdr::where('created', '>=', $todayStart)->successful()->count();
        $todayFailed = Cdr::where('created', '>=', $todayStart)->failed()->count();
        $todayAvgDuration = Cdr::where('created', '>=', $todayStart)
            ->whereNotNull('duration')
            ->avg('duration') ?? 0;
        $todaySuccessRate = $todayTotal > 0 ? round(($todaySuccessful / $todayTotal) * 100, 1) : 0;

        // This week's statistics
        $weekTotal = Cdr::where('created', '>=', $weekStart)->count();
        $weekSuccessful = Cdr::where('created', '>=', $weekStart)->successful()->count();
        $weekSuccessRate = $weekTotal > 0 ? round(($weekSuccessful / $weekTotal) * 100, 1) : 0;

        // This month's statistics
        $monthTotal = Cdr::where('created', '>=', $monthStart)->count();
        $monthSuccessful = Cdr::where('created', '>=', $monthStart)->successful()->count();
        $monthSuccessRate = $monthTotal > 0 ? round(($monthSuccessful / $monthTotal) * 100, 1) : 0;

        // All-time statistics
        $allTimeTotal = Cdr::count();
        $allTimeSuccessful = Cdr::successful()->count();
        $allTimeFailed = Cdr::failed()->count();
        $allTimeAvgDuration = Cdr::whereNotNull('duration')->avg('duration') ?? 0;
        $allTimeSuccessRate = $allTimeTotal > 0 ? round(($allTimeSuccessful / $allTimeTotal) * 100, 1) : 0;

        // Format duration (seconds to MM:SS)
        $formatDuration = function ($seconds) {
            if (!$seconds || $seconds == 0) {
                return '0:00';
            }
            $minutes = floor($seconds / 60);
            $secs = round($seconds % 60);
            return sprintf('%d:%02d', $minutes, $secs);
        };

        return [
            Stat::make('Total Calls (Today)', number_format($todayTotal))
                ->description("{$todaySuccessful} successful, {$todayFailed} failed")
                ->descriptionIcon('heroicon-m-phone')
                ->color('primary')
                ->chart([$todayTotal]),

            Stat::make('Success Rate (Today)', $todaySuccessRate . '%')
                ->description($todayTotal > 0 ? "{$todaySuccessful} of {$todayTotal} calls" : 'No calls today')
                ->descriptionIcon($todaySuccessRate >= 95 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($todaySuccessRate >= 95 ? 'success' : ($todaySuccessRate >= 80 ? 'warning' : 'danger'))
                ->chart([$todaySuccessRate]),

            Stat::make('Avg Duration (Today)', $formatDuration($todayAvgDuration))
                ->description($todayTotal > 0 ? "Based on {$todayTotal} calls" : 'No calls today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('Total Calls (This Week)', number_format($weekTotal))
                ->description("{$weekSuccessful} successful ({$weekSuccessRate}%)")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->chart([$weekTotal]),

            Stat::make('Total Calls (This Month)', number_format($monthTotal))
                ->description("{$monthSuccessful} successful ({$monthSuccessRate}%)")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart([$monthTotal]),

            Stat::make('All-Time Total', number_format($allTimeTotal))
                ->description("{$allTimeSuccessful} successful, {$allTimeFailed} failed ({$allTimeSuccessRate}%)")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success')
                ->chart([$allTimeTotal]),

            Stat::make('All-Time Avg Duration', $formatDuration($allTimeAvgDuration))
                ->description("Based on {$allTimeTotal} total calls")
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
