<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'Location';

    protected static ?string $pluralModelLabel = 'Locations';

    protected static ?string $navigationGroup = 'Routing';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        // Locations are read-only (created by OpenSIPS)
        return $form
            ->schema([
                Forms\Components\TextInput::make('contact_id')
                    ->label('Contact ID')
                    ->disabled(),
                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->disabled(),
                Forms\Components\TextInput::make('domain')
                    ->label('Domain')
                    ->disabled(),
                Forms\Components\Textarea::make('contact')
                    ->label('Contact')
                    ->disabled(),
                Forms\Components\TextInput::make('received')
                    ->label('Received')
                    ->disabled(),
                Forms\Components\TextInput::make('path')
                    ->label('Path')
                    ->disabled(),
                Forms\Components\TextInput::make('expires')
                    ->label('Expires')
                    ->disabled(),
                Forms\Components\TextInput::make('q')
                    ->label('Q Value')
                    ->disabled(),
                Forms\Components\TextInput::make('callid')
                    ->label('Call-ID')
                    ->disabled(),
                Forms\Components\TextInput::make('cseq')
                    ->label('CSeq')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('last_modified')
                    ->label('Last Modified')
                    ->disabled(),
                Forms\Components\TextInput::make('flags')
                    ->label('Flags')
                    ->disabled(),
                Forms\Components\TextInput::make('cflags')
                    ->label('CFlags')
                    ->disabled(),
                Forms\Components\TextInput::make('user_agent')
                    ->label('User Agent')
                    ->disabled(),
                Forms\Components\TextInput::make('socket')
                    ->label('Socket')
                    ->disabled(),
                Forms\Components\TextInput::make('methods')
                    ->label('Methods')
                    ->disabled(),
                Forms\Components\TextInput::make('sip_instance')
                    ->label('SIP Instance')
                    ->disabled(),
                Forms\Components\Textarea::make('kv_store')
                    ->label('KV Store')
                    ->disabled(),
                Forms\Components\TextInput::make('attr')
                    ->label('Attributes')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->label('Domain')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                TextColumn::make('contact')
                    ->label('Contact')
                    ->searchable()
                    ->copyable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->contact),
                TextColumn::make('expires')
                    ->label('Expires')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? date('Y-m-d H:i:s', $state) : 'N/A')
                    ->badge()
                    ->color(fn ($record) => $record->expires > time() ? 'success' : 'danger'),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->user_agent),
            ])
            ->filters([
                Filter::make('username')
                    ->form([
                        Forms\Components\TextInput::make('username')
                            ->label('Username (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['username'],
                            fn (Builder $query, $username): Builder => $query->where('username', 'like', "%{$username}%")
                        );
                    }),
                Filter::make('contact')
                    ->form([
                        Forms\Components\TextInput::make('contact')
                            ->label('Contact (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['contact'],
                            fn (Builder $query, $contact): Builder => $query->where('contact', 'like', "%{$contact}%")
                        );
                    }),
                Filter::make('expires')
                    ->form([
                        Forms\Components\DatePicker::make('expires_from')
                            ->label('Expires From'),
                        Forms\Components\DatePicker::make('expires_until')
                            ->label('Expires Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expires_from'],
                                fn (Builder $query, $date): Builder => $query->where('expires', '>=', is_string($date) ? strtotime($date) : $date->timestamp)
                            )
                            ->when(
                                $data['expires_until'],
                                fn (Builder $query, $date): Builder => $query->where('expires', '<=', is_string($date) ? strtotime($date . ' 23:59:59') : $date->endOfDay()->timestamp)
                            );
                    }),
                Filter::make('active')
                    ->label('Active Registrations')
                    ->query(fn (Builder $query): Builder => $query->where('expires', '>', time()))
                    ->toggle(),
                Filter::make('expired')
                    ->label('Expired Registrations')
                    ->query(fn (Builder $query): Builder => $query->where('expires', '<=', time()))
                    ->toggle(),
                SelectFilter::make('domain')
                    ->label('Domain')
                    ->options(function () {
                        return Location::query()
                            ->whereNotNull('domain')
                            ->distinct()
                            ->pluck('domain', 'domain')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $domain): Builder => $query->where('domain', $domain)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Locations are immutable (created by OpenSIPS) - no bulk actions allowed
            ])
            ->defaultSort('expires', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('30s'); // Auto-refresh every 30 seconds for new locations
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
            'index' => Pages\ListLocations::route('/'),
            'view' => Pages\ViewLocation::route('/{record}'),
        ];
    }
}
