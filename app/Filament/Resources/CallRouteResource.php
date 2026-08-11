<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CallRouteResource\Pages;
use App\Filament\Resources\DispatcherResource;
use App\Models\Domain;
use App\Models\Dispatcher;
use App\Services\FleetDomainOwnership;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CallRouteResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'lucide-git-branch';

    protected static ?string $navigationLabel = 'Domain Routes';

    protected static ?string $navigationGroup = 'Routing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Domain')
                    ->schema([
                        Forms\Components\Placeholder::make('domain_view')
                            ->label('Domain')
                            ->content(fn ($livewire) => $livewire->getRecord()?->domain ?? '—'),
                        Forms\Components\Placeholder::make('setid_view')
                            ->label('Set ID')
                            ->content(fn ($livewire) => (string) ($livewire->getRecord()?->setid ?? '—')),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof ViewRecord),

                Forms\Components\Section::make('Destinations')
                    ->schema([
                        Forms\Components\View::make('filament.forms.components.existing-destinations-table')
                            ->viewData(function ($livewire) {
                                $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                                if ($record && ! $record->relationLoaded('dispatchers')) {
                                    $record->load('dispatchers');
                                }

                                return [
                                    'dispatchers' => $record?->dispatchers ?? collect(),
                                ];
                            }),
                    ])
                    ->description('Read-only. Use Manage destinations on the list to edit individual SIP URIs.')
                    ->visible(fn ($livewire) => $livewire instanceof ViewRecord),

                Forms\Components\Section::make('Domain')
                    ->schema([
                        Forms\Components\Radio::make('domain_type')
                            ->label('Domain Type')
                            ->options([
                                'existing' => 'Use existing domain',
                                'new' => 'Create new domain',
                            ])
                            ->default('existing')
                            ->live()
                            ->required()
                            ->descriptions([
                                'existing' => 'Select from existing domains',
                                'new' => 'Enter a new domain name',
                            ])
                            ->visible(fn ($livewire) => 
                                !($livewire instanceof EditRecord)
                                && !($livewire instanceof ViewRecord)
                            )
                            ->autofocus()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'new') {
                                    $set('domain_select', '__new__');
                                    $set('domain', '');
                                    $set('has_existing_destinations', false);
                                } else {
                                    $set('domain_select', null);
                                    $set('domain', '');
                                    $set('has_existing_destinations', false);
                                }
                                // Clear new destination fields when domain type changes
                                $set('destination', '');
                                $set('weight', 1);
                                $set('priority', 0);
                                $set('state', 0);
                                $set('description', '');
                            }),

                        Forms\Components\Select::make('domain_select')
                            ->label('Select Domain')
                            ->options(function () {
                                return Domain::orderBy('domain')->pluck('domain', 'domain')->toArray();
                            })
                            ->searchable()
                            ->placeholder('Search or select a domain...')
                            ->live()
                            ->required()
                            ->visible(fn (callable $get, $livewire) => 
                                !($livewire instanceof EditRecord)
                                && !($livewire instanceof ViewRecord)
                                && $get('domain_type') === 'existing'
                            )
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
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
                            ->helperText('Search for an existing domain'),

                        Forms\Components\TextInput::make('domain')
                            ->label('New Domain Name')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->rules([
                                'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
                            ])
                            ->validationMessages([
                                'regex' => 'The domain must be a valid domain name (e.g., example.com).',
                            ])
                            ->visible(fn (callable $get, $livewire) => 
                                !($livewire instanceof EditRecord)
                                && !($livewire instanceof ViewRecord)
                                && $get('domain_type') === 'new'
                            )
                            ->placeholder('Enter a new domain name (e.g., example.com)')
                            ->helperText('Enter a valid domain name')
                            ->autofocus(),
                    ])
                    ->visible(fn ($livewire) => 
                        !($livewire instanceof EditRecord)
                        && !($livewire instanceof ViewRecord)
                    ),

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
                        // Only show on Create page, not Edit or View pages
                        return !($livewire instanceof EditRecord)
                            && !($livewire instanceof ViewRecord)
                            && $get('domain_type') === 'existing'
                            && $get('domain_select');
                    })
                    ->collapsible()
                    ->description('Read-only view of existing destinations for this domain'),

                Forms\Components\Section::make('Destination')
                    ->schema([
                        Forms\Components\TextInput::make('destination')
                            ->required()
                            ->maxLength(192)
                            ->rules([
                                'regex:'.\App\Support\SipIpUri::REGEX,
                            ])
                            ->validationMessages([
                                'regex' => \App\Support\SipIpUri::MESSAGE,
                            ])
                            ->label('SIP URI')
                            ->placeholder('sip:192.168.1.100:5060')
                            ->helperText('Literal IP only (sip:a.b.c.d:5060). DNS names break Asterisk reverse-IP lookup.'),

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
                    ])
                    ->visible(fn ($livewire) => ! ($livewire instanceof ViewRecord)),
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
                    ->weight('bold')
                    ->description(fn (Domain $record): ?string => FleetDomainOwnership::isFleetOwned($record->attrs)
                        ? 'Fleet-owned — retarget in Fleet (move / Repair / reconcile)'
                        : null),

                Tables\Columns\IconColumn::make('fleet_owned')
                    ->label('Fleet')
                    ->boolean()
                    ->getStateUsing(fn (Domain $record): bool => FleetDomainOwnership::isFleetOwned($record->attrs))
                    ->trueIcon('lucide-lock')
                    ->falseIcon('lucide-pencil')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (Domain $record): string => FleetDomainOwnership::isFleetOwned($record->attrs)
                        ? 'Projected from Fleet catalog — rename/delete/setid not offered'
                        : 'Standalone / edge-authored'),

                Tables\Columns\TextColumn::make('setid')
                    ->label('Set ID')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dispatchers_count')
                    ->label('# Destinations')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('dispatchers_list')
                    ->label('Destinations')
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
            ->checkIfRecordIsSelectableUsing(
                fn (Domain $record): bool => ! FleetDomainOwnership::isFleetOwned($record->attrs)
            )
            ->actions([
                Tables\Actions\Action::make('edit_domain')
                    ->iconButton()
                    ->icon('lucide-pencil')
                    ->tooltip('Edit domain')
                    ->color('warning')
                    ->visible(fn (Domain $record): bool => static::canEdit($record))
                    ->authorize(fn (Domain $record): bool => static::canEdit($record))
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
                        $miOk = app(\App\Services\OpenSIPSMIService::class)->domainReload();

                        if ($miOk) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Domain updated')
                                ->body('The domain has been updated and OpenSIPS modules reloaded.')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('Domain updated — reload failed')
                                ->body('The domain was updated, but OpenSIPS modules could not be reloaded. You may need to reload them manually.')
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('manage_destinations')
                    ->iconButton()
                    ->icon('lucide-server')
                    ->tooltip(fn (Domain $record): string => FleetDomainOwnership::isFleetOwned($record->attrs)
                        ? 'Fleet owns this set — change backends via Fleet Instances (not Magrathea)'
                        : 'Manage destinations')
                    ->color(fn (Domain $record): string => FleetDomainOwnership::isFleetOwned($record->attrs) ? 'gray' : 'info')
                    ->visible(fn (Domain $record): bool => ! FleetDomainOwnership::isFleetOwned($record->attrs))
                    ->url(fn ($record) => DispatcherResource::getUrl('index', [
                        'tableFilters' => [
                            'setid' => [
                                'value' => $record->setid,
                            ],
                        ],
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn (Domain $record): bool => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Domain Route')
                    ->modalDescription(fn ($record) => 
                        'This will delete the domain "' . $record->domain . '" and ALL of its destinations. ' .
                        'This action cannot be undone. ' .
                        'If you only want to delete individual destinations, use the "Manage" button instead.'
                    )
                    ->modalSubmitActionLabel('Delete Route')
                    ->before(function ($record) {
                        // Delete associated dispatchers before deleting domain
                        $record->dispatchers()->delete();
                    })
                    ->after(function ($record) {
                        // Reload OpenSIPS modules after deletion
                        $miService = app(\App\Services\OpenSIPSMIService::class);
                        $domainOk = $miService->domainReload();
                        $dispatcherOk = $miService->dispatcherReload();
                        if (! $domainOk || ! $dispatcherOk) {
                            // Store in session - will be checked in successNotification
                            session()->flash('opensips_mi_failed', true);
                        }
                    })
                    ->successRedirectUrl(CallRouteResource::getUrl('index'))
                    ->successNotification(function () {
                        $body = 'The domain route has been deleted successfully.';
                        
                        // Check if MI reload failed
                        if (session()->has('opensips_mi_failed')) {
                            session()->forget('opensips_mi_failed');
                            $body .= ' However, OpenSIPS modules could not be reloaded. You may need to reload them manually.';
                        }
                        
                        return Notification::make()
                            ->success()
                            ->title('Domain route deleted')
                            ->body($body);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if (FleetDomainOwnership::isFleetOwned($record->attrs)) {
                                    Notification::make()
                                        ->title('Fleet-owned domain routes cannot be deleted here')
                                        ->body('Remove tenants via Fleet Delete. Magrathea must not delete fleet=domain rows.')
                                        ->danger()
                                        ->send();
                                    throw new \Filament\Support\Exceptions\Halt;
                                }
                            }
                            foreach ($records as $record) {
                                $record->dispatchers()->delete();
                            }
                        })
                        ->after(function ($records) {
                            // Reload OpenSIPS modules after bulk deletion
                            $miService = app(\App\Services\OpenSIPSMIService::class);
                            $domainOk = $miService->domainReload();
                            $dispatcherOk = $miService->dispatcherReload();
                            $miReloadSuccess = $domainOk && $dispatcherOk;

                            // Store MI status in session to check in success notification
                            session()->put('last_delete_mi_status', $miReloadSuccess);
                        })
                        ->successRedirectUrl(CallRouteResource::getUrl('index'))
                        ->successNotification(function () {
                            $miStatus = session()->pull('last_delete_mi_status', true);
                            
                            $body = 'The domain routes have been deleted successfully.';
                            if (!$miStatus) {
                                $body .= ' However, OpenSIPS modules could not be reloaded. You may need to reload them manually.';
                            }
                            
                            return Notification::make()
                                ->success()
                                ->title('Domain routes deleted')
                                ->body($body);
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
        return 'Domain Route';
    }

    /**
     * Get the plural label for the resource
     */
    public static function getPluralModelLabel(): string
    {
        return 'Domain Routes';
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
