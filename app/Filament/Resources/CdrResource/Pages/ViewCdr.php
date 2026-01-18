<?php

namespace App\Filament\Resources\CdrResource\Pages;

use App\Filament\Resources\CdrResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCdr extends ViewRecord
{
    protected static string $resource = CdrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CDR records are read-only (created by OpenSIPS) - no actions allowed
        ];
    }
}
