<?php

namespace App\Filament\Resources\RegistrantResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\RegistrantResource;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRegistrant extends EditRecord
{
    use HasPanelBackLink;

    protected static string $resource = RegistrantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Do not pre-fill secret; blank on save keeps existing DB password.
        unset($data['password']);
        $data['sip_auth_secret'] = null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $secret = $data['sip_auth_secret'] ?? null;
        unset($data['sip_auth_secret'], $data['password']);

        if (filled($secret)) {
            $data['password'] = $secret;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function () {
                    if (! app(OpenSIPSMIService::class)->regReload()) {
                        Notification::make()
                            ->title('Registration deleted — reload failed')
                            ->body('The registration was deleted, but OpenSIPS uac_registrant reload (reg_reload) failed.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        if (app(OpenSIPSMIService::class)->regReload()) {
            Notification::make()
                ->title('Registration saved')
                ->body('uac_registrant reloaded (reg_reload).')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Registration saved — reload failed')
                ->body('The registration was saved, but OpenSIPS uac_registrant reload (reg_reload) failed.')
                ->warning()
                ->send();
        }
    }
}
