<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CdrResource\Pages;
use App\Models\Cdr;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;

class CdrResource extends Resource
{
    protected static ?string $model = Cdr::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static ?string $navigationLabel = 'CDR';

    protected static ?string $modelLabel = 'Call Detail Record';

    protected static ?string $pluralModelLabel = 'Call Detail Records';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        // CDR records are read-only (created by OpenSIPS)
        // Form is only used for view-only detail pages if needed
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
                Forms\Components\TextInput::make('sip_code')
                    ->label('SIP Code')
                    ->disabled(),
                Forms\Components\TextInput::make('sip_reason')
                    ->label('SIP Reason')
                    ->disabled(),
                Forms\Components\TextInput::make('duration')
                    ->label('Duration (seconds)')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created')
                    ->label('Start Time')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('time')
                    ->label('End Time')
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
                TextColumn::make('formatted_duration')
                    ->label('Duration')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('duration', $direction);
                    }),
                TextColumn::make('created')
                    ->label('Start Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('time')
                    ->label('End Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('sip_code')
                    ->label('SIP Code')
                    ->badge()
                    ->color(fn ($state) => $state == 200 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state, $record) => $state . ' ' . ($record->sip_reason ?? ''))
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created')
                    ->form([
                        Forms\Components\DatePicker::make('created_from_date')
                            ->label('Start Date')
                            ->default(now()->format('Y-m-d')),
                        Forms\Components\TextInput::make('created_from_time')
                            ->label('Start Time (HH:MM)')
                            ->placeholder('00:00')
                            ->default('00:00')
                            ->mask('99:99')
                            ->rules(['regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'])
                            ->helperText('24-hour format (e.g., 09:30, 14:15)')
                            ->live(),
                        Forms\Components\DatePicker::make('created_until_date')
                            ->label('End Date')
                            ->default(now()->format('Y-m-d'))
                            ->rules([
                                function ($get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $startDate = $get('created_from_date');
                                        $startTime = $get('created_from_time');
                                        $endTime = $get('created_until_time');
                                        
                                        if ($startDate && $value && $startTime && $endTime) {
                                            $startDateTime = $startDate . ' ' . $startTime . ':00';
                                            $endDateTime = $value . ' ' . $endTime . ':59';
                                            
                                            if (strtotime($startDateTime) >= strtotime($endDateTime)) {
                                                $fail('End date/time must be after start date/time.');
                                            }
                                        }
                                    };
                                },
                            ]),
                        Forms\Components\TextInput::make('created_until_time')
                            ->label('End Time (HH:MM)')
                            ->placeholder('23:59')
                            ->default('23:59')
                            ->mask('99:99')
                            ->rules([
                                'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/',
                                function ($get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $startDate = $get('created_from_date');
                                        $startTime = $get('created_from_time');
                                        $endDate = $get('created_until_date');
                                        
                                        // Only validate if all fields are filled
                                        if ($startDate && $endDate && $startTime && $value) {
                                            // Normalize times
                                            $startTimeParts = explode(':', $startTime);
                                            $startTimeNormalized = str_pad($startTimeParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($startTimeParts[1] ?? '00', 2, '0', STR_PAD_LEFT);
                                            
                                            $endTimeParts = explode(':', $value);
                                            $endTimeNormalized = str_pad($endTimeParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($endTimeParts[1] ?? '00', 2, '0', STR_PAD_LEFT);
                                            
                                            $startDateTime = $startDate . ' ' . $startTimeNormalized . ':00';
                                            $endDateTime = $endDate . ' ' . $endTimeNormalized . ':59';
                                            
                                            if (strtotime($startDateTime) >= strtotime($endDateTime)) {
                                                $fail('End time must be after start time.');
                                            }
                                        }
                                    };
                                },
                            ])
                            ->helperText('24-hour format (e.g., 17:45, 23:59)')
                            ->live(),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        $parts = [];
                        $startDate = $data['created_from_date'] ?? null;
                        $startTime = $data['created_from_time'] ?? null;
                        $endDate = $data['created_until_date'] ?? null;
                        $endTime = $data['created_until_time'] ?? null;
                        
                        if ($startDate || $startTime) {
                            $date = $startDate ?? 'today';
                            $time = $startTime ?? '00:00';
                            if ($time instanceof \DateTimeInterface) {
                                $time = $time->format('H:i');
                            }
                            $parts[] = "From: {$date} {$time}";
                        }
                        if ($endDate || $endTime) {
                            $date = $endDate ?? 'today';
                            $time = $endTime ?? '23:59';
                            if ($time instanceof \DateTimeInterface) {
                                $time = $time->format('H:i');
                            }
                            $parts[] = "To: {$date} {$time}";
                        }
                        
                        // Check if range is invalid and add prominent warning
                        if (!empty($parts)) {
                            $startDateCheck = $startDate ?: now()->format('Y-m-d');
                            $startTimeCheck = $startTime ?: '00:00';
                            $endDateCheck = $endDate ?: now()->format('Y-m-d');
                            $endTimeCheck = $endTime ?: '23:59';
                            
                            if ($startTimeCheck && $endTimeCheck) {
                                // Normalize times for comparison
                                $startParts = explode(':', $startTimeCheck);
                                $startNormalized = str_pad($startParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($startParts[1] ?? '00', 2, '0', STR_PAD_LEFT);
                                $endParts = explode(':', $endTimeCheck);
                                $endNormalized = str_pad($endParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($endParts[1] ?? '00', 2, '0', STR_PAD_LEFT);
                                
                                $startDateTime = $startDateCheck . ' ' . $startNormalized . ':00';
                                $endDateTime = $endDateCheck . ' ' . $endNormalized . ':59';
                                
                                if (strtotime($startDateTime) >= strtotime($endDateTime)) {
                                    $parts[] = "❌ INVALID RANGE";
                                }
                            }
                        }
                        
                        return !empty($parts) ? implode(', ', $parts) : null;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        // Handle start date/time
                        $startDate = $data['created_from_date'] ?? null;
                        $startTime = $data['created_from_time'] ?? null;
                        
                        // Convert time to string if it's a DateTime/Carbon object
                        if ($startTime instanceof \DateTimeInterface) {
                            $startTime = $startTime->format('H:i');
                        }
                        
                        $startDateTime = null;
                        // Apply filter if either date or time is provided
                        if ($startDate || $startTime) {
                            // Use provided date or default to today
                            $date = $startDate ?: now()->format('Y-m-d');
                            // Use provided time or default to start of day
                            $time = $startTime ?: '00:00';
                            
                            // Normalize time format to HH:MM
                            if (is_string($time)) {
                                $timeParts = explode(':', $time);
                                $time = str_pad($timeParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($timeParts[1] ?? '00', 2, '0', STR_PAD_LEFT);
                            }
                            
                            $startDateTime = $date . ' ' . $time . ':00';
                            $query->where('created', '>=', $startDateTime);
                        }
                        
                        // Handle end date/time
                        $endDate = $data['created_until_date'] ?? null;
                        $endTime = $data['created_until_time'] ?? null;
                        
                        // Convert time to string if it's a DateTime/Carbon object
                        if ($endTime instanceof \DateTimeInterface) {
                            $endTime = $endTime->format('H:i');
                        }
                        
                        $endDateTime = null;
                        // Apply filter if either date or time is provided
                        if ($endDate || $endTime) {
                            // Use provided date or default to today
                            $date = $endDate ?: now()->format('Y-m-d');
                            // Use provided time or default to end of day
                            $time = $endTime ?: '23:59';
                            
                            // Normalize time format to HH:MM
                            if (is_string($time)) {
                                $timeParts = explode(':', $time);
                                $time = str_pad($timeParts[0], 2, '0', STR_PAD_LEFT) . ':' . str_pad($timeParts[1] ?? '59', 2, '0', STR_PAD_LEFT);
                            }
                            
                            $endDateTime = $date . ' ' . $time . ':59';
                            
                            // Validate that end is after start
                            if ($startDateTime && strtotime($startDateTime) >= strtotime($endDateTime)) {
                                // Return empty result set if invalid range
                                return $query->whereRaw('1 = 0');
                            }
                            
                            $query->where('created', '<=', $endDateTime);
                        }
                        
                        return $query;
                    }),
                Filter::make('from_uri')
                    ->form([
                        Forms\Components\TextInput::make('from_uri')
                            ->label('From URI (partial match)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['from_uri'],
                            fn (Builder $query, $uri): Builder => $query->fromUri($uri)
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
                            fn (Builder $query, $uri): Builder => $query->toUri($uri)
                        );
                    }),
                SelectFilter::make('sip_code')
                    ->label('SIP Code')
                    ->options([
                        200 => '200 OK',
                        404 => '404 Not Found',
                        408 => '408 Request Timeout',
                        486 => '486 Busy Here',
                        487 => '487 Request Terminated',
                        500 => '500 Server Error',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $code): Builder => $query->where('sip_code', $code)
                        );
                    }),
                Filter::make('duration')
                    ->form([
                        Forms\Components\TextInput::make('duration_min')
                            ->label('Min Duration (seconds)')
                            ->numeric(),
                        Forms\Components\TextInput::make('duration_max')
                            ->label('Max Duration (seconds)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->durationRange(
                            $data['duration_min'] ?? null,
                            $data['duration_max'] ?? null
                        );
                    }),
                Filter::make('successful')
                    ->label('Successful Calls Only')
                    ->query(fn (Builder $query): Builder => $query->successful())
                    ->toggle(),
                Filter::make('failed')
                    ->label('Failed Calls Only')
                    ->query(fn (Builder $query): Builder => $query->failed())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // CDR records are immutable (created by OpenSIPS) - no bulk actions allowed
            ])
            ->defaultSort('created', 'desc')
            ->paginated([10, 25, 50, 100]) // Reasonable pagination options - NO "ALL" option for performance
            ->defaultPaginationPageOption(25) // Default to 25 records per page
            ->poll('30s'); // Auto-refresh every 30 seconds for new CDRs
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
            'index' => Pages\ListCdrs::route('/'),
            'view' => Pages\ViewCdr::route('/{record}'),
        ];
    }
}
