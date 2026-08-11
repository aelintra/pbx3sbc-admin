<?php

namespace App\Providers;

use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Livewire\TwoFactorAuthentication;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SPA kinship: table row actions are icon-only + tooltip (no "Edit"/"Delete" text).
        // Scoped to Filament\Tables\Actions — page header actions keep their labels.
        EditAction::configureUsing(function (EditAction $action): void {
            $action
                ->iconButton()
                ->icon('lucide-pencil')
                ->tooltip('Edit');
        });

        DeleteAction::configureUsing(function (DeleteAction $action): void {
            $action
                ->iconButton()
                ->icon('lucide-trash-2')
                ->tooltip('Delete');
        });

        ViewAction::configureUsing(function (ViewAction $action): void {
            $action
                ->iconButton()
                ->icon('lucide-eye')
                ->tooltip('View');
        });

        // Breezy boot registers vendor Livewire name — re-bind so profile enroll uses our strip/placeholder.
        Livewire::component('two_factor_authentication', TwoFactorAuthentication::class);
    }
}
