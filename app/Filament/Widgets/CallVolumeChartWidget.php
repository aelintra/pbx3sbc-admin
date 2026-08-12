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
    protected static string $view = 'filament.widgets.chart-widget-rescale';

    protected static ?string $heading = 'Edge call volume (last 24h)';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '240px';

    protected static ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = 1;

    /**
     * @var array{timezone: string, labels: list<string>, completed: list<int>, failed: list<int>}|null
     */
    private ?array $volumeSnapshot = null;

    public function getDescription(): string | Htmlable | null
    {
        $url = e(CdrResource::getUrl('index'));
        $v = $this->volumeCounts();
        $tz = e($v['timezone'] ?: SiteTimezone::id());
        $peak = $this->peakCount($v);

        return new HtmlString(
            'Hourly INVITE outcomes from OpenSIPS acc (hour labels <strong>'.$tz.'</strong>'
            . ($peak > 0 ? ', peak <strong>'.$peak.'/h</strong>' : '')
            . '). '
            . '<a href="' . $url . '" class="font-medium text-primary-600 hover:underline">Open CDR →</a>'
        );
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function updateChartData(): void
    {
        $this->cachedData = null;
        $this->volumeSnapshot = null;

        parent::updateChartData();
    }

    protected function getData(): array
    {
        $data = $this->volumeCounts();

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
        $peak = $this->peakCount($this->volumeCounts());
        // Floor keeps a handful of calls from looking like a 0–1 binary chart.
        $suggestedMax = max(4, (int) ceil($peak * 1.25));

        $y = [
            'beginAtZero' => true,
            'suggestedMax' => $suggestedMax,
            'ticks' => [
                'precision' => 0,
            ],
        ];
        if ($suggestedMax <= 12) {
            $y['ticks']['stepSize'] = 1;
        }

        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => $y,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    /**
     * @return array{timezone: string, labels: list<string>, completed: list<int>, failed: list<int>}
     */
    private function volumeCounts(): array
    {
        return $this->volumeSnapshot ??= app(HomeDashboardMetrics::class)->callVolumeLast24h();
    }

    /**
     * @param  array{completed: list<int>, failed: list<int>}  $v
     */
    private function peakCount(array $v): int
    {
        $completed = $v['completed'] ?? [];
        $failed = $v['failed'] ?? [];

        return (int) max(0, max($completed ?: [0]), max($failed ?: [0]));
    }
}
