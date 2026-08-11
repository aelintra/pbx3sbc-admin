<?php

namespace App\Filament\Pages\Auth;

use App\Support\TotpCode;
use Filament\Forms;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage as BreezyTwoFactorPage;

/**
 * Login 2FA challenge — no hyphenated placeholder; accept codes with spaces/hyphens.
 */
class TwoFactorPage extends BreezyTwoFactorPage
{
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('code')
                ->label($this->usingRecoveryCode ? __('filament-breezy::default.fields.2fa_recovery_code') : __('filament-breezy::default.fields.2fa_code'))
                ->placeholder($this->usingRecoveryCode ? __('filament-breezy::default.two_factor.recovery_code_placeholder') : __('filament-breezy::default.two_factor.code_placeholder'))
                ->hint(new HtmlString(Blade::render('
                    <x-filament::link href="#" wire:click="toggleRecoveryCode()">'.($this->usingRecoveryCode ? __('filament-breezy::default.cancel') : __('filament-breezy::default.two_factor.recovery_code_link')).'
                    </x-filament::link>')))
                ->required()
                ->extraInputAttributes([
                    'class' => 'text-center',
                    'autocomplete' => $this->usingRecoveryCode ? 'off' : 'one-time-code',
                    'inputmode' => $this->usingRecoveryCode ? 'text' : 'numeric',
                ])
                ->autofocus()
                ->dehydrateStateUsing(function (?string $state): ?string {
                    if ($this->usingRecoveryCode) {
                        return $state === null ? null : trim($state);
                    }

                    $digits = TotpCode::digitsOnly($state);

                    return $digits === '' ? $state : $digits;
                })
                ->suffixAction(
                    Forms\Components\Actions\Action::make('cancel')
                        ->ToolTip(__('filament-breezy::default.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->action(function () {
                            \Filament\Facades\Filament::auth()->logout();
                            $this->mount();
                        })
                ),
        ];
    }

    public function hasValidCode()
    {
        if ($this->usingRecoveryCode) {
            return parent::hasValidCode();
        }

        $digits = TotpCode::digitsOnly($this->code);
        if ($digits === '') {
            return false;
        }

        return filament('filament-breezy')->verify(code: $digits);
    }
}
