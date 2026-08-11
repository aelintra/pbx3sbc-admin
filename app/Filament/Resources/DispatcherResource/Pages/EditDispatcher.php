<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Services\FleetDomainOwnership;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDispatcher extends EditRecord
{
    use HasPanelBackLink;

    protected static string $resource = DispatcherResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (! FleetDomainOwnership::destinationMutateAllowed($this->record)) {
            Notification::make()
                ->title('Fleet owns this destination')
                ->body('Change backends via Fleet Instances / node provision. Magrathea cannot edit fleet-locked destinations.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(CallRouteResource::getUrl('index'));
        }
    }

    protected function getPanelBackUrl(): string
    {
        $setid = $this->record->setid ?? null;

        if ($setid !== null) {
            return DispatcherResource::getUrl('index', [
                'tableFilters' => [
                    'setid' => [
                        'value' => $setid,
                    ],
                ],
            ]);
        }

        return DispatcherResource::getUrl('index');
    }

    protected function getPanelBackLabel(): string
    {
        return 'Destinations';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => DispatcherResource::canDelete($this->record))
                ->after(function () {
                    if (! app(OpenSIPSMIService::class)->dispatcherReload()) {
                        Notification::make()
                            ->title('Destination deleted — reload failed')
                            ->body('The destination was deleted, but OpenSIPS dispatcher reload failed. Routing may be stale until reloaded.')
                            ->warning()
                            ->send();
                    }
                })
                ->successRedirectUrl(function () {
                    // Preserve setid filter after deletion from edit page
                    $setid = $this->record->setid ?? null;
                    
                    // Fall back to URL query parameter
                    if ($setid === null) {
                        $setid = request()->query('tableFilters.setid.value') 
                            ?? (request()->query('tableFilters')['setid']['value'] ?? null);
                    }
                    
                    if ($setid !== null) {
                        return DispatcherResource::getUrl('index', [
                            'tableFilters' => [
                                'setid' => [
                                    'value' => $setid,
                                ],
                            ],
                        ]);
                    }
                    
                    return DispatcherResource::getUrl('index');
                }),
        ];
    }

    protected function afterSave(): void
    {
        if (! app(OpenSIPSMIService::class)->dispatcherReload()) {
            Notification::make()
                ->title('Destination saved — reload failed')
                ->body('The destination was saved, but OpenSIPS dispatcher reload failed. Routing may be stale until reloaded.')
                ->warning()
                ->send();
        }
    }
}
