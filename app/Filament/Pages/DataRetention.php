<?php

namespace App\Filament\Pages;

use App\Services\Retention\RetentionSettingsService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DataRetention extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'lucide-database';

    protected static string $view = 'filament.pages.data-retention';

    protected static ?string $navigationLabel = 'Data retention';

    protected static ?string $title = 'Data retention';

    protected static ?string $navigationGroup = 'Logs';

    protected static ?int $navigationSort = 50;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $status = null;

    public string $overridePath = '';

    public bool $hasOverride = false;

    public function mount(): void
    {
        $this->refreshFromService();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Local retention (days)')
                    ->description('Applies to the next daily cron purge. Does not delete immediately. Destructive purge is cron/CLI only.')
                    ->schema([
                        TextInput::make('security_events_days')
                            ->label('Security events (door-knock + failed registrations)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->helperText('Default 30'),
                        TextInput::make('acc_days')
                            ->label('Edge CDR (acc)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->helperText('Default 90 — edge ops only; not product call history'),
                        TextInput::make('batch_size')
                            ->label('Delete batch size')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(10000)
                            ->helperText('Rows per DELETE; default 1000'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        try {
            $settings = app(RetentionSettingsService::class);
            $settings->put([
                'security_events_days' => (int) $state['security_events_days'],
                'acc_days' => (int) $state['acc_days'],
                'batch_size' => (int) $state['batch_size'],
            ]);
            $this->refreshFromService();
            Notification::make()
                ->title('Retention settings saved')
                ->body('Next cron run will use these values.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not save retention settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshStatus(): void
    {
        $this->refreshFromService();
        Notification::make()
            ->title('Status refreshed')
            ->success()
            ->send();
    }

    private function refreshFromService(): void
    {
        $settings = app(RetentionSettingsService::class);
        $info = $settings->get();
        $this->data = [
            'security_events_days' => $info['security_events_days'],
            'acc_days' => $info['acc_days'],
            'batch_size' => $info['batch_size'],
        ];
        $this->form->fill($this->data);
        $this->status = $info['last_purge'];
        $this->overridePath = $info['override_path'];
        $this->hasOverride = $info['has_override'];
    }
}
