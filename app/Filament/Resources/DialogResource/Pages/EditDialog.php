<?php

namespace App\Filament\Resources\DialogResource\Pages;

use App\Filament\Resources\DialogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDialog extends EditRecord
{
    protected static string $resource = DialogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
