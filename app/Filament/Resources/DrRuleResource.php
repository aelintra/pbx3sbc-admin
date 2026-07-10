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

    protected static ?string $navigationLabel = 'Routing Rules';

    protected static ?string $navigationGroup = 'Peering';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Routing Rule';

    protected static ?string $pluralModelLabel = 'Routing Rules';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('groupid')
                    ->label('Group')
                    ->options([
                        '0' => 'Outbound (0)',
                        '1' => 'Inbound DID (1)',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('prefix')
                    ->label('Prefix')
                    ->maxLength(64)
                    ->helperText('Longest-prefix match. Empty = default/catch-all. Lab DID: 01924918076'),
                Forms\Components\TextInput::make('priority')
                    ->numeric()
                    ->default(10)
                    ->required()
                    ->helperText('Lower = higher priority when prefixes overlap.'),
                Forms\Components\TextInput::make('gwlist')
                    ->label('Gateway list (gwlist)')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('10')
                    ->helperText('Comma-separated gwids from Gateways (e.g. 1 or 1,2 or 10).')
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                $tokens = array_filter(array_map('trim', explode(',', (string) $value)));
                                if ($tokens === []) {
                                    $fail('gwlist must list at least one gwid.');
                                    return;
                                }
                                foreach ($tokens as $token) {
                                    if (str_starts_with($token, '#')) {
                                        continue; // dr_carriers optional
                                    }
                                    if (! DrGateway::where('gwid', $token)->exists()) {
                                        $fail("Unknown gwid: {$token}");
                                    }
                                }
                            };
                        },
                    ]),
                Forms\Components\Select::make('sort_alg')
                    ->options([
                        'N' => 'N — preserve order',
                        'W' => 'W — weight',
                        'Q' => 'Q — quality',
                    ])
                    ->default('N')
                    ->required(),
                Forms\Components\TextInput::make('routeid')
                    ->maxLength(255)
                    ->nullable()
                    ->helperText('Optional script route; leave empty for normal gwlist relay.'),
                Forms\Components\TextInput::make('timerec')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('sort_profile')
                    ->numeric()
                    ->nullable(),
                Forms\Components\TextInput::make('attrs')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('description')
                    ->maxLength(128)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ruleid')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('groupid')
                    ->label('Group')
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
                    ->searchable()
                    ->sortable()
                    ->placeholder('(default)'),
                Tables\Columns\TextColumn::make('gwlist')
                    ->label('gwlist')
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(40)
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('groupid')
                    ->label('Group')
                    ->options([
                        '0' => 'Outbound (0)',
                        '1' => 'Inbound DID (1)',
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
