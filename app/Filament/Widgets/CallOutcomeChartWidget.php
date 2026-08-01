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
    protected static ?string $heading = 'Call outcome mix (today)';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '240px';

    protected static ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = 1;

    public function getDescription(): string | Htmlable | null
    {
        $url = e(CdrResource::getUrl('index'));
        $tz = e(SiteTimezone::id());

        return new HtmlString(
            'Disposition buckets since midnight <strong>'.$tz.'</strong>. '
            . '<a href="' . $url . '" class="font-medium text-primary-600 hover:underline">Open CDR →</a>'
        );
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $o = app(HomeDashboardMetrics::class)->callOutcomeToday();

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
            $labels[] = $label;
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
