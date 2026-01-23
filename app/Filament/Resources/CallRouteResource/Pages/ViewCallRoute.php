<?php

namespace App\Filament\Resources\CallRouteResource\Pages;

use App\Filament\Resources\CallRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCallRoute extends ViewRecord
{
    protected static string $resource = CallRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
