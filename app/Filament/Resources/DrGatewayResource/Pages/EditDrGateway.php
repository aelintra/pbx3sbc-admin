<?php

namespace App\Filament\Resources\DrGatewayResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DrGatewayResource;
use App\Models\DrGateway;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrGateway extends EditRecord
{
    use HasPanelBackLink;

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var DrGateway $record */
        $record = $this->getRecord();
        $slug = $record->carrierSlug();
        $data['carrier_label'] = $slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : '';
        $data['peer_role'] = $record->peerRole() ?: null;
        $dialect = $record->numberDialect();
        $data['number_dialect'] = $dialect !== '' ? $dialect : 'none';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return DrGatewayResource::applyCarrierFieldsToData($data);
    }

    protected function afterSave(): void
    {
        app(OpenSIPSMIService::class)->drReload();
        Notification::make()
            ->title('Peer saved')
            ->body('drouting reloaded (dr_reload).')
            ->success()
            ->send();
    }
}
