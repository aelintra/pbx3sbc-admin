<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Services\FleetDomainOwnership;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDispatcher extends CreateRecord
{
    use HasPanelBackLink;

    protected static string $resource = DispatcherResource::class;
    
    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        parent::mount();

        $setid = $this->getSetidFromFilter();
        if ($setid !== null && FleetDomainOwnership::setidIsFleetLocked($setid)) {
            Notification::make()
                ->title('Fleet owns destinations for this set')
                ->body('Change backends via Fleet Instances / node provision. Magrathea cannot add destinations here.')
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(CallRouteResource::getUrl('index'));
        }
    }

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

        $setid = (int) ($data['setid'] ?? 0);
        if ($setid >= 1 && FleetDomainOwnership::setidIsFleetLocked($setid)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'destination' => 'Fleet owns destinations for this set — change backends via Fleet Instances / node provision.',
            ]);
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
