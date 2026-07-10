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

    protected static ?string $navigationLabel = 'Gateways';

    protected static ?string $navigationGroup = 'Peering';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Gateway';

    protected static ?string $pluralModelLabel = 'Gateways';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('gwid')
                    ->label('Gateway ID (gwid)')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Referenced by routing rules gwlist (e.g. 1, 10).'),
                Forms\Components\TextInput::make('address')
                    ->label('Address (SIP URI)')
                    ->required()
                    ->maxLength(128)
                    ->placeholder('sip:87.238.72.129:5060')
                    ->rules(['regex:/^sip:.+/'])
                    ->validationMessages([
                        'regex' => 'Address must be a SIP URI starting with sip:',
                    ])
                    ->helperText('Carrier signaling IP (is_from_gw) or Asterisk destination.'),
                Forms\Components\TextInput::make('description')
                    ->maxLength(128)
                    ->columnSpanFull(),
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
                Forms\Components\Select::make('state')
                    ->options([
                        0 => 'Enabled',
                        1 => 'Disabled',
                        2 => 'Temp disabled',
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
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gwid')
                    ->label('gwid')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->copyable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap()
                    ->limit(40),
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
                Tables\Columns\TextColumn::make('probe_mode')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('gwid')
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
                                ->title('Cannot delete gateway')
                                ->body("gwid {$gwid} is referenced by one or more routing rules.")
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
