<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DispatcherResource\Pages;
use App\Models\Dispatcher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DispatcherResource extends Resource
{
    protected static ?string $model = Dispatcher::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('setid')
                    ->required()
                    ->rules(['integer', 'min:0'])
                    ->default(0)
                    ->label('Set ID'),
                Forms\Components\TextInput::make('destination')
                    ->required()
                    ->maxLength(192)
                    ->rules([
                        'regex:/^sip:((\[([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\]|([0-9]{1,3}\.){3}[0-9]{1,3}|([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}))(:[0-9]{1,5})?$/',
                    ])
                    ->validationMessages([
                        'regex' => 'The destination must start with "sip:" followed by an IP address or domain name (e.g., sip:10.0.1.10:5060 or sip:example.com:5060).',
                    ])
                    ->label('Destination (SIP URI)')
                    ->placeholder('sip:10.0.1.10:5060')
                    ->helperText('Format: sip:host:port (host can be IP address or domain name)'),
                Forms\Components\Select::make('state')
                    ->required()
                    ->options([
                        0 => 'Active',
                        1 => 'Inactive',
                    ])
                    ->default(0)
                    ->label('State'),
                Forms\Components\Select::make('probe_mode')
                    ->options([
                        0 => 'No probing',
                        1 => 'Probe when disabled (auto re-enable)',
                        2 => 'Continuous probing',
                    ])
                    ->default(0)
                    ->label('Probe Mode'),
                Forms\Components\TextInput::make('weight')
                    ->rules(['integer', 'min:0'])
                    ->default(1)
                    ->label('Weight'),
                Forms\Components\TextInput::make('priority')
                    ->rules(['integer', 'min:0'])
                    ->default(0)
                    ->label('Priority'),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(64)
                    ->label('Description'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('setid')
                    ->sortable()
                    ->label('Set ID'),
                Tables\Columns\TextColumn::make('destination')
                    ->searchable()
                    ->sortable()
                    ->label('Destination'),
                Tables\Columns\TextColumn::make('state')
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'Active' : 'Inactive')
                    ->badge()
                    ->color(fn ($state) => $state == 0 ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('setid')
                    ->label('Set ID'),
                Tables\Filters\SelectFilter::make('state')
                    ->options([
                        0 => 'Active',
                        1 => 'Inactive',
                    ])
                    ->label('State'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListDispatchers::route('/'),
            'create' => Pages\CreateDispatcher::route('/create'),
            'edit' => Pages\EditDispatcher::route('/{record}/edit'),
        ];
    }
}
