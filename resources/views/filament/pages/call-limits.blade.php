<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Max call duration
            </x-slot>
            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    OpenSIPS dialog absolute lifetime. Orphaned Active Calls (no BYE after
                    Asterisk restart or abrupt SIPp stop) expire after this many seconds.
                </p>
                @if ($loadError !== '')
                    <p class="text-sm text-danger-600">{{ $loadError }}</p>
                    <p class="text-xs text-gray-500">
                        Showing OpenSIPS module default until
                        <code class="text-xs">{{ $cfgPath }}</code> is readable.
                    </p>
                @endif
                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Value</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $dialogTimeoutSeconds }}s
                            <span class="text-base font-normal text-gray-500">({{ $dialogTimeoutHuman }})</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Source</dt>
                        <dd class="mt-1 text-gray-800 dark:text-gray-200">
                            @if ($source === 'cfg')
                                <code class="text-xs">{{ $cfgPath }}</code>
                                <span class="text-gray-500">— modparam dialog / default_timeout</span>
                            @elseif ($source === 'opensips_default')
                                OpenSIPS module default (modparam omitted in cfg)
                            @else
                                Unreadable cfg — assumed module default
                            @endif
                        </dd>
                    </div>
                </dl>
                <p class="text-xs text-gray-500">
                    Kinship: pbx3 node Globals / tenant <code class="text-xs">abstimeout</code> default is
                    14400 (4h). Change on the edge via <code class="text-xs">opensips.cfg</code> +
                    OpenSIPS restart — not from this panel.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
