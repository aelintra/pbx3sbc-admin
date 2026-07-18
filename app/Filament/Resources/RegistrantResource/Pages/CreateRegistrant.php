<?php

namespace App\Filament\Resources\RegistrantResource\Pages;

use App\Filament\Concerns\HasPanelBackLink;

use App\Filament\Resources\RegistrantResource;
use App\Services\OpenSIPSMIService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrant extends CreateRecord
{
    use HasPanelBackLink;

    protected static string $resource = RegistrantResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('sip_auth_secret', $data)) {
            $data['password'] = $data['sip_auth_secret'];
            unset($data['sip_auth_secret']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(OpenSIPSMIService::class)->regReload();
        Notification::make()
            ->title('Registration created')
            ->body('uac_registrant reloaded (reg_reload).')
            ->success()
            ->send();
    }
}
