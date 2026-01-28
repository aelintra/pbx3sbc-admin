<?php

namespace App\Filament\Resources\FailedRegistrationResource\Pages;

use App\Filament\Resources\FailedRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFailedRegistrations extends ListRecords
{
    protected static string $resource = FailedRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Failed registrations are created by OpenSIPS, not manually
        ];
    }
}
