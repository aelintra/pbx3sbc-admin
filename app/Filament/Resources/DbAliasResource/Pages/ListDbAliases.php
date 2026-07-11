<?php

namespace App\Filament\Resources\DbAliasResource\Pages;

use App\Filament\Resources\DbAliasResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDbAliases extends ListRecords
{
    protected static string $resource = DbAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
