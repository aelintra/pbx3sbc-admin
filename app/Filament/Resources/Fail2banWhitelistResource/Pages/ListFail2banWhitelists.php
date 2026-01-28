<?php

namespace App\Filament\Resources\Fail2banWhitelistResource\Pages;

use App\Filament\Resources\Fail2banWhitelistResource;
use App\Services\WhitelistSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFail2banWhitelists extends ListRecords
{
    protected static string $resource = Fail2banWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync')
                ->label('Sync to Fail2Ban')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync Whitelist to Fail2Ban')
                ->modalDescription('This will update the Fail2Ban configuration file and restart the service.')
                ->action(function () {
                    try {
                        $syncService = app(WhitelistSyncService::class);
                        if ($syncService->sync()) {
                            Notification::make()
                                ->title('Sync successful')
                                ->body('Whitelist synced to Fail2Ban successfully.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync failed')
                                ->body('Failed to sync whitelist to Fail2Ban. Check logs for details.')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync error')
                            ->body('Error syncing whitelist: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
