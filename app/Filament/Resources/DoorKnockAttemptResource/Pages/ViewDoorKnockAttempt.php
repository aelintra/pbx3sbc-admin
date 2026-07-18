<?php

namespace App\Filament\Resources\DoorKnockAttemptResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DoorKnockAttemptResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDoorKnockAttempt extends ViewRecord
{
    use HasPanelBackLink;

    protected static string $resource = DoorKnockAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Door-knock attempts are read-only (created by OpenSIPS) - no actions allowed
        ];
    }
}
