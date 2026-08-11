<?php

namespace App\Filament\Resources\CallRouteResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Services\FleetDomainOwnership;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCallRoute extends ViewRecord
{
    use HasPanelBackLink;

    protected static string $resource = CallRouteResource::class;

    public function getHeading(): string
    {
        return 'View Domain Route: ' . $this->record->domain;
    }

    protected function getHeaderActions(): array
    {
        // Read-only view: mutate via list (edit domain / delete) or Manage destinations (standalone only).
        return [
            Actions\Action::make('manage_destinations')
                ->label('Manage destinations')
                ->icon('lucide-server')
                ->visible(fn (): bool => ! FleetDomainOwnership::isFleetOwned($this->record->attrs))
                ->url(fn () => DispatcherResource::getUrl('index', [
                    'tableFilters' => [
                        'setid' => [
                            'value' => $this->record->setid,
                        ],
                    ],
                ])),
        ];
    }
}
