<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CallRouteResource\Pages;
use App\Filament\Resources\DispatcherResource;
use App\Models\Domain;
use App\Models\Dispatcher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CallRouteResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Call Routes';

    protected static ?string $navigationGroup = 'Routing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Domain')
                    ->schema([
                        Forms\Components\Select::make('domain_select')
                            ->label('')
                            ->options(function () {
                                $domains = Domain::orderBy('domain')->pluck('domain', 'domain')->toArray();
                                $domains['__new__'] = '+ Create new domain';
                                return $domains;
                            })
                            ->searchable()
                            ->placeholder('Select an existing domain')
                            ->reactive()
                            ->required()
                            ->visible(fn ($livewire) => !($livewire instanceof \App\Filament\Resources\CallRouteResource\Pages\EditCallRoute))
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === '__new__') {
                                    $set('domain', '');
                                    $set('has_existing_destinations', false);
                                } else {
                                    $set('domain', $state);
                                    // Check if domain has existing destinations
                                    $domain = Domain::where('domain', $state)->with('dispatchers')->first();
                                    $set('has_existing_destinations', $domain && $domain->dispatchers->isNotEmpty());
                                }
                                // Clear new destination fields when domain changes
                                $set('destination', '');
                                $set('weight', 1);
                                $set('priority', 0);
                                $set('state', 0);
                                $set('description', '');
                            })
                            ->helperText('Select an existing domain or choose "Create new domain"'),

                        Forms\Components\TextInput::make('domain')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->rules([
                                'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
                            ])
                            ->validationMessages([
                                'regex' => 'The domain must be a valid domain name (e.g., example.com).',
                            ])
                            ->label('')
                            ->visible(fn (callable $get, $livewire) => 
                                !($livewire instanceof \App\Filament\Resources\CallRouteResource\Pages\EditCallRoute)
                                && $get('domain_select') === '__new__'
                            )
                            ->placeholder('Enter a new domain name (e.g., example.com)'),
                    ])
                    ->visible(fn ($livewire) => !($livewire instanceof \App\Filament\Resources\CallRouteResource\Pages\EditCallRoute)),

                Forms\Components\Section::make('Existing Destinations')
                    ->schema([
                        Forms\Components\View::make('filament.forms.components.existing-destinations-table')
                            ->viewData(function (callable $get) {
                                $domainSelect = $get('domain_select');
                                if ($domainSelect && $domainSelect !== '__new__') {
                                    $domain = Domain::where('domain', $domainSelect)->with('dispatchers')->first();
                                    return [
                                        'dispatchers' => $domain ? $domain->dispatchers : collect(),
                                    ];
                                }
                                return ['dispatchers' => collect()];
                            }),
                    ])
                    ->visible(function (callable $get, $livewire) {
                        // Only show on Create page, not Edit page
                        return !$livewire instanceof \App\Filament\Resources\CallRouteResource\Pages\EditCallRoute
                            && $get('domain_select') 
                            && $get('domain_select') !== '__new__';
                    })
                    ->collapsible()
                    ->description('Read-only view of existing destinations for this domain'),

                Forms\Components\Section::make('Destination')
                    ->schema([
                        Forms\Components\TextInput::make('destination')
                            ->required()
                            ->maxLength(192)
                            ->rules([
                                'regex:/^sip:((\[([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\]|([0-9]{1,3}\.){3}[0-9]{1,3}|([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}))(:[0-9]{1,5})?$/',
                            ])
                            ->validationMessages([
                                'regex' => 'The destination must start with "sip:" followed by an IP address or domain name (e.g., sip:192.168.1.100:5060).',
                            ])
                            ->label('SIP URI')
                            ->placeholder('sip:192.168.1.100:5060')
                            ->helperText('Format: sip:host:port'),

                        Forms\Components\TextInput::make('weight')
                            ->numeric()
                            ->default(1)
                            ->label('Weight')
                            ->helperText('Load balancing weight'),

                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->label('Priority')
                            ->helperText('Priority for failover'),

                        Forms\Components\Select::make('state')
                            ->options([
                                0 => 'Active',
                                1 => 'Inactive',
                            ])
                            ->default(0)
                            ->label('State'),

                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->maxLength(64)
                            ->rows(2)
                            ->label('Description')
                            ->helperText('Description for this destination'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('dispatchers'))
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->label('Domain')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('dispatchers_count')
                    ->counts('dispatchers')
                    ->label('# Destinations')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('dispatchers_list')
                    ->label('Destinations')
                    ->getStateUsing(function ($record) {
                        // Load dispatchers for this domain's setid
                        $destinations = Dispatcher::where('setid', $record->setid)->get();
                        
                        if ($destinations->isEmpty()) {
                            return 'No destinations';
                        }
                        
                        return $destinations->take(3)->map(function ($dispatcher) {
                            $status = $dispatcher->state == 0 ? '✓' : '✗';
                            return "{$status} {$dispatcher->destination}";
                        })->join(', ') . ($destinations->count() > 3 ? '...' : '');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('last_modified')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Modified')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('edit_domain')
                    ->label('Edit Domain')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->modalHeading(fn ($record) => 'Edit Domain: ' . $record->domain)
                    ->form([
                        Forms\Components\TextInput::make('domain')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->rules([
                                'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
                            ])
                            ->validationMessages([
                                'regex' => 'The domain must be a valid domain name (e.g., example.com).',
                            ])
                            ->label('Domain Name'),
                    ])
                    ->fillForm(function ($record) {
                        return [
                            'domain' => $record->domain,
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $record->update([
                            'domain' => $data['domain'],
                            'last_modified' => now(),
                        ]);

                        // Reload OpenSIPS modules after domain update
                        try {
                            $miService = app(\App\Services\OpenSIPSMIService::class);
                            $miService->domainReload();
                        } catch (\Exception $e) {
                            \Log::warning('OpenSIPS MI reload failed after domain update', ['error' => $e->getMessage()]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Domain updated')
                            ->body('The domain has been updated and OpenSIPS modules reloaded.')
                            ->send();
                    }),
                Tables\Actions\Action::make('manage_destinations')
                    ->label('Manage Destinations')
                    ->icon('heroicon-o-server')
                    ->color('info')
                    ->url(fn ($record) => DispatcherResource::getUrl('index', [
                        'tableFilters' => [
                            'setid' => [
                                'value' => $record->setid,
                            ],
                        ],
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Call Route')
                    ->modalDescription(fn ($record) => 
                        'This will delete the domain "' . $record->domain . '" and ALL of its destinations. ' .
                        'This action cannot be undone. ' .
                        'If you only want to delete individual destinations, use the "Manage" button instead.'
                    )
                    ->modalSubmitActionLabel('Delete Route')
                    ->before(function ($record) {
                        // Delete associated dispatchers before deleting domain
                        Dispatcher::where('setid', $record->setid)->delete();
                    })
                    ->after(function () {
                        // Reload OpenSIPS modules after deletion
                        try {
                            $miService = app(\App\Services\OpenSIPSMIService::class);
                            $miService->domainReload();
                            $miService->dispatcherReload();
                        } catch (\Exception $e) {
                            \Log::warning('OpenSIPS MI reload failed after route deletion', ['error' => $e->getMessage()]);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Delete associated dispatchers before deleting domains
                            foreach ($records as $record) {
                                Dispatcher::where('setid', $record->setid)->delete();
                            }
                        })
                        ->after(function () {
                            // Reload OpenSIPS modules after bulk deletion
                            try {
                                $miService = app(\App\Services\OpenSIPSMIService::class);
                                $miService->domainReload();
                                $miService->dispatcherReload();
                            } catch (\Exception $e) {
                                \Log::warning('OpenSIPS MI reload failed after bulk route deletion', ['error' => $e->getMessage()]);
                            }
                        }),
                ]),
            ])
            ->defaultSort('domain');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the label for the resource
     */
    public static function getModelLabel(): string
    {
        return 'Call Route';
    }

    /**
     * Get the plural label for the resource
     */
    public static function getPluralModelLabel(): string
    {
        return 'Call Routes';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCallRoutes::route('/'),
            'create' => Pages\CreateCallRoute::route('/create'),
            'view' => Pages\ViewCallRoute::route('/{record}'),
            'edit' => Pages\EditCallRoute::route('/{record}/edit'),
        ];
    }
}
