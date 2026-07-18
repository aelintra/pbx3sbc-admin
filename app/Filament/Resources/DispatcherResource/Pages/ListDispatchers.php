<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;
use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Models\Domain;
use Filament\Actions;
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
        return 'Call Routes';
    }

    public function getHeading(): string
    {
        $setid = $this->tableFilters['setid']['value']
            ?? request()->query('tableFilters.setid.value')
            ?? null;

        if ($setid !== null) {
            $domain = Domain::where('setid', $setid)->first();
            if ($domain) {
                return $domain->domain . ' Destinations';
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
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
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
