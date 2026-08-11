<x-filament-panels::page>
    {{-- wire:init defers S3 list so first paint can show a spinner --}}
    <div class="space-y-6" wire:init="refresh">
        <x-filament::section>
            <x-slot name="heading">
                Cold DR backup
            </x-slot>
            <div class="space-y-2 text-sm text-gray-600">
                <p>
                    Creates a local MariaDB dump zip under
                    <code class="text-xs">/var/lib/pbx3sbc/bkup/</code>
                    (FIFO keep 9). Optional upload to
                    <code class="text-xs">s3://…/sbc/{id}/backups/</code>.
                </p>
                <p>
                    This is <strong>not</strong> HA failover — warm standby sync is
                    <strong>Fleet → Edge HA → Sync now</strong>.
                    Full restore stays CLI-only on a replacement host.
                </p>
                @if ($roleNote !== '')
                    <p class="text-sm {{ $vipHolder ? 'text-gray-700' : 'text-amber-700' }}">
                        {{ $roleNote }}
                    </p>
                @endif
            </div>
        </x-filament::section>

        @if ($loadError !== '')
            <x-filament::section>
                <p class="text-sm text-danger-600">{{ $loadError }}</p>
                <p class="mt-2 text-xs text-gray-500">
                    Ensure <code class="text-xs">sbc-backup-panel.sh</code> is deployed and
                    <code class="text-xs">sudo ./scripts/setup-admin-panel-sudoers.sh</code>
                    has been re-run on this host.
                </p>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                Create backup
            </x-slot>
            <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        wire:model="uploadToS3"
                        class="rounded border-gray-300"
                        @disabled(! $vipHolder || $creating || $loading)
                    />
                    Upload to S3 after create
                </label>
                <x-filament::button
                    type="button"
                    wire:click="createBackup"
                    wire:loading.attr="disabled"
                    wire:target="createBackup"
                    :disabled="! $vipHolder || $creating || $loading"
                    icon="lucide-hard-drive"
                >
                    <span wire:loading.remove wire:target="createBackup">Backup now</span>
                    <span wire:loading wire:target="createBackup" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating…
                    </span>
                </x-filament::button>
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="refresh"
                    wire:loading.attr="disabled"
                    wire:target="refresh"
                    :disabled="$loading || $creating"
                    icon="lucide-refresh-cw"
                >
                    <span wire:loading.remove wire:target="refresh">Refresh list</span>
                    <span wire:loading wire:target="refresh" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading…
                    </span>
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Archives
            </x-slot>
            <x-slot name="description">
                Newest first. Filename kinship with instance Backup (SPA):
                <span class="font-mono text-xs">local+S3</span> = on this host and S3;
                <span class="font-mono text-xs">S3</span> = aged out of local FIFO (keep 9) but still under
                <code class="text-xs">s3://…/sbc/{id}/backups/</code> (~30d).
                Restore from CLI — not from this panel.
            </x-slot>

            @if ($loading)
                <div
                    class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
                    role="status"
                    aria-live="polite"
                >
                    <svg class="mt-0.5 h-5 w-5 shrink-0 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div>
                        <p class="font-medium">Loading archives…</p>
                        <p class="mt-0.5 text-blue-800/80">Fetching the local + S3 backup list. This can take a moment.</p>
                    </div>
                </div>
            @else
                <div
                    wire:loading.flex
                    wire:target="refresh"
                    class="hidden items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
                    role="status"
                    aria-live="polite"
                >
                    <svg class="mt-0.5 h-5 w-5 shrink-0 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div>
                        <p class="font-medium">Loading archives…</p>
                        <p class="mt-0.5 text-blue-800/80">Fetching the local + S3 backup list. This can take a moment.</p>
                    </div>
                </div>

                <div wire:loading.remove wire:target="refresh">
                    @if (count($backups) === 0)
                        <p class="text-sm text-gray-500">No local or S3 archives found yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-gray-500">
                                        <th class="py-2 pr-4 font-medium">Created (UTC)</th>
                                        <th class="py-2 pr-4 font-medium">Archive ID</th>
                                        <th
                                            class="py-2 pr-4 font-medium"
                                            title="Zip name (sbcbak.{epoch}.zip). S3-only rows have no copy on this host."
                                        >
                                            Filename
                                        </th>
                                        <th class="py-2 font-medium">Size</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($backups as $row)
                                        <tr>
                                            <td class="py-2 pr-4 text-gray-900">{{ $row['created_at'] ?: '—' }}</td>
                                            <td class="py-2 pr-4 font-mono text-xs text-gray-700">{{ $row['backup_stamp'] ?: '—' }}</td>
                                            <td class="py-2 pr-4 font-mono text-xs text-gray-700">
                                                {{ $row['name'] ?: '—' }}
                                                @if (! empty($row['has_s3']) && empty($row['has_local']))
                                                    <span
                                                        class="ml-1 inline-flex rounded bg-sky-50 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-sky-800"
                                                        title="Archive only on S3 — restore via CLI (fetch-latest / restore-sbc-backup)"
                                                    >S3</span>
                                                @elseif (! empty($row['on_s3']) || (! empty($row['has_local']) && ! empty($row['has_s3'])))
                                                    <span
                                                        class="ml-1 inline-flex rounded bg-green-50 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-green-800"
                                                        title="Local zip and S3 archive"
                                                    >local+S3</span>
                                                @endif
                                            </td>
                                            <td class="py-2 text-gray-700">
                                                @if ((int) ($row['bytes'] ?? 0) > 0)
                                                    {{ \App\Filament\Pages\Backup::formatBytes((int) $row['bytes']) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
