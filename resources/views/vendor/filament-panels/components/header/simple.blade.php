@props([
    'heading' => null,
    'logo' => true,
    'subheading' => null,
])

{{-- SPA LoginView kinship: left-aligned title (Filament default was centered). --}}
<header class="fi-simple-header flex flex-col items-start">
    @if ($logo)
        <x-filament-panels::logo class="mb-4" />
    @endif

    @if (filled($heading))
        <h1
            class="fi-simple-header-heading text-left text-2xl font-semibold tracking-normal text-gray-950 dark:text-white"
        >
            {{ $heading }}
        </h1>
    @endif

    @if (filled($subheading))
        <p
            class="fi-simple-header-subheading mt-2 text-left text-sm text-gray-500 dark:text-gray-400"
        >
            {{ $subheading }}
        </p>
    @endif
</header>
