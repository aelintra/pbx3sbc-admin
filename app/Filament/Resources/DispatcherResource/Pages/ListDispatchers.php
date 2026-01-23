<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Models\Domain;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDispatchers extends ListRecords
{
    protected static string $resource = DispatcherResource::class;

    public function getHeading(): string
    {
        // Get setid from filter
        $setid = $this->tableFilters['setid']['value'] 
            ?? request()->query('tableFilters.setid.value') 
            ?? null;
        
        if ($setid !== null) {
            // Look up the domain for this setid
            $domain = Domain::where('setid', $setid)->first();
            if ($domain) {
                return $domain->domain . ' Destinations';
            }
        }
        
        return 'Destinations';
    }
    
    public function updatedTableFilters(): void
    {
        // Check if setid filter is set and domain exists
        $setid = $this->tableFilters['setid']['value'] ?? null;
        if ($setid !== null) {
            $domainExists = Domain::where('setid', (int) $setid)->exists();
            if (!$domainExists) {
                // Domain was deleted, redirect to Call Routes list
                $this->redirect(CallRouteResource::getUrl('index'));
            }
        }
    }

    public function mount(): void
    {
        parent::mount();
        
        // Apply setid filter from URL if present
        $setidFilter = request()->query('tableFilters.setid.value') 
            ?? request()->query('tableFilters')['setid']['value'] 
            ?? null;
            
        if ($setidFilter !== null) {
            // Verify the domain still exists (might have been deleted)
            $domain = Domain::where('setid', (int) $setidFilter)->first();
            if (!$domain) {
                // Domain was deleted, redirect to Call Routes list
                $this->redirect(CallRouteResource::getUrl('index'));
                return;
            }
            
            $this->tableFilters['setid']['value'] = (int) $setidFilter;
        }
    }

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
