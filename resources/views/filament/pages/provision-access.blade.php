<x-filament-panels::page>
    <div class="space-y-6">
        @if (! $scriptFound)
            <x-filament::section>
                <p class="text-sm text-danger-600">
                    UFW apply script not found on this host. Deploy
                    <code class="text-xs">pbx3sbc/scripts/apply-provision-access-ufw.sh</code>
                    and run
                    <code class="text-xs">sudo ./scripts/setup-admin-panel-sudoers.sh</code>,
                    then refresh.
                </p>
            </x-filament::section>
        @endif

        <form wire:submit="apply" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="button" color="gray" wire:click="addMyIp">
                    Add my IP
                </x-filament::button>
                <x-filament::button type="submit" color="primary">
                    Apply
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="refreshStatus">
                    Refresh UFW status
                </x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">
                Status
            </x-slot>
            <div class="space-y-2 text-sm text-gray-600">
                <p>
                    Client IP:
                    <code class="text-xs">{{ $clientIp !== '' ? $clientIp : 'unknown' }}</code>
                    · State:
                    <code class="text-xs">{{ $statePath }}</code>
                </p>
                @if ($lastApply)
                    <p>
                        Last apply:
                        <code class="text-xs">{{ $lastApply['at'] ?? '' }}</code>
                        —
                        {{ ($lastApply['ok'] ?? false) ? 'ok' : 'failed' }}
                        (lockdown={{ ($lastApply['lockdown'] ?? false) ? 'on' : 'off' }},
                        allows={{ $lastApply['allow_count'] ?? 0 }})
                    </p>
                @endif
                <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-800 whitespace-pre-wrap">{{ $ufwStatus !== '' ? $ufwStatus : '(no status yet)' }}</pre>
                <p class="text-xs text-gray-500">
                    Recovery if locked out: AWS console / serial / physical access, then
                    <code class="text-xs">sudo ufw allow 41363/tcp</code>
                    or set lockdown false in the state JSON and re-run the apply script.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
