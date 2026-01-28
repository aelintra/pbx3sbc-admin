<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Fail2banWhitelistResource\Pages;
use App\Models\Fail2banWhitelist;
use App\Services\WhitelistSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

class Fail2banWhitelistResource extends Resource
{
    protected static ?string $model = Fail2banWhitelist::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Fail2Ban Whitelist';

    protected static ?string $modelLabel = 'Whitelist Entry';

    protected static ?string $pluralModelLabel = 'Whitelist Entries';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip_or_cidr')
                    ->label('IP Address or CIDR Range')
                    ->placeholder('192.168.1.100 or 192.168.1.0/24')
                    ->required()
                    ->maxLength(45)
                    ->unique(ignoreRecord: true)
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                // Validate IP or CIDR format
                                if (!preg_match('/^([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(\/([0-9]|[1-2][0-9]|3[0-2]))?$/', $value)) {
                                    // Try IPv6
                                    if (!preg_match('/^([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]{0,4}(\/[0-9]{1,3})?$/', $value) && $value !== '::1') {
                                        $fail('The :attribute must be a valid IPv4/IPv6 address or CIDR range (e.g., 192.168.1.100 or 192.168.1.0/24).');
                                    }
                                }
                            };
                        },
                    ])
                    ->helperText('Enter an IP address (e.g., 192.168.1.100) or CIDR range (e.g., 192.168.1.0/24)'),
                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->placeholder('Description of this whitelist entry (e.g., "Customer A office")')
                    ->maxLength(255)
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_or_cidr')
                    ->label('IP/CIDR')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('success'),
                TextColumn::make('comment')
                    ->label('Comment')
                    ->searchable()
                    ->wrap()
                    ->limit(50),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->default('System'),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Fail2banWhitelist $record) {
                        // Sync after deletion
                        app(WhitelistSyncService::class)->sync();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function () {
                            // Sync after bulk deletion
                            app(WhitelistSyncService::class)->sync();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListFail2banWhitelists::route('/'),
            'create' => Pages\CreateFail2banWhitelist::route('/create'),
            'view' => Pages\ViewFail2banWhitelist::route('/{record}'),
            'edit' => Pages\EditFail2banWhitelist::route('/{record}/edit'),
        ];
    }
}
