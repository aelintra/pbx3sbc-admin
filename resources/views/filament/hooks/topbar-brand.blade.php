@php
    $homeUrl = filament()->getHomeUrl();
    $brandName = filament()->getBrandName();
@endphp

{{-- SPA kinship: mode label in topbar-left; PBX³ mark lives in sidebar header --}}
<div class="pbx-topbar-brand me-2 flex min-w-0 items-center">
    @if ($homeUrl)
        <a
            {{ \Filament\Support\generate_href_html($homeUrl) }}
            class="pbx-topbar-brand-link truncate text-[1.25rem] font-semibold leading-tight text-gray-950 no-underline hover:text-gray-950 dark:text-white dark:hover:text-white"
        >
            {{ $brandName }}
        </a>
    @else
        <span class="truncate text-[1.25rem] font-semibold leading-tight text-gray-950 dark:text-white">
            {{ $brandName }}
        </span>
    @endif
</div>
