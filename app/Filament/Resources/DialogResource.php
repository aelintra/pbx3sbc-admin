<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DialogResource\Pages;
use App\Models\Dialog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;

class DialogResource extends Resource
{
    protected static ?string $model = Dialog::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';

    protected static ?string $navigationLabel = 'Active Calls';

    protected static ?string $modelLabel = 'Active Call';

    protected static ?string $pluralModelLabel = 'Active Calls';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        // Dialog records are read-only (managed by OpenSIPS)
        return $form
            ->schema([
                Forms\Components\TextInput::make('callid')
                    ->label('Call-ID')
                    ->disabled(),
                Forms\Components\TextInput::make('from_uri')
                    ->label('From URI')
                    ->disabled(),
                Forms\Components\TextInput::make('to_uri')
                    ->label('To URI')
                    ->disabled(),
                Forms\Components\TextInput::make('state')
                    ->label('State')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('Start Time')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dlg_id')
                    ->label('Dialog ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('callid')
                    ->label('Call-ID')
                    ->searchable()
                    ->copyable()
                    ->limit(20),
                TextColumn::make('from_uri')
                    ->label('From URI')
                    ->searchable()
                    ->copyable()
                    ->limit(30),
                TextColumn::make('to_uri')
                    ->label('To URI')
                    ->searchable()
                    ->copyable()
                    ->limit(30),
                TextColumn::make('state_label')
                    ->label('State')
                    ->badge()
                    ->color(fn ($record) => $record->state_badge)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('state', $direction);
                    }),
                TextColumn::make('formatted_live_duration')
                    ->label('Duration')
                    ->badge()
                    ->color('info')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('start_time', $direction);
                    }),
                TextColumn::make('start_time')
                    ->label('Start Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('modified')
                    ->label('Last Modified')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Call State')
                    ->options([
                        1 => 'Unconfirmed',
                        2 => 'Early',
                        3 => 'Confirmed',
                        4 => 'Established',
                        5 => 'Ended',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $state): Builder => $query->where('state', $state)
                        );
                    }),
                Filter::make('active_only')
                    ->label('Active Calls Only')
                    ->query(fn (Builder $query): Builder => $query->active())
                    ->toggle(),
                Filter::make('from_uri')
                    ->form([
                        Forms\Components\TextInput::make('from_uri')
                            ->label('From URI (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['from_uri'],
                            fn (Builder $query, $uri): Builder => $query->where('from_uri', 'like', "%{$uri}%")
                        );
                    }),
                Filter::make('to_uri')
                    ->form([
                        Forms\Components\TextInput::make('to_uri')
                            ->label('To URI (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['to_uri'],
                            fn (Builder $query, $uri): Builder => $query->where('to_uri', 'like', "%{$uri}%")
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for active calls
            ])
            ->defaultSort('start_time', 'desc')
            ->paginated([10, 25, 50, 100]) // Reasonable pagination options - NO "ALL" option for performance
            ->defaultPaginationPageOption(25) // Default to 25 records per page
            ->poll('5s') // Auto-refresh every 5 seconds for real-time updates
            ->emptyStateHeading('No active calls')
            ->emptyStateDescription('There are currently no active calls.');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDialogs::route('/'),
            'view' => Pages\ViewDialog::route('/{record}'),
        ];
    }
}
