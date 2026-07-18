@php
    $user = filament()->auth()->user();
    $userName = $user ? filament()->getUserName($user) : null;
    $logoutUrl = filament()->getLogoutUrl();
@endphp

{{-- SPA kinship: inline Logged in as + Logout (no avatar dropdown) --}}
@if ($userName)
    <div class="pbx-topbar-user ms-auto flex items-center gap-x-4">
        <span class="pbx-topbar-user-label truncate text-sm text-gray-500 dark:text-gray-400">
            Logged in as {{ $userName }}
        </span>
        <form method="POST" action="{{ $logoutUrl }}" class="pbx-topbar-logout-form shrink-0">
            @csrf
            <button type="submit" class="pbx-topbar-logout-btn">
                Logout
            </button>
        </form>
    </div>
@endif
