<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\DispatcherResource;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDispatcher extends CreateRecord
{
    use HasPanelBackLink;

    protected static string $resource = DispatcherResource::class;
    
    protected static bool $canCreateAnother = false;

    protected function getPanelBackUrl(): string
    {
        $setid = $this->getSetidFromFilter();

        if ($setid !== null) {
            return DispatcherResource::getUrl('index', [
                'tableFilters' => [
                    'setid' => [
                        'value' => $setid,
                    ],
                ],
            ]);
        }

        return DispatcherResource::getUrl('index');
    }

    protected function getPanelBackLabel(): string
    {
        return 'Destinations';
    }

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
        if (! app(OpenSIPSMIService::class)->dispatcherReload()) {
            Notification::make()
                ->title('Destination created — reload failed')
                ->body('The destination was created, but OpenSIPS dispatcher reload failed. Routing may be stale until reloaded.')
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        // Redirect back to filtered list - get setid from created record or filter
        $setid = $this->record->setid ?? $this->getSetidFromFilter();
        
        if ($setid !== null) {
            return DispatcherResource::getUrl('index', [
                'tableFilters' => [
                    'setid' => [
                        'value' => $setid,
                    ],
                ],
            ]);
        }
        
        return DispatcherResource::getUrl('index');
    }
}
