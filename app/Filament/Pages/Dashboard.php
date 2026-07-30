<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CallOutcomeChartWidget;
use App\Filament\Widgets\CallVolumeChartWidget;
use App\Filament\Widgets\LivePostureWidget;
use App\Filament\Widgets\SecurityPulseWidget;
use App\Filament\Widgets\SecurityTrendChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    protected static ?string $navigationIcon = 'lucide-home';

    public function getTitle(): string | Htmlable
    {
        $fqdn = parse_url((string) config('app.url'), PHP_URL_HOST)
            ?: request()->getHost();

        return $fqdn ? "Home — {$fqdn}" : 'Home';
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
            LivePostureWidget::class,
            CallVolumeChartWidget::class,
            CallOutcomeChartWidget::class,
            SecurityPulseWidget::class,
            SecurityTrendChartWidget::class,
        ];
    }
}
