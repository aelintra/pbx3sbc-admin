<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DoorKnockAttemptResource;
use App\Filament\Resources\FailedRegistrationResource;
use App\Services\HomeDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SecurityPulseWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $p = app(HomeDashboardMetrics::class)->securityPulse24h();

        return [
            Stat::make('Door-knocks (24h)', number_format($p['door_knocks']))
                ->description("{$p['scanners']} scanner detections")
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($p['door_knocks'] > 100 ? 'danger' : ($p['door_knocks'] > 50 ? 'warning' : 'info'))
                ->url(DoorKnockAttemptResource::getUrl('index')),

            Stat::make('Failed REGISTER (24h)', number_format($p['failed_regs']))
                ->description("{$p['forbidden']} with 403 Forbidden")
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($p['failed_regs'] > 100 ? 'danger' : ($p['failed_regs'] > 50 ? 'warning' : 'gray'))
                ->url(FailedRegistrationResource::getUrl('index')),

            Stat::make('High-risk IPs (24h)', $p['high_risk_count'] > 0 ? number_format($p['high_risk_count']) : 'None')
                ->description($p['high_risk_count'] > 0 ? "Top: {$p['top_risk']}" : 'No IP ≥10 failed REGISTER')
                ->descriptionIcon($p['high_risk_count'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($p['high_risk_count'] > 0 ? 'danger' : 'success')
                ->url(FailedRegistrationResource::getUrl('index')),
        ];
    }
}
