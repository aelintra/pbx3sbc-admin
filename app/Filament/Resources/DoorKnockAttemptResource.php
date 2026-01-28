<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoorKnockAttemptResource\Pages;
use App\Models\DoorKnockAttempt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class DoorKnockAttemptResource extends Resource
{
    protected static ?string $model = DoorKnockAttempt::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationLabel = 'Door-Knock Attempts';

    protected static ?string $modelLabel = 'Door-Knock Attempt';

    protected static ?string $pluralModelLabel = 'Door-Knock Attempts';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        // Door-knock attempts are read-only (created by OpenSIPS)
        return $form
            ->schema([
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
                Forms\Components\TextInput::make('method')
                    ->label('SIP Method')
                    ->disabled(),
                Forms\Components\TextInput::make('request_uri')
                    ->label('Request URI')
                    ->disabled(),
                Forms\Components\TextInput::make('reason')
                    ->label('Reason')
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
                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                TextColumn::make('source_ip')
                    ->label('Source IP')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('method')
                    ->label('Method')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color(fn ($record) => $record->reason_badge)
                    ->formatStateUsing(fn ($record) => $record->reason_label)
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
                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options([
                        'scanner_detected' => 'Scanner Detected',
                        'domain_not_found' => 'Domain Not Found',
                        'query_failed' => 'Query Failed',
                        'domain_mismatch' => 'Domain Mismatch',
                        'method_not_allowed' => 'Method Not Allowed',
                        'max_forwards_exceeded' => 'Max Forwards Exceeded',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $reason): Builder => $query->byReason($reason)
                        );
                    }),
                SelectFilter::make('method')
                    ->label('SIP Method')
                    ->options([
                        'INVITE' => 'INVITE',
                        'REGISTER' => 'REGISTER',
                        'OPTIONS' => 'OPTIONS',
                        'BYE' => 'BYE',
                        'CANCEL' => 'CANCEL',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $method): Builder => $query->byMethod($method)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Door-knock attempts are immutable (created by OpenSIPS) - no bulk actions allowed
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
            'index' => Pages\ListDoorKnockAttempts::route('/'),
            'view' => Pages\ViewDoorKnockAttempt::route('/{record}'),
        ];
    }
}
