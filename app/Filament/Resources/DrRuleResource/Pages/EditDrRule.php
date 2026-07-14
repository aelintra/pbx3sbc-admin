<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Resources\DrRuleResource;
use App\Services\DrRulePrefixOverlap;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDrRule extends EditRecord
{
    protected static string $resource = DrRuleResource::class;

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
            ->title('Routing rule saved')
            ->body('drouting reloaded (dr_reload).')
            ->success()
            ->send();

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
