@php
    use App\Services\GeoIpLookupService;

    /** @var GeoIpLookupService $geoService */
    $geoService = app(GeoIpLookupService::class);
    $geo = ($sourceIp ?? null) ? $geoService->lookup((string) $sourceIp) : null;

    $hasCoords = is_array($geo)
        && $geo['status'] === 'success'
        && $geo['lat'] !== null
        && $geo['lon'] !== null;

    $locationParts = [];
    if (is_array($geo) && ($geo['status'] ?? '') === 'success') {
        foreach (['city', 'region', 'country'] as $part) {
            if (! empty($geo[$part])) {
                $locationParts[] = $geo[$part];
            }
        }
    }
@endphp

<div class="space-y-4">
    @if ($geo === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">Location unavailable (lookup failed or timed out).</p>
    @elseif (($geo['status'] ?? '') === 'fail')
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if (! empty($geo['message']))
                Location unavailable: {{ $geo['message'] }}
            @else
                Location unavailable
            @endif
        </p>
    @else
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Location</dt>
                <dd class="text-gray-950 dark:text-white">
                    {{ $locationParts !== [] ? implode(', ', $locationParts) : '—' }}
                    @if (! empty($geo['countryCode']))
                        <span class="text-gray-500">({{ $geo['countryCode'] }})</span>
                    @endif
                </dd>
            </div>
            @if (! empty($geo['isp']))
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">ISP</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $geo['isp'] }}</dd>
                </div>
            @endif
            @if (! empty($geo['org']) && ($geo['org'] !== ($geo['isp'] ?? null)))
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Org</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $geo['org'] }}</dd>
                </div>
            @endif
        </dl>

        @if ($hasCoords)
            @php
                $mapUrl = $geoService->embedMapUrl((float) $geo['lat'], (float) $geo['lon']);
                $osmUrl = $geoService->openStreetMapUrl((float) $geo['lat'], (float) $geo['lon']);
                $mapLabel = implode(', ', $locationParts) ?: 'source IP';
            @endphp
            <div class="inline-block overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <iframe
                    title="Map of {{ $mapLabel }}"
                    src="{{ $mapUrl }}"
                    width="320"
                    height="320"
                    class="block h-80 w-80 bg-gray-100 dark:bg-gray-800"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                ></iframe>
            </div>
            <p class="text-sm">
                <a
                    href="{{ $osmUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary-600 hover:underline dark:text-primary-400"
                >
                    Open in OpenStreetMap
                </a>
                <span class="text-gray-400 dark:text-gray-500">
                    ({{ number_format((float) $geo['lat'], 4) }}, {{ number_format((float) $geo['lon'], 4) }})
                </span>
            </p>
        @endif
    @endif
</div>
