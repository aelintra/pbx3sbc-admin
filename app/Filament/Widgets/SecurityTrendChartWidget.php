<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DoorKnockAttemptResource;
use App\Filament\Resources\FailedRegistrationResource;
use App\Models\DoorKnockAttempt;
use App\Models\FailedRegistration;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class SecurityTrendChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Security trend (last 7 days)';

    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '200px';

    protected static ?string $pollingInterval = '120s';

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
        $start = Carbon::now()->subDays(6)->startOfDay();

        $doorRows = DoorKnockAttempt::query()
            ->where('attempt_time', '>=', $start)
            ->selectRaw('DATE(attempt_time) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        $failRows = FailedRegistration::query()
            ->where('attempt_time', '>=', $start)
            ->selectRaw('DATE(attempt_time) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        $labels = [];
        $door = [];
        $fail = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format('D');
            $door[] = (int) ($doorRows[$key] ?? 0);
            $fail[] = (int) ($failRows[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Door-knock',
                    'data' => $door,
                    'backgroundColor' => '#ca8a04',
                ],
                [
                    'label' => 'Failed REGISTER',
                    'data' => $fail,
                    'backgroundColor' => '#dc2626',
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
