<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Resources\DrRuleResource;
use App\Models\DrRule;
use App\Services\OpenSIPSMIService;
use App\Support\FleetMode;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDrRules extends ListRecords
{
    protected static string $resource = DrRuleResource::class;

    public function getSubheading(): ?string
    {
        if (! FleetMode::joined()) {
            return null;
        }

        return 'Outbound only — inbound DID delivery is Fleet → DIDs (Allocate / Project).';
    }

    public function getTabs(): array
    {
        if (FleetMode::joined()) {
            return [
                'outbound' => Tab::make('Outbound')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('groupid', '0'))
                    ->badge(DrRule::where('groupid', '0')->count()),
            ];
        }

        return [
            'all' => Tab::make('All'),
            'outbound' => Tab::make('Outbound')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('groupid', '0'))
                ->badge(DrRule::where('groupid', '0')->count()),
            'inbound' => Tab::make('Inbound')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('groupid', '1'))
                ->badge(DrRule::where('groupid', '1')->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('reload')
                ->label('Reload drouting')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    if (app(OpenSIPSMIService::class)->drReload()) {
                        Notification::make()
                            ->title('drouting reloaded')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('drouting reload failed')
                            ->body('OpenSIPS drouting reload (dr_reload) failed. Check MI connectivity.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
