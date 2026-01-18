<?php

namespace App\Filament\Resources\DialogResource\Pages;

use App\Filament\Resources\DialogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDialog extends ViewRecord
{
    protected static string $resource = DialogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Dialog records are read-only (managed by OpenSIPS)
        ];
    }
}
