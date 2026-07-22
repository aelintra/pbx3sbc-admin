<x-filament-panels::page>
    <div class="space-y-6">
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
                        @disabled(! $vipHolder || $creating)
                    />
                    Upload to S3 after create
                </label>
                <x-filament::button
                    type="button"
                    wire:click="createBackup"
                    wire:loading.attr="disabled"
                    :disabled="! $vipHolder || $creating"
                    icon="lucide-hard-drive"
                >
                    <span wire:loading.remove wire:target="createBackup">Backup now</span>
                    <span wire:loading wire:target="createBackup">Creating…</span>
                </x-filament::button>
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="refresh"
                    icon="lucide-refresh-cw"
                >
                    Refresh list
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Local archives
            </x-slot>
            <x-slot name="description">
                Newest first. Restore from CLI — not from this panel.
            </x-slot>

            @if (count($backups) === 0)
                <p class="text-sm text-gray-500">No local <code class="text-xs">sbcbak.*.zip</code> files yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500">
                                <th class="py-2 pr-4 font-medium">Created (UTC)</th>
                                <th class="py-2 pr-4 font-medium">Archive ID</th>
                                <th class="py-2 pr-4 font-medium">Local file</th>
                                <th class="py-2 pr-4 font-medium">Size</th>
                                <th class="py-2 font-medium">On S3?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($backups as $row)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-900">{{ $row['created_at'] ?: '—' }}</td>
                                    <td class="py-2 pr-4 font-mono text-xs text-gray-700">{{ $row['backup_stamp'] ?: '—' }}</td>
                                    <td class="py-2 pr-4 font-mono text-xs text-gray-700">{{ $row['name'] }}</td>
                                    <td class="py-2 pr-4 text-gray-700">{{ \App\Filament\Pages\Backup::formatBytes((int) $row['bytes']) }}</td>
                                    <td class="py-2">
                                        @if (! empty($row['on_s3']))
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Yes</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
