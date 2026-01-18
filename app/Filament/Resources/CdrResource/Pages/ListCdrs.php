<?php

namespace App\Filament\Resources\CdrResource\Pages;

use App\Filament\Resources\CdrResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCdrs extends ListRecords
{
    protected static string $resource = CdrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CDR records are created by OpenSIPS, not manually
        ];
    }
}
