<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DrRuleResource;
use App\Services\DrRulePrefixOverlap;
use App\Services\FleetDidProjector;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrRule extends EditRecord
{
    use HasPanelBackLink;

    protected static string $resource = DrRuleResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (FleetDidProjector::isFleetOwned($this->record->attrs)) {
            Notification::make()
                ->title('Fleet owns this delivery route')
                ->body('Retarget the DID or block in Fleet → DIDs (Allocate / reassign → Project). Magrathea cannot edit fleet=did Number routes.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(DrRuleResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->authorize(fn (): bool => DrRuleResource::canDelete($this->record))
                ->after(function () {
                    if (! app(OpenSIPSMIService::class)->drReload()) {
                        Notification::make()
                            ->title('Route deleted — reload failed')
                            ->body('The route was deleted, but OpenSIPS drouting reload (dr_reload) failed. Routing may be stale until reloaded.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        if (app(OpenSIPSMIService::class)->drReload()) {
            Notification::make()
                ->title('Routing rule saved')
                ->body('drouting reloaded (dr_reload).')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Routing rule saved — reload failed')
                ->body('The rule was saved, but OpenSIPS drouting reload (dr_reload) failed. Routing may be stale until reloaded.')
                ->warning()
                ->send();
        }

        $hint = DrRulePrefixOverlap::nestingHint(
            $this->record->groupid,
            $this->record->prefix,
            (int) $this->record->getKey()
        );
        if ($hint !== null) {
            Notification::make()
                ->title('Prefix overlap')
                ->body($hint)
                ->warning()
                ->send();
        }
    }
}
