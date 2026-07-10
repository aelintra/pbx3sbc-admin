<?php

namespace App\Filament\Resources\DrGatewayResource\Pages;

use App\Filament\Resources\DrGatewayResource;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrGateways extends ListRecords
{
    protected static string $resource = DrGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('reload')
                ->label('Reload drouting')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    app(OpenSIPSMIService::class)->drReload();
                    \Filament\Notifications\Notification::make()
                        ->title('drouting reloaded')
                        ->success()
                        ->send();
                }),
        ];
    }
}
