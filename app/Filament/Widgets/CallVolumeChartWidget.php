<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CdrResource;
use App\Services\HomeDashboardMetrics;
use App\Support\SiteTimezone;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CallVolumeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Edge call volume (last 24h)';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '240px';

    protected static ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = 1;

    public function getDescription(): string | Htmlable | null
    {
        $url = e(CdrResource::getUrl('index'));
        $tz = e(SiteTimezone::id());

        return new HtmlString(
            'Hourly INVITE outcomes from OpenSIPS acc (hour labels <strong>'.$tz.'</strong>). '
            . '<a href="' . $url . '" class="font-medium text-primary-600 hover:underline">Open CDR →</a>'
        );
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = app(HomeDashboardMetrics::class)->callVolumeLast24h();

        return [
            'datasets' => [
                [
                    'label' => 'Completed (200)',
                    'data' => $data['completed'],
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Failed / other',
                    'data' => $data['failed'],
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
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
}
