<?php

namespace App\Filament\Resources\CallRouteResource\Pages;

use App\Filament\Resources\CallRouteResource;
use App\Filament\Resources\DispatcherResource;
use App\Models\Domain;
use App\Models\Dispatcher;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateCallRoute extends CreateRecord
{
    protected static string $resource = CallRouteResource::class;

    protected static bool $canCreateAnother = false;

    protected bool $usingExistingDomain = false;
    protected ?int $existingDomainId = null;
    protected ?int $domainSetid = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle domain selection based on domain_type
        $domainType = $data['domain_type'] ?? 'existing';
        
        if ($domainType === 'existing' && isset($data['domain_select']) && $data['domain_select'] !== '__new__') {
            // Existing domain selected - use it
            $existingDomain = Domain::where('domain', $data['domain_select'])->first();
            if ($existingDomain) {
                // Store flag to indicate we're using existing domain
                $this->usingExistingDomain = true;
                $this->existingDomainId = $existingDomain->id;
                // Set domain name for reference (won't be used to create)
                $data['domain'] = $existingDomain->domain;
                $data['setid'] = $existingDomain->setid;
            }
        } else {
            // New domain - use the domain field value
            // Auto-generate unique setid for new domain with transaction and lock to prevent race conditions
            if (!isset($data['setid']) || $data['setid'] === null || $data['setid'] === 0) {
                $data['setid'] = DB::transaction(function () {
                    // Lock the table to prevent concurrent setid generation
                    $maxSetid = Domain::lockForUpdate()->max('setid') ?? 0;
                    return $maxSetid + 1;
                });
            }
            $this->usingExistingDomain = false;
        }

        // Set default values
        $data['accept_subdomain'] = $data['accept_subdomain'] ?? 0;
        $data['last_modified'] = now();

        // Remove form-only fields from data (they're not database fields)
        unset($data['domain_select'], $data['domain_type']);

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // If using existing domain, don't create a new one
        if ($this->usingExistingDomain && $this->existingDomainId) {
            // Return the existing domain as if we created it
            return Domain::find($this->existingDomainId);
        }

        // Otherwise, create new domain as normal
        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        // If using existing domain, get it instead of the newly created record
        if ($this->usingExistingDomain && $this->existingDomainId) {
            $domain = Domain::find($this->existingDomainId);
        } else {
            $domain = $this->record;
        }

        // Store setid for redirect
        $this->domainSetid = $domain->setid;

        $formData = $this->form->getState();
        
        // Handle single destination (not repeater)
        if (!empty($formData['destination'])) {
            DB::transaction(function () use ($domain, $formData) {
                $domain->dispatchers()->create([
                    'destination' => $formData['destination'],
                    'weight' => $formData['weight'] ?? '1',
                    'priority' => $formData['priority'] ?? 0,
                    'state' => $formData['state'] ?? 0,
                    'description' => $formData['description'] ?? '',
                    'probe_mode' => $formData['probe_mode'] ?? 0,
                ]);
            });
        }

        // Reload OpenSIPS modules
        $miReloadSuccess = true;
        try {
            $miService = app(OpenSIPSMIService::class);
            $miService->domainReload();
            $miService->dispatcherReload();
        } catch (\Exception $e) {
            // Log but don't fail the operation
            \Log::warning('OpenSIPS MI reload failed after route creation', ['error' => $e->getMessage()]);
            $miReloadSuccess = false;
        }

        Notification::make()
            ->title('Call route created successfully')
            ->success()
            ->send();

        if (!$miReloadSuccess) {
            Notification::make()
                ->warning()
                ->title('OpenSIPS Module Reload Failed')
                ->body('The call route was created, but OpenSIPS modules could not be reloaded. You may need to reload them manually.')
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to Destinations page filtered by the domain's setid
        if ($this->domainSetid !== null) {
            return DispatcherResource::getUrl('index', [
                'tableFilters' => [
                    'setid' => [
                        'value' => $this->domainSetid,
                    ],
                ],
            ]);
        }
        
        // Fallback to call routes list if setid is not available
        return CallRouteResource::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
