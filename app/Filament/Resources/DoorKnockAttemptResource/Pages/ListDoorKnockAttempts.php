<?php

namespace App\Filament\Resources\DoorKnockAttemptResource\Pages;

use App\Filament\Resources\DoorKnockAttemptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDoorKnockAttempts extends ListRecords
{
    protected static string $resource = DoorKnockAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Door-knock attempts are created by OpenSIPS, not manually
        ];
    }
}
