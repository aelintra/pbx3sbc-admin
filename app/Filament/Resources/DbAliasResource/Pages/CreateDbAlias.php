<?php

namespace App\Filament\Resources\DbAliasResource\Pages;

use App\Filament\Resources\DbAliasResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDbAlias extends CreateRecord
{
    protected static string $resource = DbAliasResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('DID alias created')
            ->body('alias_db reads MySQL on each lookup — no OpenSIPS reload required.')
            ->success()
            ->send();
    }
}
