<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DoorKnockAttemptResource;
use App\Filament\Resources\FailedRegistrationResource;
use App\Models\DoorKnockAttempt;
use App\Models\FailedRegistration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SecurityPulseWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $last24Hours = Carbon::now()->subHours(24);

        $doorKnocks = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)->count();
        $scanners = DoorKnockAttempt::where('attempt_time', '>=', $last24Hours)
            ->where('reason', 'scanner_detected')
            ->count();

        $failedRegs = FailedRegistration::where('attempt_time', '>=', $last24Hours)->count();
        $forbidden = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->where('response_code', 403)
            ->count();

        $highRiskIps = FailedRegistration::where('attempt_time', '>=', $last24Hours)
            ->selectRaw('source_ip, COUNT(*) as count')
            ->groupBy('source_ip')
            ->havingRaw('COUNT(*) >= 10')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $highRiskCount = $highRiskIps->count();
        $top = $highRiskIps->first();
        $topText = $top
            ? "{$top->source_ip} ({$top->count})"
            : 'None';

        return [
            Stat::make('Door-knocks (24h)', number_format($doorKnocks))
                ->description("{$scanners} scanner detections")
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($doorKnocks > 100 ? 'danger' : ($doorKnocks > 50 ? 'warning' : 'info'))
                ->url(DoorKnockAttemptResource::getUrl('index')),

            Stat::make('Failed REGISTER (24h)', number_format($failedRegs))
                ->description("{$forbidden} with 403 Forbidden")
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($failedRegs > 100 ? 'danger' : ($failedRegs > 50 ? 'warning' : 'gray'))
                ->url(FailedRegistrationResource::getUrl('index')),

            Stat::make('High-risk IPs (24h)', $highRiskCount > 0 ? number_format($highRiskCount) : 'None')
                ->description($highRiskCount > 0 ? "Top: {$topText}" : 'No IP ≥10 failed REGISTER')
                ->descriptionIcon($highRiskCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($highRiskCount > 0 ? 'danger' : 'success')
                ->url(FailedRegistrationResource::getUrl('index')),
        ];
    }
}
