<?php

namespace App\Filament\Resources\FailedRegistrationResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\FailedRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFailedRegistration extends ViewRecord
{
    use HasPanelBackLink;

    protected static string $resource = FailedRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Failed registrations are read-only (created by OpenSIPS) - no actions allowed
        ];
    }
}
