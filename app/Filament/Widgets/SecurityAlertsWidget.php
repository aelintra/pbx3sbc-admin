<?php

namespace App\Filament\Widgets;

use App\Models\FailedRegistration;
use App\Models\DoorKnockAttempt;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SecurityAlertsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $now = Carbon::now();
        $last24Hours = $now->copy()->subHours(24);

        // High-risk IPs (many failures in last 24h)
        $highRiskIps = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->selectRaw('source_ip, COUNT(*) as count')
            ->groupBy('source_ip')
            ->havingRaw('COUNT(*) >= 10')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $highRiskCount = $highRiskIps->count();
        $topRiskIp = $highRiskIps->first();
        $topRiskText = $topRiskIp 
            ? $topRiskIp->source_ip . ' (' . $topRiskIp->count . ' attempts)' 
            : 'None';

        // Recent scanner detections
        $recentScanners = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)
            ->where('reason', 'scanner_detected')
            ->count();

        // Recent 403 errors
        $recent403s = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->where('response_code', 403)
            ->count();

        return [
            Stat::make('High-Risk IPs (24h)', $highRiskCount > 0 ? number_format($highRiskCount) : 'None')
                ->description($highRiskCount > 0 ? "Top: {$topRiskText}" : 'No high-risk IPs detected')
                ->descriptionIcon($highRiskCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($highRiskCount > 0 ? 'danger' : 'success'),

            Stat::make('Scanner Detections (24h)', number_format($recentScanners))
                ->description($recentScanners > 0 ? 'Potential security threat' : 'No scanners detected')
                ->descriptionIcon($recentScanners > 0 ? 'heroicon-m-shield-exclamation' : 'heroicon-m-shield-check')
                ->color($recentScanners > 0 ? 'warning' : 'success'),

            Stat::make('403 Forbidden (24h)', number_format($recent403s))
                ->description($recent403s > 0 ? 'Unauthorized access attempts' : 'No 403 errors')
                ->descriptionIcon($recent403s > 0 ? 'heroicon-m-lock-closed' : 'heroicon-m-check-circle')
                ->color($recent403s > 0 ? 'warning' : 'success'),
        ];
    }
}
