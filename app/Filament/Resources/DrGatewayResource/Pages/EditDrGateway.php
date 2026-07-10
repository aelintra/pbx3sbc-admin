<?php

namespace App\Filament\Resources\DrGatewayResource\Pages;

use App\Filament\Resources\DrGatewayResource;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrGateway extends EditRecord
{
    protected static string $resource = DrGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    app(OpenSIPSMIService::class)->drReload();
                }),
        ];
    }

    protected function afterSave(): void
    {
        app(OpenSIPSMIService::class)->drReload();
        Notification::make()
            ->title('Gateway saved')
            ->body('drouting reloaded (dr_reload).')
            ->success()
            ->send();
    }
}
