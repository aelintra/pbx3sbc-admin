<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DialogResource;
use App\Filament\Resources\DomainResource;
use App\Filament\Resources\LocationResource;
use App\Services\Fail2banService;
use App\Services\HomeDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class LivePostureWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    /** Live tables are small; still avoid sub-15s hammering. */
    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $live = app(HomeDashboardMetrics::class)->livePosture();

        $bannedCount = 0;
        $fail2banOk = true;
        try {
            $status = Cache::remember('fail2ban_status', 15, function () {
                try {
                    return app(Fail2banService::class)->getStatus();
                } catch (\Throwable) {
                    return null;
                }
            });
            if ($status === null) {
                $fail2banOk = false;
            } else {
                $bannedCount = (int) ($status['currently_banned'] ?? 0);
                if (! ($status['service_running'] ?? true)) {
                    $fail2banOk = false;
                }
            }
        } catch (\Throwable) {
            $fail2banOk = false;
        }

        return [
            Stat::make('Active dialogs', number_format($live['dialogs']))
                ->description($live['dialogs'] > 0 ? 'In progress on this edge' : 'No live dialogs')
                ->descriptionIcon('heroicon-m-phone')
                ->color($live['dialogs'] > 0 ? 'info' : 'gray')
                ->url(DialogResource::getUrl('index')),

            Stat::make('Registered AoRs', number_format($live['aors']))
                ->description('Contacts not yet expired')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('primary')
                ->url(LocationResource::getUrl('index')),

            Stat::make('Tenant domains', number_format($live['domains']))
                ->description('OpenSIPS domain table')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary')
                ->url(DomainResource::getUrl('index')),

            Stat::make('Banned IPs', $fail2banOk ? number_format($bannedCount) : '—')
                ->description($fail2banOk
                    ? ($bannedCount > 0 ? 'Currently blocked by Fail2ban' : 'No active bans')
                    : 'Fail2ban unavailable')
                ->descriptionIcon($fail2banOk
                    ? ($bannedCount > 0 ? 'heroicon-m-lock-closed' : 'heroicon-m-shield-check')
                    : 'heroicon-m-exclamation-triangle')
                ->color($fail2banOk
                    ? ($bannedCount > 0 ? 'warning' : 'success')
                    : 'danger')
                ->url(route('filament.admin.pages.fail2ban-status')),
        ];
    }
}
