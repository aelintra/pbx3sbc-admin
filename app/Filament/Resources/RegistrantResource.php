<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistrantResource\Pages;
use App\Models\Registrant;
use App\Services\OpenSIPSMIService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegistrantResource extends Resource
{
    protected static ?string $model = Registrant::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Registrations';

    protected static ?string $navigationGroup = 'Peering';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Carrier registration';

    protected static ?string $pluralModelLabel = 'Registrations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('OpenSIPS → carrier REGISTER')
                    ->description('For carriers that require the SBC to register (username/password). IP-trusted peers (e.g. Magrathea) use Peers only — no row here. Registration carriers usually need both a Peer (signaling IP / outbound) and a Registration.')
                    ->schema([
                        Forms\Components\TextInput::make('registrar')
                            ->label('Registrar URI')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('sip:registrar.example.com:5060')
                            ->rules(['regex:/^sip:.+/'])
                            ->validationMessages([
                                'regex' => 'Must be a SIP URI starting with sip:',
                            ])
                            ->helperText('Where OpenSIPS sends REGISTER.'),
                        Forms\Components\TextInput::make('aor')
                            ->label('AOR (To URI)')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('sip:trunk-id@example.com')
                            ->rules(['regex:/^sip:.+/'])
                            ->validationMessages([
                                'regex' => 'Must be a SIP URI starting with sip:',
                            ])
                            ->helperText('Address of record — To header on REGISTER.'),
                        Forms\Components\TextInput::make('binding_URI')
                            ->label('Contact (binding URI)')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('sip:sbc.pbx3.com:5060')
                            ->rules(['regex:/^sip:.+/'])
                            ->validationMessages([
                                'regex' => 'Must be a SIP URI starting with sip:',
                            ])
                            ->helperText('SBC public Contact — where the carrier should send inbound INVITEs.'),
                        Forms\Components\TextInput::make('username')
                            ->label('Auth username')
                            ->maxLength(64)
                            ->autocomplete(false)
                            ->extraInputAttributes([
                                'autocomplete' => 'off',
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                            ])
                            ->helperText('Required when the registrar challenges with 401/407.'),
                        // Field name deliberately not "password" — Safari treats that as account signup and auto-fills a strong password.
                        Forms\Components\TextInput::make('sip_auth_secret')
                            ->label('Auth password')
                            ->password()
                            ->revealable()
                            ->maxLength(64)
                            ->autocomplete(false)
                            ->extraInputAttributes([
                                'autocomplete' => 'off',
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                                'data-bwignore' => 'true',
                            ])
                            ->helperText('Carrier SIP auth secret (not a login password). Leave blank on edit to keep the existing value.'),
                        Forms\Components\TextInput::make('expiry')
                            ->label('Expiry (seconds)')
                            ->numeric()
                            ->nullable()
                            ->placeholder('3600')
                            ->helperText('Match carrier requirements; blank uses module default.'),
                        Forms\Components\Select::make('state')
                            ->label('State')
                            ->options([
                                0 => 'Enabled',
                                1 => 'Disabled',
                            ])
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Advanced')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('proxy')
                            ->label('Outbound proxy')
                            ->maxLength(255)
                            ->nullable()
                            ->placeholder('sip:proxy.example.com:5060')
                            ->helperText('Optional SIP URI; leave blank if unused.'),
                        Forms\Components\TextInput::make('binding_params')
                            ->label('Contact params')
                            ->maxLength(64)
                            ->nullable()
                            ->placeholder(';reg-id=1')
                            ->helperText('Must start with “;” if set.'),
                        Forms\Components\TextInput::make('forced_socket')
                            ->label('Forced socket')
                            ->maxLength(64)
                            ->nullable()
                            ->helperText('Must match a listen socket in opensips.cfg if set.'),
                        Forms\Components\TextInput::make('third_party_registrant')
                            ->label('Third-party registrant (From URI)')
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('Optional; blank = From matches To (AOR).'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('aor')
                    ->label('AOR')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('registrar')
                    ->label('Registrar')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('binding_URI')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('expiry')
                    ->label('Expiry')
                    ->placeholder('default')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        0 => 'Enabled',
                        1 => 'Disabled',
                        default => (string) $state,
                    })
                    ->color(fn ($state) => match ((int) $state) {
                        0 => 'success',
                        1 => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('aor')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        app(OpenSIPSMIService::class)->regReload();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrants::route('/'),
            'create' => Pages\CreateRegistrant::route('/create'),
            'edit' => Pages\EditRegistrant::route('/{record}/edit'),
        ];
    }
}
