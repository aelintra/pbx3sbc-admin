<?php

namespace App\Filament\Widgets;

use App\Services\Fail2banService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class Fail2banStatusWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        try {
            $fail2banService = app(Fail2banService::class);
            
            // Cache status for 10 seconds
            $status = Cache::remember('fail2ban_status', 10, function () use ($fail2banService) {
                try {
                    return $fail2banService->getStatus();
                } catch (\Exception $e) {
                    return null;
                }
            });
            
            if (!$status) {
                return [
                    Stat::make('Fail2ban Status', 'Unavailable')
                        ->description('Unable to connect to Fail2Ban')
                        ->descriptionIcon('heroicon-m-exclamation-triangle')
                        ->color('gray'),
                ];
            }
            
            $bannedCount = $status['currently_banned'] ?? 0;
            $isEnabled = $status['enabled'] ?? false;
            
            return [
                Stat::make('Fail2ban Status', $isEnabled ? 'Enabled' : 'Disabled')
                    ->description($isEnabled ? 'Jail is active' : 'Jail is disabled')
                    ->descriptionIcon($isEnabled ? 'heroicon-m-shield-check' : 'heroicon-m-shield-exclamation')
                    ->color($isEnabled ? 'success' : 'danger')
                    ->url($isEnabled ? route('filament.admin.pages.fail2ban-status') : null),
                
                Stat::make('Banned IPs', $bannedCount)
                    ->description($bannedCount > 0 ? 'Currently blocked' : 'No active bans')
                    ->descriptionIcon($bannedCount > 0 ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                    ->color($bannedCount > 0 ? 'warning' : 'success')
                    ->url(route('filament.admin.pages.fail2ban-status')),
            ];
        } catch (\Exception $e) {
            return [
                Stat::make('Fail2ban Status', 'Error')
                    ->description('Failed to load status')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}
