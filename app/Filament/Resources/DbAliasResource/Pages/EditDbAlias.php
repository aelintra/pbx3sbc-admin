<?php

namespace App\Filament\Resources\DbAliasResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DbAliasResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDbAlias extends EditRecord
{
    use HasPanelBackLink;

    protected static string $resource = DbAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function (): void {
                    Notification::make()
                        ->title('DID alias deleted')
                        ->body('alias_db reads MySQL on each lookup — no OpenSIPS reload required.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('DID alias updated')
            ->body('alias_db reads MySQL on each lookup — no OpenSIPS reload required.')
            ->success()
            ->send();
    }
}
