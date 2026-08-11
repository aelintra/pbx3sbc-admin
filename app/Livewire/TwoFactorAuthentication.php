<?php

namespace App\Livewire;

use App\Support\TotpCode;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Jeffgreco13\FilamentBreezy\Livewire\TwoFactorAuthentication as BreezyTwoFactorAuthentication;

/**
 * Profile enroll confirm — placeholder without hyphens; accept spaced/hyphenated OTP.
 */
class TwoFactorAuthentication extends BreezyTwoFactorAuthentication
{
    public function confirmAction(): Action
    {
        return Action::make('confirm')
            ->color('success')
            ->label(__('filament-breezy::default.profile.2fa.actions.confirm_finish'))
            ->modalWidth('sm')
            ->form([
                Forms\Components\TextInput::make('code')
                    ->label(__('filament-breezy::default.fields.2fa_code'))
                    ->placeholder(__('filament-breezy::default.two_factor.code_placeholder'))
                    ->required()
                    ->extraInputAttributes(['inputmode' => 'numeric', 'autocomplete' => 'one-time-code'])
                    ->dehydrateStateUsing(fn (?string $state): string => TotpCode::digitsOnly($state)),
            ])
            ->action(function ($data, $action, $livewire) {
                $code = TotpCode::digitsOnly($data['code'] ?? null);
                if ($code === '' || ! filament('filament-breezy')->verify(code: $code)) {
                    $livewire->addError('mountedActionsData.0.code', __('filament-breezy::default.profile.2fa.confirmation.invalid_code'));
                    $action->halt();
                }
                $this->user->confirmTwoFactorAuthentication();
                $this->user->setTwoFactorSession();
                Notification::make()
                    ->success()
                    ->title(__('filament-breezy::default.profile.2fa.confirmation.success_notification'))
                    ->send();
            });
    }
}
