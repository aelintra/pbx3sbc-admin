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
