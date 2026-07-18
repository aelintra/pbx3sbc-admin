<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrGatewayResource\Pages;
use App\Models\DrGateway;
use App\Models\DrRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class DrGatewayResource extends Resource
{
    protected static ?string $model = DrGateway::class;

    protected static ?string $navigationIcon = 'lucide-network';

    protected static ?string $navigationLabel = 'Peers';

    protected static ?string $navigationGroup = 'Peering';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Peer';

    protected static ?string $pluralModelLabel = 'Peers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Peer')
                    ->description('Logical carriers group several peers: one outbound SIP destination (often a DNS name) plus inbound trust IPs. Same OpenSIPS rows underneath — group key is stored in attrs.')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Name')
                            ->required()
                            ->maxLength(128)
                            ->placeholder('e.g. Magrathea inbound 87.238.72.129')
                            ->helperText('Shown in Number routes when choosing where a call goes.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('carrier_label')
                            ->label('Carrier')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('e.g. Magrathea')
                            ->helperText('Groups this peer with others for the same provider (stored as carrier=… in attrs).'),
                        Forms\Components\Select::make('peer_role')
                            ->label('Role')
                            ->required()
                            ->options([
                                DrGateway::ROLE_OUTBOUND => 'Outbound destination',
                                DrGateway::ROLE_INBOUND => 'Inbound trust (is_from_gw)',
                                DrGateway::ROLE_ASTERISK => 'Asterisk destination',
                            ])
                            ->helperText('Outbound: Prefer sip:fqdn:5060 (Route53). Inbound: literal signaling IPs. Asterisk: fleet node for DID delivery.'),
                        Forms\Components\TextInput::make('address')
                            ->label('SIP address')
                            ->required()
                            ->maxLength(128)
                            ->placeholder('sip:sipipgw.magrathea.net:5060')
                            ->rules(['regex:/^sip:.+/'])
                            ->validationMessages([
                                'regex' => 'Address must be a SIP URI starting with sip:',
                            ])
                            ->helperText('FQDN for outbound when the carrier uses DNS; IPs for inbound allow lists.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('state')
                            ->options([
                                0 => 'Enabled',
                                1 => 'Disabled',
                                2 => 'Temp disabled',
                            ])
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Advanced')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('gwid')
                            ->label('Internal ID (gwid)')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => (string) ((int) (DrGateway::query()->max('gwid') ?? 0) + 1))
                            ->helperText('Auto-assigned for new peers. Stored in OpenSIPS dr_gateways; rarely edited by hand.'),
                        Forms\Components\TextInput::make('type')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('strip')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('pri_prefix')
                            ->maxLength(16)
                            ->nullable(),
                        Forms\Components\Select::make('probe_mode')
                            ->options([
                                0 => 'No probing',
                                1 => 'Probe when disabled',
                                2 => 'Always probe',
                            ])
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('socket')
                            ->maxLength(128)
                            ->nullable(),
                        Forms\Components\TextInput::make('attrs')
                            ->label('attrs (raw)')
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('Escape hatch. Carrier/Role above write carrier= and role=; other keys are preserved on save.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder(fn (DrGateway $record) => $record->address),
                Tables\Columns\TextColumn::make('peer_role_badge')
                    ->label('Role')
                    ->badge()
                    ->state(fn (DrGateway $record): string => $record->peerRoleLabel())
                    ->color(fn (DrGateway $record): string => match ($record->peerRole()) {
                        DrGateway::ROLE_OUTBOUND => 'info',
                        DrGateway::ROLE_INBOUND => 'warning',
                        DrGateway::ROLE_ASTERISK => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('address')
                    ->label('SIP address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('used_by')
                    ->label('Used by routes')
                    ->state(function (DrGateway $record): string {
                        $gwid = (string) $record->gwid;
                        $counts = DrRule::query()
                            ->whereRaw("FIND_IN_SET(?, REPLACE(gwlist, ' ', ''))", [$gwid])
                            ->selectRaw('groupid, COUNT(*) as c')
                            ->groupBy('groupid')
                            ->pluck('c', 'groupid');
                        $inbound = (int) ($counts['1'] ?? 0);
                        $outbound = (int) ($counts['0'] ?? 0);
                        $parts = [];
                        if ($inbound > 0) {
                            $parts[] = $inbound === 1 ? '1 inbound' : "{$inbound} inbound";
                        }
                        if ($outbound > 0) {
                            $parts[] = $outbound === 1 ? '1 outbound' : "{$outbound} outbound";
                        }

                        return $parts === [] ? '—' : implode(' · ', $parts);
                    })
                    ->url(function (DrGateway $record): ?string {
                        $gwid = (string) $record->gwid;
                        $used = DrRule::query()
                            ->whereRaw("FIND_IN_SET(?, REPLACE(gwlist, ' ', ''))", [$gwid])
                            ->exists();
                        if (! $used) {
                            return null;
                        }

                        return DrRuleResource::getUrl('index', [
                            'tableFilters' => [
                                'peer' => [
                                    'value' => $gwid,
                                ],
                            ],
                        ]);
                    })
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'primary'),
                Tables\Columns\TextColumn::make('state')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        0 => 'Enabled',
                        1 => 'Disabled',
                        2 => 'Temp disabled',
                        default => (string) $state,
                    })
                    ->color(fn ($state) => match ((int) $state) {
                        0 => 'success',
                        1 => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('gwid')
                    ->label('ID')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
            ])
            ->defaultGroup('attrs')
            ->groups([
                Group::make('attrs')
                    ->label('Carrier')
                    ->getKeyFromRecordUsing(fn (DrGateway $record): string => $record->carrierSlug() !== ''
                        ? $record->carrierSlug()
                        : 'ungrouped')
                    ->getTitleFromRecordUsing(fn (DrGateway $record): string => $record->carrierGroupTitle())
                    ->groupQueryUsing(fn ($query) => $query)
                    ->orderQueryUsing(function ($query, string $direction) {
                        return $query->orderBy('attrs', $direction)->orderByRaw('CAST(gwid AS UNSIGNED)');
                    }),
            ])
            ->defaultSort('description')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (DrGateway $record, Tables\Actions\DeleteAction $action) {
                        $gwid = (string) $record->gwid;
                        $referenced = DrRule::query()
                            ->whereRaw("FIND_IN_SET(?, REPLACE(gwlist, ' ', ''))", [$gwid])
                            ->exists();
                        if ($referenced) {
                            Notification::make()
                                ->title('Cannot delete peer')
                                ->body('This peer is still selected on one or more number routes. Edit or remove those routes first.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    })
                    ->after(function () {
                        app(\App\Services\OpenSIPSMIService::class)->drReload();
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * Apply Carrier / Role form fields into attrs before persist.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyCarrierFieldsToData(array $data): array
    {
        $label = $data['carrier_label'] ?? null;
        $role = $data['peer_role'] ?? null;
        unset($data['carrier_label'], $data['peer_role']);

        $gw = new DrGateway;
        $gw->attrs = $data['attrs'] ?? null;
        $gw->setCarrierAttrs(
            is_string($label) ? $label : null,
            is_string($role) ? $role : null
        );
        $data['attrs'] = $gw->attrs;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrGateways::route('/'),
            'create' => Pages\CreateDrGateway::route('/create'),
            'edit' => Pages\EditDrGateway::route('/{record}/edit'),
        ];
    }
}
