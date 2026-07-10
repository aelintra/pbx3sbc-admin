<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Resources\DrRuleResource;
use App\Services\OpenSIPSMIService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDrRule extends CreateRecord
{
    protected static string $resource = DrRuleResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        app(OpenSIPSMIService::class)->drReload();
        Notification::make()
            ->title('Routing rule created')
            ->body('drouting reloaded (dr_reload).')
            ->success()
            ->send();
    }
}
