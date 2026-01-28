<?php

namespace App\Filament\Resources\Fail2banWhitelistResource\Pages;

use App\Filament\Resources\Fail2banWhitelistResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFail2banWhitelist extends ViewRecord
{
    protected static string $resource = Fail2banWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
