<?php

namespace App\Filament\Resources\DispatcherResource\Pages;

use App\Filament\Resources\DispatcherResource;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDispatcher extends EditRecord
{
    protected static string $resource = DispatcherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    try {
                        $miService = app(OpenSIPSMIService::class);
                        $miService->dispatcherReload();
                    } catch (\Exception $e) {
                        \Log::warning('OpenSIPS MI reload failed after destination deletion', ['error' => $e->getMessage()]);
                    }
                })
                ->successRedirectUrl(function () {
                    // Preserve setid filter after deletion from edit page
                    $setid = $this->record->setid ?? null;
                    
                    // Fall back to URL query parameter
                    if ($setid === null) {
                        $setid = request()->query('tableFilters.setid.value') 
                            ?? (request()->query('tableFilters')['setid']['value'] ?? null);
                    }
                    
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
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Reload OpenSIPS modules after update
        try {
            $miService = app(OpenSIPSMIService::class);
            $miService->dispatcherReload();
        } catch (\Exception $e) {
            \Log::warning('OpenSIPS MI reload failed after destination update', ['error' => $e->getMessage()]);
        }
    }
}
