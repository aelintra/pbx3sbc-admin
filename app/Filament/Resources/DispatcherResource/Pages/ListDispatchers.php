<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;
use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Models\Domain;
use App\Services\FleetDomainOwnership;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDispatchers extends ListRecords
{
    use HasPanelBackLink;

    protected static string $resource = DispatcherResource::class;

    protected function getPanelBackUrl(): string
    {
        return CallRouteResource::getUrl('index');
    }

    protected function getPanelBackLabel(): string
    {
        return 'Domain Routes';
    }

    public function getHeading(): string
    {
        $setid = $this->tableFilters['setid']['value']
            ?? request()->query('tableFilters.setid.value')
            ?? null;

        if ($setid !== null) {
            $domain = Domain::where('setid', $setid)->first();
            if ($domain) {
                $suffix = FleetDomainOwnership::setidIsFleetLocked((int) $setid)
                    ? ' Destinations (Fleet-locked)'
                    : ' Destinations';

                return $domain->domain.$suffix;
            }
        }

        return 'Destinations';
    }

    public function updatedTableFilters(): void
    {
        $setid = $this->tableFilters['setid']['value'] ?? null;
        if ($setid !== null) {
            $domainExists = Domain::where('setid', (int) $setid)->exists();
            if (! $domainExists) {
                $this->redirect(CallRouteResource::getUrl('index'));
            }
        }
    }

    public function mount(): void
    {
        parent::mount();

        $setidFilter = request()->query('tableFilters.setid.value')
            ?? request()->query('tableFilters')['setid']['value']
            ?? null;

        if ($setidFilter !== null) {
            $domain = Domain::where('setid', (int) $setidFilter)->first();
            if (! $domain) {
                $this->redirect(CallRouteResource::getUrl('index'));

                return;
            }

            $this->tableFilters['setid']['value'] = (int) $setidFilter;

            if (FleetDomainOwnership::setidIsFleetLocked((int) $setidFilter)) {
                Notification::make()
                    ->title('Fleet owns destinations for this set')
                    ->body('Read-only here. Change backends via Fleet Instances / node provision.')
                    ->warning()
                    ->send();
            }
        }
    }

    protected function getHeaderActions(): array
    {
        $setid = $this->tableFilters['setid']['value']
            ?? request()->query('tableFilters.setid.value')
            ?? request()->query('tableFilters')['setid']['value']
            ?? null;
        $fleetLocked = $setid !== null && FleetDomainOwnership::setidIsFleetLocked((int) $setid);

        return [
            Actions\CreateAction::make()
                ->visible(! $fleetLocked)
                ->url(function () {
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
