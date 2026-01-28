<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Fail2banStatusWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        // TODO: Implement Fail2ban status checking via fail2ban-client
        // For now, return placeholder stats
        
        return [
            Stat::make('Fail2ban Status', 'Not Configured')
                ->description('Fail2ban integration pending')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),

            Stat::make('Banned IPs', 'N/A')
                ->description('Fail2ban integration pending')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('gray'),
        ];
    }
}
