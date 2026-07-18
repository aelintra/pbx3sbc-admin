<?php

namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDomain extends EditRecord
{
    use HasPanelBackLink;

    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
