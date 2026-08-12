<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CdrResource;
use App\Services\HomeDashboardMetrics;
use App\Support\SiteTimezone;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CallOutcomeChartWidget extends ChartWidget
{
    protected static string $view = 'filament.widgets.chart-widget-rescale';

    protected static ?string $heading = 'Call outcome mix (today)';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '240px';

    protected static ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = 1;

    /**
     * @var array{timezone: string, answered: int, no_answer: int, busy: int, other: int}|null
     */
    private ?array $outcomeSnapshot = null;

    public function getHeading(): string | Htmlable | null
    {
        $tz = $this->outcomeCounts()['timezone'] ?: SiteTimezone::id();

        return 'Call outcome mix (today · '.$tz.')';
    }

    public function getDescription(): string | Htmlable | null
    {
        $url = e(CdrResource::getUrl('index'));
        $o = $this->outcomeCounts();
        $total = $o['answered'] + $o['no_answer'] + $o['busy'] + $o['other'];

        if ($total === 0) {
            $summary = 'No calls today. ';
        } else {
            $summary = sprintf(
                '%d answered · %d no-answer · %d busy · %d other. ',
                $o['answered'],
                $o['no_answer'],
                $o['busy'],
                $o['other'],
            );
        }

        return new HtmlString(
            $summary
            . '<a href="' . $url . '" class="font-medium text-primary-600 hover:underline">Open CDR →</a>'
        );
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * Parent memoizes getData() on $cachedData for the request; clear so poll
     * re-reads acc after new calls land.
     */
    public function updateChartData(): void
    {
        $this->cachedData = null;
        $this->outcomeSnapshot = null;

        parent::updateChartData();
    }

    protected function getData(): array
    {
        $o = $this->outcomeCounts();

        $labels = [];
        $data = [];
        $colors = [];

        $slices = [
            ['Answered', $o['answered'], '#16a34a'],
            ['No answer / cancel', $o['no_answer'], '#ca8a04'],
            ['Busy / reject', $o['busy'], '#ea580c'],
            ['Error / other', $o['other'], '#64748b'],
        ];

        foreach ($slices as [$label, $value, $color]) {
            if ($value <= 0) {
                continue;
            }
            // Chart.js legend omits values by default — bake counts into labels.
            $labels[] = sprintf('%s (%d)', $label, $value);
            $data[] = $value;
            $colors[] = $color;
        }

        if ($data === []) {
            $labels = ['No calls today'];
            $data = [1];
            $colors = ['#e2e8f0'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Parent view sets a fixed height box; fill it without aspect-ratio growth.
            'maintainAspectRatio' => false,
            // Filament’s chart Alpine always seeds x/y scales (line defaults) — hide on doughnut.
            'scales' => [
                'x' => [
                    'display' => false,
                ],
                'y' => [
                    'display' => false,
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

    /**
     * @return array{timezone: string, answered: int, no_answer: int, busy: int, other: int}
     */
    private function outcomeCounts(): array
    {
        return $this->outcomeSnapshot ??= app(HomeDashboardMetrics::class)->callOutcomeToday();
    }
}
