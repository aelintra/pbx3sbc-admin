<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DoorKnockAttemptResource;
use App\Filament\Resources\FailedRegistrationResource;
use App\Services\HomeDashboardMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class SecurityTrendChartWidget extends ChartWidget
{
    protected static string $view = 'filament.widgets.chart-widget-rescale';

    protected static ?string $heading = 'Security trend (last 7 days)';

    protected static ?int $sort = 6;

    protected static ?string $maxHeight = '200px';

    /** Heaviest query (door_knock 7d); slow poll + long cache. */
    protected static ?string $pollingInterval = '300s';

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): string | Htmlable | null
    {
        $doorUrl = e(DoorKnockAttemptResource::getUrl('index'));
        $failUrl = e(FailedRegistrationResource::getUrl('index'));

        return new HtmlString(
            'Daily door-knock and failed REGISTER counts. '
            . '<a href="' . $doorUrl . '" class="font-medium text-primary-600 hover:underline">Door-knock →</a>'
            . ' · '
            . '<a href="' . $failUrl . '" class="font-medium text-primary-600 hover:underline">Failed REGISTER →</a>'
        );
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = app(HomeDashboardMetrics::class)->securityTrend7d();

        return [
            'datasets' => [
                [
                    'label' => 'Door-knock',
                    'data' => $data['door'],
                    'backgroundColor' => '#ca8a04',
                ],
                [
                    'label' => 'Failed REGISTER',
                    'data' => $data['fail'],
                    'backgroundColor' => '#dc2626',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            // chart-widget-rescale uses a fixed-height host; fill it (do not keep
            // aspectRatio or a full-width bar chart grows taller than the card).
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    public function updateChartData(): void
    {
        $this->cachedData = null;

        parent::updateChartData();
    }
}
