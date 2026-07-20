<?php

namespace App\Filament\Resources\CallRouteResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCallRoute extends ViewRecord
{
    use HasPanelBackLink;

    protected static string $resource = CallRouteResource::class;

    public function getHeading(): string
    {
        return 'View Call Route: ' . $this->record->domain;
    }

    protected function getHeaderActions(): array
    {
        // Read-only view: mutate via list (edit domain / delete) or Manage destinations.
        return [
            Actions\Action::make('manage_destinations')
                ->label('Manage destinations')
                ->icon('lucide-server')
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
