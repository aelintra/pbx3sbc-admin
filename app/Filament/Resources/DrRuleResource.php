<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrRuleResource\Pages;
use App\Models\DrGateway;
use App\Models\DrRule;
use App\Services\DrRulePrefixOverlap;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class DrRuleResource extends Resource
{
    protected static ?string $model = DrRule::class;

    protected static ?string $navigationIcon = 'lucide-route';

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
                            ->live()
                            ->helperText('Outbound runs when Asterisk sends a long number to the SBC. Inbound runs for trusted carrier INVITEs.'),
                        Forms\Components\TextInput::make('prefix')
                            ->label('Number prefix')
                            ->maxLength(64)
                            ->placeholder('e.g. 01924918076 or leave empty')
                            ->live(onBlur: true)
                            ->helperText('Longest-prefix match on the SIP user part. Empty = default / catch-all for that direction.')
                            ->rules([
                                function (Get $get, $livewire) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get, $livewire): void {
                                        $groupid = $get('groupid');
                                        if ($groupid === null || $groupid === '') {
                                            return;
                                        }

                                        $exclude = null;
                                        if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                                            $exclude = (int) $livewire->getRecord()->getKey();
                                        }

                                        $dup = DrRulePrefixOverlap::findDuplicate($groupid, is_string($value) ? $value : null, $exclude);
                                        if ($dup !== null) {
                                            $label = $dup->description ? " — {$dup->description}" : '';
                                            $shown = DrRulePrefixOverlap::normalize($dup->prefix);
                                            $shown = $shown === '' ? '(default / empty)' : "“{$shown}”";
                                            $fail("Another route already uses this direction and prefix {$shown} (rule {$dup->ruleid}{$label}).");
                                        }
                                    };
                                },
                            ]),
                        Forms\Components\Placeholder::make('prefix_overlap_hint')
                            ->label('Prefix overlap')
                            ->content(function (Get $get, $livewire): HtmlString|string {
                                $groupid = $get('groupid');
                                $prefix = $get('prefix');
                                if ($groupid === null || $groupid === '') {
                                    return '';
                                }

                                $exclude = null;
                                if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                                    $exclude = (int) $livewire->getRecord()->getKey();
                                }

                                $hint = DrRulePrefixOverlap::nestingHint($groupid, is_string($prefix) ? $prefix : null, $exclude);
                                if ($hint === null) {
                                    return '';
                                }

                                return new HtmlString(
                                    '<span class="text-sm text-amber-700 dark:text-amber-400">'.e($hint).'</span>'
                                );
                            })
                            ->visible(function (Get $get, $livewire): bool {
                                $groupid = $get('groupid');
                                $prefix = $get('prefix');
                                if ($groupid === null || $groupid === '' || ! filled(DrRulePrefixOverlap::normalize(is_string($prefix) ? $prefix : null))) {
                                    return false;
                                }

                                $exclude = null;
                                if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                                    $exclude = (int) $livewire->getRecord()->getKey();
                                }

                                return DrRulePrefixOverlap::nestingHint($groupid, is_string($prefix) ? $prefix : null, $exclude) !== null;
                            })
                            ->columnSpanFull(),
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
                Tables\Filters\SelectFilter::make('peer')
                    ->label('Peer')
                    ->options(fn () => DrGateway::optionsForSelect())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $gwid = $data['value'] ?? null;
                        if ($gwid === null || $gwid === '') {
                            return $query;
                        }

                        return $query->whereRaw(
                            "FIND_IN_SET(?, REPLACE(gwlist, ' ', ''))",
                            [(string) $gwid]
                        );
                    }),
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
