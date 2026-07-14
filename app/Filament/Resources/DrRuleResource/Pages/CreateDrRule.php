<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Resources\DrRuleResource;
use App\Services\DrRulePrefixOverlap;
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
