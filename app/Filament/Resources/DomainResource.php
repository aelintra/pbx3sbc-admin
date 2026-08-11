<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DomainResource\Pages;
use App\Models\Domain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $navigationIcon = 'lucide-globe';

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden - use Domain Routes instead
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Forms\Components\TextInput::make('setid')
                    ->required()
                    ->rules(['integer', 'min:0'])
                    ->default(0)
                    ->label('Set ID'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('setid')
                    ->sortable()
                    ->label('Set ID'),
                Tables\Columns\TextColumn::make('last_modified')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Modified'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('setid')
                    ->label('Set ID'),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Domain $record): bool => ! \App\Services\FleetDomainOwnership::isFleetOwned($record->attrs)
            )
            ->actions([
                Tables\Actions\EditAction::make()
                    ->authorize(fn (Domain $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn (Domain $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if (\App\Services\FleetDomainOwnership::isFleetOwned($record->attrs)) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Fleet-owned domains cannot be deleted here')
                                        ->body('Use Fleet Delete. Magrathea must not delete fleet=domain rows.')
                                        ->danger()
                                        ->send();
                                    throw new \Filament\Support\Exceptions\Halt;
                                }
                            }
                        }),
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
            'index' => Pages\ListDomains::route('/'),
            'create' => Pages\CreateDomain::route('/create'),
            'edit' => Pages\EditDomain::route('/{record}/edit'),
        ];
    }
}
