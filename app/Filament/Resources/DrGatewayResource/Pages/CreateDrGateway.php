<?php

namespace App\Filament\Resources\DrGatewayResource\Pages;

use App\Filament\Resources\DrGatewayResource;
use App\Services\OpenSIPSMIService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDrGateway extends CreateRecord
{
    protected static string $resource = DrGatewayResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        app(OpenSIPSMIService::class)->drReload();
        Notification::make()
            ->title('Gateway created')
            ->body('drouting reloaded (dr_reload).')
            ->success()
            ->send();
    }
}
