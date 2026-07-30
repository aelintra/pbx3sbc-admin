<?php

namespace App\Filament\Widgets;

use App\Models\Cdr;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CallOutcomeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Call outcome mix (today)';

    protected static ?string $description = 'Disposition buckets from today’s edge CDR';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '240px';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $todayStart = Carbon::now()->startOfDay();

        $rows = Cdr::query()
            ->where('created', '>=', $todayStart)
            ->selectRaw('sip_code, COUNT(*) as c')
            ->groupBy('sip_code')
            ->pluck('c', 'sip_code');

        $answered = 0;
        $noAnswer = 0;
        $busy = 0;
        $other = 0;

        foreach ($rows as $code => $count) {
            $count = (int) $count;
            $code = (int) $code;

            if ($code === 200) {
                $answered += $count;
            } elseif (in_array($code, [486, 600, 603], true)) {
                $busy += $count;
            } elseif (in_array($code, [408, 480, 487, 484, 404], true)) {
                $noAnswer += $count;
            } else {
                $other += $count;
            }
        }

        $labels = [];
        $data = [];
        $colors = [];

        $slices = [
            ['Answered', $answered, '#16a34a'],
            ['No answer / cancel', $noAnswer, '#ca8a04'],
            ['Busy / reject', $busy, '#ea580c'],
            ['Error / other', $other, '#64748b'],
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
