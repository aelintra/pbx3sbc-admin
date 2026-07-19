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

    @once
        <script>
            (() => {
                const timeoutMs = {{ max(0.1, (float) config('panel.inactivity_timeout_minutes', 10)) * 60 * 1000 }};
                const logoutUrl = @js($logoutUrl);
                const loginUrl = @js(filament()->getLoginUrl());
                const csrfToken = @js(csrf_token());
                const activityEvents = ['pointerdown', 'keydown', 'scroll', 'touchstart', 'wheel'];
                const activityStorageKey = 'pbx3sbc-admin.lastActivityAt';
                let timerId = null;
                let deadline = Date.now() + timeoutMs;
                let loggingOut = false;
                let lastBroadcastAt = 0;

                const logout = () => {
                    if (loggingOut) return;
                    loggingOut = true;
                    window.clearTimeout(timerId);

                    const body = new URLSearchParams({ _token: csrfToken });
                    fetch(logoutUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    }).finally(() => window.location.replace(loginUrl));
                };

                const expireIfIdle = () => {
                    window.clearTimeout(timerId);
                    let sharedActivityAt = 0;
                    try {
                        sharedActivityAt = Number(localStorage.getItem(activityStorageKey));
                    } catch {
                        // Storage may be unavailable in private mode.
                    }
                    if (Number.isFinite(sharedActivityAt)) {
                        deadline = Math.max(deadline, sharedActivityAt + timeoutMs);
                    }
                    const remaining = deadline - Date.now();
                    if (remaining > 0) {
                        timerId = window.setTimeout(expireIfIdle, remaining);
                        return;
                    }
                    logout();
                };

                const resetTimer = () => {
                    if (loggingOut) return;
                    window.clearTimeout(timerId);
                    const now = Date.now();
                    deadline = now + timeoutMs;
                    timerId = window.setTimeout(expireIfIdle, timeoutMs);
                    if (now - lastBroadcastAt >= 1000) {
                        lastBroadcastAt = now;
                        try {
                            localStorage.setItem(activityStorageKey, String(now));
                        } catch {
                            // Storage may be unavailable in private mode.
                        }
                    }
                };

                for (const event of activityEvents) {
                    window.addEventListener(event, resetTimer, { passive: true });
                }
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') expireIfIdle();
                });
                window.addEventListener('storage', (event) => {
                    if (event.key !== activityStorageKey) return;
                    const activityAt = Number(event.newValue);
                    if (!Number.isFinite(activityAt)) return;
                    window.clearTimeout(timerId);
                    deadline = activityAt + timeoutMs;
                    timerId = window.setTimeout(expireIfIdle, Math.max(0, deadline - Date.now()));
                });
                resetTimer();
            })();
        </script>
    @endonce
@endif
