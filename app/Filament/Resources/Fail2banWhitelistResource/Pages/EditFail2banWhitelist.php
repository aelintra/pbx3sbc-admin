<?php

namespace App\Filament\Resources\Fail2banWhitelistResource\Pages;

use App\Filament\Resources\Fail2banWhitelistResource;
use App\Services\WhitelistSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFail2banWhitelist extends EditRecord
{
    protected static string $resource = Fail2banWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    // Sync after deletion
                    app(WhitelistSyncService::class)->sync();
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Sync whitelist to Fail2Ban config after update
        try {
            $syncService = app(WhitelistSyncService::class);
            if ($syncService->sync()) {
                Notification::make()
                    ->title('Whitelist synced')
                    ->body('Whitelist entry updated and synced to Fail2Ban successfully.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Sync failed')
                    ->body('Whitelist entry updated but sync to Fail2Ban failed. Please sync manually.')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Sync error')
                ->body('Whitelist entry updated but sync failed: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
