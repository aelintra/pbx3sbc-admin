<?php

namespace App\Filament\Resources\DialogResource\Pages;

use App\Filament\Resources\DialogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDialogs extends ListRecords
{
    protected static string $resource = DialogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Dialog records are created by OpenSIPS, not manually
        ];
    }
}
