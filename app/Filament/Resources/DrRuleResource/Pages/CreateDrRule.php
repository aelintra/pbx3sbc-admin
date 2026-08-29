<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DrRuleResource;
use App\Services\DrRulePrefixOverlap;
use App\Services\OpenSIPSMIService;
use App\Support\FleetMode;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDrRule extends CreateRecord
{
    use HasPanelBackLink;

    protected static string $resource = DrRuleResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (FleetMode::joined()) {
            $data['groupid'] = '0';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (app(OpenSIPSMIService::class)->drReload()) {
            Notification::make()
                ->title('Routing rule created')
                ->body('drouting reloaded (dr_reload).')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Routing rule created — reload failed')
                ->body('The rule was created, but OpenSIPS drouting reload (dr_reload) failed. Routing may be stale until reloaded.')
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
