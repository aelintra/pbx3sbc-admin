<?php

namespace App\Filament\Pages;

use App\Services\DialogTimeoutReader;
use Filament\Pages\Page;

/**
 * Read-only edge call limits from opensips.cfg (not editable here).
 */
class CallLimits extends Page
{
    protected static ?string $navigationIcon = 'lucide-timer';

    protected static string $view = 'filament.pages.call-limits';

    protected static ?string $navigationLabel = 'Call limits';

    protected static ?string $title = 'Call limits';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 15;

    public int $dialogTimeoutSeconds = DialogTimeoutReader::OPENSIPS_DEFAULT_SECONDS;

    public string $dialogTimeoutHuman = '';

    public string $source = 'opensips_default';

    public string $cfgPath = '';

    public string $loadError = '';

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $info = app(DialogTimeoutReader::class)->read();
        $this->dialogTimeoutSeconds = $info['seconds'];
        $this->dialogTimeoutHuman = $info['human'];
        $this->source = $info['source'];
        $this->cfgPath = $info['cfg_path'];
        $this->loadError = $info['error'] ?? '';
    }
}
