<?php

namespace App\Filament\Resources\CallRouteResource\Pages;

use App\Filament\Resources\CallRouteResource;
use App\Models\Dispatcher;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCallRoute extends EditRecord
{
    protected static string $resource = CallRouteResource::class;

    public function getHeading(): string
    {
        return 'Edit Call Route: ' . $this->record->domain;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function () {
                    // Delete associated dispatchers before deleting domain
                    $domain = $this->record;
                    $domain->dispatchers()->delete();
                })
                ->after(function () {
                    // Reload OpenSIPS modules after deletion
                    try {
                        $miService = app(OpenSIPSMIService::class);
                        $miService->domainReload();
                        $miService->dispatcherReload();
                    } catch (\Exception $e) {
                        \Log::warning('OpenSIPS MI reload failed after route deletion', ['error' => $e->getMessage()]);
                        Notification::make()
                            ->warning()
                            ->title('OpenSIPS Module Reload Failed')
                            ->body('The call route was deleted, but OpenSIPS modules could not be reloaded. You may need to reload them manually.')
                            ->send();
                    }
                })
                ->successRedirectUrl(CallRouteResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Set domain_select to the domain name (for the dropdown)
        $domain = $this->record;
        $data['domain_select'] = $domain->domain;
        
        // Load the first dispatcher's data into the form fields
        // (Since Create form only allows one destination, Edit should show the first one)
        $firstDispatcher = $domain->dispatchers->first();
        if ($firstDispatcher) {
            $data['destination'] = $firstDispatcher->destination;
            $data['weight'] = $firstDispatcher->weight;
            $data['priority'] = $firstDispatcher->priority;
            $data['state'] = $firstDispatcher->state;
            $data['description'] = $firstDispatcher->description ?? '';
            $data['probe_mode'] = $firstDispatcher->probe_mode ?? 0;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Update last_modified
        $data['last_modified'] = now();

        return $data;
    }

    protected function afterSave(): void
    {
        $domain = $this->record;
        $formData = $this->form->getState();
        
        // Handle single destination (not repeater)
        // Note: Domain was already saved by Filament's save process (between mutateFormDataBeforeSave and afterSave)
        // We wrap dispatcher operations in a transaction for consistency
        // If dispatcher update fails, it will roll back, but domain save already happened (Filament limitation)
        if (!empty($formData['destination'])) {
            DB::transaction(function () use ($domain, $formData) {
                // Check if this destination already exists
                $existingDispatcher = $domain->dispatchers()
                    ->where('destination', $formData['destination'])
                    ->first();
                
                if ($existingDispatcher) {
                    // Update existing dispatcher
                    $existingDispatcher->update([
                        'weight' => $formData['weight'] ?? '1',
                        'priority' => $formData['priority'] ?? 0,
                        'state' => $formData['state'] ?? 0,
                        'description' => $formData['description'] ?? '',
                        'probe_mode' => $formData['probe_mode'] ?? 0,
                    ]);
                } else {
                    // Create new dispatcher
                    $domain->dispatchers()->create([
                        'destination' => $formData['destination'],
                        'weight' => $formData['weight'] ?? '1',
                        'priority' => $formData['priority'] ?? 0,
                        'state' => $formData['state'] ?? 0,
                        'description' => $formData['description'] ?? '',
                        'probe_mode' => $formData['probe_mode'] ?? 0,
                    ]);
                }
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
            \Log::warning('OpenSIPS MI reload failed after route update', ['error' => $e->getMessage()]);
            $miReloadSuccess = false;
        }

        Notification::make()
            ->title('Call route updated successfully')
            ->success()
            ->send();

        if (!$miReloadSuccess) {
            Notification::make()
                ->warning()
                ->title('OpenSIPS Module Reload Failed')
                ->body('The call route was updated, but OpenSIPS modules could not be reloaded. You may need to reload them manually.')
                ->send();
        }
    }
}
