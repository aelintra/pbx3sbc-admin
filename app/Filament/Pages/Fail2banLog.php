<?php

namespace App\Filament\Pages;

use App\Services\Fail2banService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Fail2banLog extends Page
{
    protected static ?string $navigationIcon = 'lucide-file-text';

    protected static string $view = 'filament.pages.fail2ban-log';

    protected static ?string $navigationLabel = 'Fail2ban log';

    protected static ?string $title = 'Fail2ban log';

    protected static ?string $navigationGroup = 'Logs';

    protected static ?int $navigationSort = 40;

    /** @var list<string> */
    public array $lines = [];

    public int $lineCount = 0;

    public int $linesRequested = 200;

    public string $logPath = '/var/log/fail2ban.log';

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadLog();
    }

    public function loadLog(): void
    {
        $service = app(Fail2banService::class);
        $result = $service->getLogTail($this->linesRequested);

        $this->lines = $result['lines'];
        $this->lineCount = $result['line_count'];
        $this->logPath = $result['path'];
        $this->error = $result['error'];

        if ($this->error) {
            Notification::make()
                ->title('Could not read Fail2ban log')
                ->body($this->error)
                ->danger()
                ->send();
        }
    }

    public function updatedLinesRequested(): void
    {
        $this->linesRequested = max(1, min(2000, (int) $this->linesRequested));
        $this->loadLog();
    }
}
