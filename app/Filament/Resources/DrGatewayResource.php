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
use Filament\Tables\Table;

class DrGatewayResource extends Resource
{
    protected static ?string $model = DrGateway::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

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
                    ->description('A SIP address OpenSIPS can trust as a carrier source, or send calls to (carrier or Asterisk). Number routes pick these by name — you do not need to remember internal IDs.')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Name')
                            ->required()
                            ->maxLength(128)
                            ->placeholder('e.g. Magrathea inbound 87.238.72.129')
                            ->helperText('Shown in Number routes when choosing where a call goes.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('address')
                            ->label('SIP address')
                            ->required()
                            ->maxLength(128)
                            ->placeholder('sip:87.238.72.129:5060')
                            ->rules(['regex:/^sip:.+/'])
                            ->validationMessages([
                                'regex' => 'Address must be a SIP URI starting with sip:',
                            ])
                            ->helperText('Carrier signaling IP (inbound trust) or destination (outbound carrier / Asterisk).'),
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
                            ->maxLength(255)
                            ->nullable()
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
                Tables\Columns\TextColumn::make('address')
                    ->label('SIP address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('used_by')
                    ->label('Used by routes')
                    ->state(function (DrGateway $record): string {
                        $gwid = (string) $record->gwid;
                        $rules = DrRule::query()
                            ->whereRaw("FIND_IN_SET(?, REPLACE(gwlist, ' ', ''))", [$gwid])
                            ->orderBy('ruleid')
                            ->get(['groupid', 'prefix', 'description']);
                        if ($rules->isEmpty()) {
                            return '—';
                        }

                        return $rules->map(function (DrRule $r) {
                            $dir = (string) $r->groupid === '1' ? 'In' : 'Out';
                            $pfx = $r->prefix !== null && $r->prefix !== '' ? $r->prefix : 'default';
                            $label = $r->description ? " ({$r->description})" : '';

                            return "{$dir}:{$pfx}{$label}";
                        })->implode(', ');
                    })
                    ->wrap(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrGateways::route('/'),
            'create' => Pages\CreateDrGateway::route('/create'),
            'edit' => Pages\EditDrGateway::route('/{record}/edit'),
        ];
    }
}
