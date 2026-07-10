<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrRuleResource\Pages;
use App\Models\DrGateway;
use App\Models\DrRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DrRuleResource extends Resource
{
    protected static ?string $model = DrRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Number routes';

    protected static ?string $navigationGroup = 'Peering';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Number route';

    protected static ?string $pluralModelLabel = 'Number routes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('What this route does')
                    ->description('Match a dialled or inbound number prefix, then send the call to one or more peers (in order).')
                    ->schema([
                        Forms\Components\Select::make('groupid')
                            ->label('Direction')
                            ->options([
                                '0' => 'Outbound — fleet dials PSTN (pick carrier peer)',
                                '1' => 'Inbound — carrier DID arrives (pick Asterisk destination)',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Outbound runs when Asterisk sends a long number to the SBC. Inbound runs for trusted carrier INVITEs.'),
                        Forms\Components\TextInput::make('prefix')
                            ->label('Number prefix')
                            ->maxLength(64)
                            ->placeholder('e.g. 01924918076 or leave empty')
                            ->helperText('Longest-prefix match on the SIP user part. Empty = default / catch-all for that direction.'),
                        Forms\Components\TextInput::make('description')
                            ->label('Label')
                            ->maxLength(128)
                            ->placeholder('Short note for operators')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('gwlist')
                            ->label('Send call to')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->options(fn () => DrGateway::optionsForSelect())
                            ->helperText('Pick peers by name. First = primary; further entries are failover order.')
                            ->afterStateHydrated(function (Forms\Components\Select $component, $state): void {
                                if (is_array($state)) {
                                    return;
                                }
                                $component->state(array_values(array_filter(array_map(
                                    'trim',
                                    explode(',', (string) $state)
                                ))));
                            })
                            ->dehydrateStateUsing(function ($state): string {
                                if (is_array($state)) {
                                    return implode(',', array_values(array_filter(array_map('strval', $state))));
                                }

                                return (string) $state;
                            })
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail): void {
                                        $tokens = is_array($value)
                                            ? array_values(array_filter(array_map('strval', $value)))
                                            : array_values(array_filter(array_map('trim', explode(',', (string) $value))));
                                        if ($tokens === []) {
                                            $fail('Select at least one peer / destination.');

                                            return;
                                        }
                                        foreach ($tokens as $token) {
                                            if (str_starts_with($token, '#')) {
                                                continue;
                                            }
                                            if (! DrGateway::where('gwid', $token)->exists()) {
                                                $fail("Unknown peer id: {$token}");
                                            }
                                        }
                                    };
                                },
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Advanced')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(10)
                            ->required()
                            ->helperText('Lower wins when two prefixes are equally long.'),
                        Forms\Components\Select::make('sort_alg')
                            ->label('Gateway order')
                            ->options([
                                'N' => 'Keep listed order (recommended)',
                                'W' => 'Weight',
                                'Q' => 'Quality',
                            ])
                            ->default('N')
                            ->required(),
                        Forms\Components\TextInput::make('routeid')
                            ->label('Script route id')
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('Leave empty unless you have a custom OpenSIPS route.'),
                        Forms\Components\TextInput::make('timerec')
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\TextInput::make('sort_profile')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\TextInput::make('attrs')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('groupid')
                    ->label('Direction')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        '0' => 'Outbound',
                        '1' => 'Inbound',
                        default => (string) $state,
                    })
                    ->color(fn ($state) => match ((string) $state) {
                        '0' => 'info',
                        '1' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('prefix')
                    ->label('Prefix')
                    ->searchable()
                    ->sortable()
                    ->placeholder('(default)'),
                Tables\Columns\TextColumn::make('gwlist')
                    ->label('Goes to')
                    ->formatStateUsing(fn (?string $state) => DrGateway::labelsForGwlist($state))
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Label')
                    ->wrap()
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ruleid')
                    ->label('Rule ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('groupid')
                    ->label('Direction')
                    ->options([
                        '0' => 'Outbound',
                        '1' => 'Inbound',
                    ]),
            ])
            ->defaultSort('ruleid')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        app(\App\Services\OpenSIPSMIService::class)->drReload();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrRules::route('/'),
            'create' => Pages\CreateDrRule::route('/create'),
            'edit' => Pages\EditDrRule::route('/{record}/edit'),
        ];
    }
}
