<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedRegistrationResource\Pages;
use App\Models\FailedRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class FailedRegistrationResource extends Resource
{
    protected static ?string $model = FailedRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Failed Registrations';

    protected static ?string $modelLabel = 'Failed Registration';

    protected static ?string $pluralModelLabel = 'Failed Registrations';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        // Failed registrations are read-only (created by OpenSIPS)
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->disabled(),
                Forms\Components\TextInput::make('domain')
                    ->label('Domain')
                    ->disabled(),
                Forms\Components\TextInput::make('source_ip')
                    ->label('Source IP')
                    ->disabled(),
                Forms\Components\TextInput::make('source_port')
                    ->label('Source Port')
                    ->disabled(),
                Forms\Components\TextInput::make('user_agent')
                    ->label('User Agent')
                    ->disabled(),
                Forms\Components\TextInput::make('response_code')
                    ->label('Response Code')
                    ->disabled(),
                Forms\Components\TextInput::make('response_reason')
                    ->label('Response Reason')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('attempt_time')
                    ->label('Attempt Time')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source_ip')
                    ->label('Source IP')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('response_code')
                    ->label('Response Code')
                    ->badge()
                    ->color(fn ($record) => $record->response_code_badge)
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->response_reason ?? ''))
                    ->sortable(),
                TextColumn::make('attempt_time')
                    ->label('Attempt Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('attempt_time')
                    ->form([
                        Forms\Components\DatePicker::make('attempt_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('attempt_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['attempt_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('attempt_time', '>=', $date),
                            )
                            ->when(
                                $data['attempt_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('attempt_time', '<=', $date),
                            );
                    }),
                Filter::make('username')
                    ->form([
                        Forms\Components\TextInput::make('username')
                            ->label('Username (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['username'],
                            fn (Builder $query, $username): Builder => $query->byUsername($username)
                        );
                    }),
                Filter::make('domain')
                    ->form([
                        Forms\Components\TextInput::make('domain')
                            ->label('Domain (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['domain'],
                            fn (Builder $query, $domain): Builder => $query->byDomain($domain)
                        );
                    }),
                Filter::make('source_ip')
                    ->form([
                        Forms\Components\TextInput::make('source_ip')
                            ->label('Source IP (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['source_ip'],
                            fn (Builder $query, $ip): Builder => $query->bySourceIp($ip)
                        );
                    }),
                SelectFilter::make('response_code')
                    ->label('Response Code')
                    ->options([
                        403 => '403 Forbidden',
                        404 => '404 Not Found',
                        408 => '408 Request Timeout',
                        500 => '500 Server Error',
                        503 => '503 Service Unavailable',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $code): Builder => $query->byResponseCode($code)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Failed registrations are immutable (created by OpenSIPS) - no bulk actions allowed
            ])
            ->defaultSort('attempt_time', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('30s'); // Auto-refresh every 30 seconds
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
            'index' => Pages\ListFailedRegistrations::route('/'),
            'view' => Pages\ViewFailedRegistration::route('/{record}'),
        ];
    }
}
