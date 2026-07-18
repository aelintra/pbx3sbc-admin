@php
    /** @var \Filament\Pages\Page $this */
@endphp

<div class="pbx-panel-back-header flex flex-col gap-3">
    <a
        href="{{ $backUrl }}"
        class="pbx-panel-back-link"
        wire:navigate
    >
        ← {{ $backLabel }}
    </a>

    <x-filament-panels::header
        :actions="$actions"
        :breadcrumbs="[]"
        :heading="$heading"
        :subheading="$subheading"
    >
        @if ($heading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="heading">
                {{ $heading }}
            </x-slot>
        @endif

        @if ($subheading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="subheading">
                {{ $subheading }}
            </x-slot>
        @endif
    </x-filament-panels::header>
</div>
