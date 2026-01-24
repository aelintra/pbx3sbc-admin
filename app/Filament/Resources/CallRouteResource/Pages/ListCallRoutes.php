<?php

namespace App\Filament\Resources\CallRouteResource\Pages;

use App\Filament\Resources\CallRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCallRoutes extends ListRecords
{
    protected static string $resource = CallRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->disableCreateAnother(),
        ];
    }
}
