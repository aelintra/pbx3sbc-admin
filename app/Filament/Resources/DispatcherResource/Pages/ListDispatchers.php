<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDispatchers extends ListRecords
{
    protected static string $resource = DispatcherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_routes')
                ->label('Back to Call Routes')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CallRouteResource::getUrl('index'))
                ->outlined(),
            Actions\CreateAction::make()
                ->url(function () {
                    // Preserve setid filter when creating
                    $filters = request()->get('tableFilters', []);
                    $setidFilter = $filters['setid']['value'] ?? request()->query('tableFilters.setid.value') ?? null;
                    if ($setidFilter !== null) {
                        return DispatcherResource::getUrl('create', [
                            'tableFilters' => [
                                'setid' => [
                                    'value' => $setidFilter,
                                ],
                            ],
                        ]);
                    }
                    return DispatcherResource::getUrl('create');
                }),
        ];
    }
}
