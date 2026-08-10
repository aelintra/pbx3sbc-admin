<?php

namespace App\Filament\Resources\DrGatewayResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DrGatewayResource;
use App\Services\OpenSIPSMIService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDrGateway extends CreateRecord
{
    use HasPanelBackLink;

    protected static string $resource = DrGatewayResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return DrGatewayResource::applyCarrierFieldsToData($data);
    }

    protected function afterCreate(): void
    {
        if (app(OpenSIPSMIService::class)->drReload()) {
            Notification::make()
                ->title('Peer created')
                ->body('drouting reloaded (dr_reload).')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Peer created — reload failed')
                ->body('The peer was created, but OpenSIPS drouting reload (dr_reload) failed. Routing may be stale until reloaded.')
                ->warning()
                ->send();
        }
    }
}
