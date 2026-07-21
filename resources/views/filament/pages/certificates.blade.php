{{-- SPA CertificatesView kinship (single FQDN; no tenant SAN sync). --}}
<x-filament-panels::page>
    <div class="certificates-view space-y-8">
        @if ($activeLabel)
            <p class="active-line text-base text-gray-800">
                <strong>Currently in use:</strong> {{ $activeLabel }}
            </p>
        @elseif ($loadError)
            <p class="text-sm text-danger-600">{{ $loadError }}</p>
        @endif

        {{-- Section 1: Let's Encrypt --}}
        <section class="cert-section space-y-3">
            <div class="section-header">
                <h2 class="text-xl font-bold text-slate-900">Let's Encrypt</h2>
            </div>
            <p class="section-explanation text-sm text-slate-600">
                A certificate for this host's hostname (e.g. <code class="rounded bg-slate-100 px-1 text-xs">{{ $leSetupFqdn ?: 'sbc.example.com' }}</code>)
                is issued and renewed via HTTP-01. Port <strong>80</strong> must be reachable from the internet
                during issuance or renewal (EC2 security group: TCP 80+443 world-open). No DNS API — just an
                A record for this host's FQDN.
            </p>
            <p class="section-help text-sm text-slate-600">
                <strong>Before getting a certificate:</strong> Create an A record for this hostname pointing
                to the stable public IP (EIP). Ensure port 80 can reach this server from the internet.
                <a
                    href="https://letsencrypt.org/docs/challenge-types/#http-01-challenge"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary-600 underline"
                >Learn about HTTP-01</a>.
            </p>

            @if ($leLoading)
                <p class="text-sm text-slate-500">Loading…</p>
            @elseif ($leError)
                <p class="text-sm text-danger-600">{{ $leError }}</p>
            @elseif (($leStatus['configured'] ?? false) === true)
                <dl class="cert-dl grid max-w-xl grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                    <dt class="font-semibold text-slate-700">Hostname</dt>
                    <dd>{{ $leStatus['domain'] ?? $leSetupFqdn }}</dd>
                    <dt class="font-semibold text-slate-700">Expires</dt>
                    <dd>{{ $leStatus['expires_at'] ?? '—' }}</dd>
                    <dt class="font-semibold text-slate-700">Issuer</dt>
                    <dd class="break-all">{{ $leStatus['issuer'] ?? '—' }}</dd>
                </dl>
                <p class="section-help text-sm text-slate-600">
                    <strong>Renew now</strong> extends expiry for this hostname. (Unlike the instance SPA,
                    there is no multi-tenant SAN sync on the SBC edge.)
                </p>
                <div class="section-actions flex flex-wrap gap-3">
                    <x-filament::button
                        wire:click="renewNow"
                        wire:loading.attr="disabled"
                        wire:target="renewNow"
                        :disabled="$renewing"
                        color="gray"
                    >
                        <span wire:loading.remove wire:target="renewNow">Renew now</span>
                        <span wire:loading wire:target="renewNow" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Renewing…
                        </span>
                    </x-filament::button>
                </div>
                <div
                    wire:loading.flex
                    wire:target="renewNow"
                    class="hidden items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
                    role="status"
                    aria-live="polite"
                >
                    <svg class="mt-0.5 h-5 w-5 shrink-0 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div>
                        <p class="font-semibold">Renewing certificate…</p>
                        <p class="mt-1 text-blue-800">Usually under a minute. Keep this tab open.</p>
                    </div>
                </div>
                @if ($renewMessage)
                    <p class="text-sm text-success-600">{{ $renewMessage }}</p>
                @endif
                @if ($renewErrorMessage)
                    <p class="text-sm text-danger-600">{{ $renewErrorMessage }}</p>
                @endif
                @if ($renewErrorDetail)
                    <pre class="overflow-x-auto rounded bg-slate-100 p-2 text-xs text-danger-700">{{ $renewErrorDetail }}</pre>
                @endif
            @else
                <p class="not-configured text-sm text-slate-600">
                    Enable Let's Encrypt with this admin hostname and an email for expiry notices.
                    The hostname is taken from <code class="rounded bg-slate-100 px-1 text-xs">APP_URL</code>
                    (set at install via <code class="rounded bg-slate-100 px-1 text-xs">--server-name</code>).
                </p>
                <div class="le-setup-form max-w-md space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Hostname (from APP_URL)</label>
                        <input
                            type="text"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm"
                            value="{{ $leSetupFqdn }}"
                            readonly
                            disabled
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Email (Let's Encrypt)</label>
                        <input
                            type="email"
                            wire:model="leSetupEmail"
                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm"
                            placeholder="admin@example.com"
                            wire:loading.attr="disabled"
                            wire:target="setupLetsEncrypt"
                            @disabled($settingUp)
                        />
                    </div>
                    <div class="section-actions">
                        <x-filament::button
                            wire:click="setupLetsEncrypt"
                            wire:loading.attr="disabled"
                            wire:target="setupLetsEncrypt"
                            :disabled="$settingUp || $leSetupEmail === ''"
                        >
                            <span wire:loading.remove wire:target="setupLetsEncrypt">Get certificate</span>
                            <span wire:loading wire:target="setupLetsEncrypt" class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Getting certificate…
                            </span>
                        </x-filament::button>
                    </div>
                    {{-- Comfort feedback: first ACME can take 1–3 min (certbot + dhparam). --}}
                    <div
                        wire:loading.flex
                        wire:target="setupLetsEncrypt"
                        class="hidden items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
                        role="status"
                        aria-live="polite"
                    >
                        <svg class="mt-0.5 h-5 w-5 shrink-0 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                            <p class="font-semibold">Issuing Let's Encrypt certificate…</p>
                            <p class="mt-1 text-blue-800">
                                First run often takes <strong>1–3 minutes</strong> (ACME challenge + TLS setup).
                                Keep this tab open — the page will update when done.
                            </p>
                        </div>
                    </div>
                    @if ($setupErrorMessage)
                        <p class="text-sm text-danger-600">{{ $setupErrorMessage }}</p>
                    @endif
                    @if ($setupErrorDetail)
                        <pre class="overflow-x-auto rounded bg-slate-100 p-2 text-xs text-danger-700">{{ $setupErrorDetail }}</pre>
                    @endif
                    @if ($setupSuccess)
                        <p class="text-sm text-success-600">{{ $setupSuccess }}</p>
                    @endif
                </div>
            @endif
        </section>

        {{-- Section 2: Purchased certificate --}}
        <section class="cert-section space-y-3">
            <div class="section-header">
                <h2 class="text-xl font-bold text-slate-900">Purchased certificate</h2>
            </div>
            <p class="section-explanation text-sm text-slate-600">
                Upload your own certificate (fullchain.pem) and private key (privkey.pem) from a commercial CA.
            </p>

            @if ($customLoading)
                <p class="text-sm text-slate-500">Loading…</p>
            @elseif ($customError)
                <p class="text-sm text-danger-600">{{ $customError }}</p>
            @else
                @if ($customInstalled)
                    <p class="text-sm text-slate-800">Customer certificate: In use.</p>
                @else
                    <p class="text-sm text-slate-500">Not installed.</p>
                @endif

                <div class="cert-upload max-w-lg space-y-3 text-sm">
                    <div>
                        <label class="mb-1 block font-medium text-slate-700">Certificate (fullchain.pem)</label>
                        <input type="file" wire:model="certFile" accept=".pem,.crt" @disabled($installing) />
                        @error('certFile') <span class="text-danger-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block font-medium text-slate-700">Private key (privkey.pem)</label>
                        <input type="file" wire:model="keyFile" accept=".pem,.key" @disabled($installing) />
                        @error('keyFile') <span class="text-danger-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="section-actions flex flex-wrap gap-3">
                    <x-filament::button
                        wire:click="installCustom"
                        wire:loading.attr="disabled"
                        :disabled="$installing || ! $certFile || ! $keyFile"
                    >
                        <span wire:loading.remove wire:target="installCustom">Install</span>
                        <span wire:loading wire:target="installCustom">Installing…</span>
                    </x-filament::button>
                    <x-filament::button
                        wire:click="confirmRemoveCustom"
                        color="danger"
                        :disabled="$installing || ! $customInstalled"
                    >
                        Remove
                    </x-filament::button>
                </div>
                @if ($installError)
                    <p class="text-sm text-danger-600">{{ $installError }}</p>
                @endif
                @if ($installSuccess)
                    <p class="text-sm text-success-600">{{ $installSuccess }}</p>
                @endif
            @endif
        </section>

        @if ($showRemoveConfirm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
                <div class="max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-slate-900">Remove purchased certificate?</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        The purchased certificate will be removed. The system will use Let's Encrypt (if
                        configured) or require a new certificate setup.
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <x-filament::button color="gray" wire:click="$set('showRemoveConfirm', false)">
                            Cancel
                        </x-filament::button>
                        <x-filament::button color="danger" wire:click="doRemoveCustom" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="doRemoveCustom">Remove</span>
                            <span wire:loading wire:target="doRemoveCustom">Removing…</span>
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
