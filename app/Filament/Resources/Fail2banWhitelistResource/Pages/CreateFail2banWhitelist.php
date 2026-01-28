<?php

namespace App\Filament\Resources\Fail2banWhitelistResource\Pages;

use App\Filament\Resources\Fail2banWhitelistResource;
use App\Services\WhitelistSyncService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFail2banWhitelist extends CreateRecord
{
    protected static string $resource = Fail2banWhitelistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to current user
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        // Sync whitelist to Fail2Ban config
        try {
            $syncService = app(WhitelistSyncService::class);
            if ($syncService->sync()) {
                Notification::make()
                    ->title('Whitelist synced')
                    ->body('Whitelist entry created and synced to Fail2Ban successfully.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Sync failed')
                    ->body('Whitelist entry created but sync to Fail2Ban failed. Please sync manually.')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Sync error')
                ->body('Whitelist entry created but sync failed: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
