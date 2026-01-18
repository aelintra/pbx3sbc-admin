<?php

namespace App\Filament\Resources\CdrResource\Pages;

use App\Filament\Resources\CdrResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCdr extends EditRecord
{
    protected static string $resource = CdrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
