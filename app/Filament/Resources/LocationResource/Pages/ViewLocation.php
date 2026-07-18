<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLocation extends ViewRecord
{
    use HasPanelBackLink;

    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Locations are read-only (created by OpenSIPS)
        ];
    }
}
