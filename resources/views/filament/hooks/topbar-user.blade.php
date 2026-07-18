@php
    $user = filament()->auth()->user();
    $userName = $user ? filament()->getUserName($user) : null;
    $logoutUrl = filament()->getLogoutUrl();
    $instanceFqdn = parse_url((string) config('app.url'), PHP_URL_HOST)
        ?: request()->getHost();
@endphp

{{-- SPA kinship: centered Instance chip + Logged in as / Logout / theme --}}
@if ($instanceFqdn)
    <div class="pbx-topbar-center" role="group" aria-label="Connected PBX context">
        <span class="pbx-context-chip" title="SBC instance FQDN (APP_URL)">
            <span class="pbx-context-chip-k">Instance</span>
            <span class="pbx-context-chip-v">{{ $instanceFqdn }}</span>
        </span>
    </div>
@endif

@if ($userName)
    <div class="pbx-topbar-user ms-auto flex items-center gap-x-4">
        <span class="pbx-topbar-user-label truncate">
            Logged in as {{ $userName }}
        </span>
        <form method="POST" action="{{ $logoutUrl }}" class="pbx-topbar-logout-form shrink-0">
            @csrf
            <button type="submit" class="pbx-topbar-logout-btn">
                Logout
            </button>
        </form>
        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <div class="pbx-topbar-theme shrink-0">
                <x-filament-panels::theme-switcher />
            </div>
        @endif
    </div>
@endif
