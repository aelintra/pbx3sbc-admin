{{-- SPA HomeHostStrip kinship: load / mem / disk with usage meters --}}
<div class="fi-wi-system-posture grid gap-3 sm:grid-cols-3">
    @foreach ($cards as $card)
        <div
            class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                {{ $card['label'] }}
            </div>
            <div class="mt-1 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $card['value'] }}
                </span>
                @if ($card['meta'] !== '')
                    <span class="text-xs font-medium text-gray-500">{{ $card['meta'] }}</span>
                @endif
            </div>
            @if ($card['meter_pct'] !== null)
                <div
                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200"
                    role="meter"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ (int) round($card['meter_pct']) }}"
                    aria-label="{{ $card['label'] }} {{ (int) round($card['meter_pct']) }}%"
                >
                    <div
                        class="h-full rounded-full transition-[width] duration-300"
                        style="width: {{ min(100, max(0, $card['meter_pct'])) }}%; background: {{ $card['meter_color'] }};"
                    ></div>
                </div>
            @endif
        </div>
    @endforeach
</div>
