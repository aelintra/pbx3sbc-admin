<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DbAliasResource\Pages;
use App\Models\DbAlias;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DbAliasResource extends Resource
{
    protected static ?string $model = DbAlias::class;

    protected static ?string $navigationIcon = 'heroicon-o-at-symbol';

    protected static ?string $navigationLabel = 'DID aliases';

    protected static ?string $navigationGroup = 'Peering';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'DID alias';

    protected static ?string $pluralModelLabel = 'DID aliases';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('One-off inbound DID')
                    ->description('Used when the DID is not in a Number route prefix (Phase 5). Carrier INVITE user part matches Alias DID; call is rewritten to Target user@domain and sent via that domain’s dispatcher set.')
                    ->schema([
                        Forms\Components\TextInput::make('alias_username')
                            ->label('Alias DID')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('e.g. 01924918076')
                            ->helperText('Matched on SIP user only (OpenSIPS flag “d”).'),
                        Forms\Components\TextInput::make('alias_domain')
                            ->label('Alias domain (optional)')
                            ->maxLength(64)
                            ->default('')
                            ->dehydrated()
                            ->helperText('Usually leave blank — inbound lookup ignores carrier RURI domain.'),
                        Forms\Components\TextInput::make('username')
                            ->label('Target user')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('e.g. 1000 or same DID')
                            ->helperText('Extension, or keep the DID if Asterisk inroutes should match it.'),
                        Forms\Components\Select::make('domain')
                            ->label('Target domain')
                            ->required()
                            ->searchable()
                            ->options(fn () => Domain::query()->orderBy('domain')->pluck('domain', 'domain')->all())
                            ->helperText('Must exist under Domains (setid → Asterisk).'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('alias_username')
                    ->label('Alias DID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('alias_domain')
                    ->label('Alias domain')
                    ->placeholder('(any)')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('username')
                    ->label('Target user')
                    ->searchable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label('Target domain')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('alias_username')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDbAliases::route('/'),
            'create' => Pages\CreateDbAlias::route('/create'),
            'edit' => Pages\EditDbAlias::route('/{record}/edit'),
        ];
    }
}
