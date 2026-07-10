<?php

namespace App\Filament\Resources\DrRuleResource\Pages;

use App\Filament\Resources\DrRuleResource;
use App\Models\DrRule;
use App\Services\OpenSIPSMIService;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDrRules extends ListRecords
{
    protected static string $resource = DrRuleResource::class;

    public function getTabs(): array
    {
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
                    app(OpenSIPSMIService::class)->drReload();
                    \Filament\Notifications\Notification::make()
                        ->title('drouting reloaded')
                        ->success()
                        ->send();
                }),
        ];
    }
}
