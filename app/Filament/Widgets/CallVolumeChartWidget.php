<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CdrResource;
use App\Models\Cdr;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class CallVolumeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Edge call volume (last 24h)';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '240px';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    public function getDescription(): string | Htmlable | null
    {
        $url = e(CdrResource::getUrl('index'));

        return new HtmlString(
            'Hourly INVITE outcomes from OpenSIPS acc — edge ops only. '
            . '<a href="' . $url . '" class="font-medium text-primary-600 hover:underline">Open CDR →</a>'
        );
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $now = Carbon::now()->startOfHour();
        $start = $now->copy()->subHours(23);

        $rows = Cdr::query()
            ->where('created', '>=', $start)
            ->selectRaw("DATE_FORMAT(created, '%Y-%m-%d %H:00:00') as bucket")
            ->selectRaw('SUM(CASE WHEN sip_code = 200 THEN 1 ELSE 0 END) as completed')
            ->selectRaw('SUM(CASE WHEN sip_code != 200 OR sip_code IS NULL THEN 1 ELSE 0 END) as failed')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $labels = [];
        $completed = [];
        $failed = [];

        for ($i = 0; $i < 24; $i++) {
            $hour = $start->copy()->addHours($i);
            $key = $hour->format('Y-m-d H:00:00');
            $labels[] = $hour->format('H:00');
            $row = $rows->get($key);
            $completed[] = (int) ($row->completed ?? 0);
            $failed[] = (int) ($row->failed ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Completed (200)',
                    'data' => $completed,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Failed / other',
                    'data' => $failed,
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
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
