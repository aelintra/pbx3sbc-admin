<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CallOutcomeChartWidget;
use App\Filament\Widgets\CallVolumeChartWidget;
use App\Filament\Widgets\LivePostureWidget;
use App\Filament\Widgets\SecurityPulseWidget;
use App\Filament\Widgets\SecurityTrendChartWidget;
use App\Filament\Widgets\SystemPostureWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    protected static ?string $navigationIcon = 'lucide-home';

    public function getTitle(): string | Htmlable
    {
        // FQDN stays on the INSTANCE chip; avoid repeating it in the page title (SPA kinship: Home — {sitename}).
        return 'Home';
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            SystemPostureWidget::class,
            LivePostureWidget::class,
            CallVolumeChartWidget::class,
            CallOutcomeChartWidget::class,
            SecurityPulseWidget::class,
            SecurityTrendChartWidget::class,
        ];
    }
}
