<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Resources\DispatcherResource;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDispatcher extends CreateRecord
{
    protected static string $resource = DispatcherResource::class;

    protected function getSetidFromFilter(): ?int
    {
        // Try multiple ways to get the setid filter from URL
        $filters = request()->get('tableFilters', []);
        if (isset($filters['setid']['value'])) {
            return (int) $filters['setid']['value'];
        }
        
        // Also check query string directly
        $setid = request()->query('tableFilters.setid.value') ?? request()->query('setid');
        if ($setid !== null) {
            return (int) $setid;
        }
        
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-fill setid from URL filter if present
        $setidFilter = $this->getSetidFromFilter();
        if ($setidFilter !== null) {
            $data['setid'] = $setidFilter;
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Pre-fill setid from URL filter if present
        $setidFilter = $this->getSetidFromFilter();
        if ($setidFilter !== null) {
            $data['setid'] = $setidFilter;
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Reload OpenSIPS modules after creation
        try {
            $miService = app(OpenSIPSMIService::class);
            $miService->dispatcherReload();
        } catch (\Exception $e) {
            \Log::warning('OpenSIPS MI reload failed after destination creation', ['error' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        // Redirect back to filtered list if setid filter was present
        $setidFilter = $this->getSetidFromFilter();
        if ($setidFilter !== null) {
            return DispatcherResource::getUrl('index', [
                'tableFilters' => [
                    'setid' => [
                        'value' => $setidFilter,
                    ],
                ],
            ]);
        }
        
        return parent::getRedirectUrl();
    }
}
