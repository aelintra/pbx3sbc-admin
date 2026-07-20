<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                How purge works
            </x-slot>
            <div class="space-y-2 text-sm text-gray-600">
                <p>
                    Daily cron deletes rows older than the days below
                    (<code class="text-xs">06:15</code> security events,
                    <code class="text-xs">06:20</code> edge CDR).
                    Saving here updates an override file; it does <strong>not</strong> run a purge.
                </p>
                <p>
                    CLI:
                    <code class="text-xs">php artisan pbx3sbc:purge-security-events --dry-run</code>
                    ·
                    <code class="text-xs">php artisan pbx3sbc:purge-acc --dry-run</code>
                </p>
                @if ($hasOverride)
                    <p class="text-xs text-gray-500">Override file: {{ $overridePath }}</p>
                @else
                    <p class="text-xs text-gray-500">Using .env / config defaults until you save (then writes {{ $overridePath }}).</p>
                @endif
            </div>
        </x-filament::section>

        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex gap-3">
                <x-filament::button type="submit">
                    Save retention days
                </x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="refreshStatus" icon="lucide-refresh-cw">
                    Refresh status
                </x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">
                Last purge
            </x-slot>
            <x-slot name="description">
                Written by artisan/cron after each run (including dry-run).
            </x-slot>

            @php
                $security = is_array($status) ? ($status['security_events'] ?? null) : null;
                $acc = is_array($status) ? ($status['acc'] ?? null) : null;
            @endphp

            @if (! $security && ! $acc)
                <p class="text-sm text-gray-500">No purge has been recorded yet on this host.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500">
                                <th class="py-2 pr-4 font-medium">Job</th>
                                <th class="py-2 pr-4 font-medium">When (UTC)</th>
                                <th class="py-2 pr-4 font-medium">Days</th>
                                <th class="py-2 pr-4 font-medium">Mode</th>
                                <th class="py-2 font-medium">Tables</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ([['Security events', $security], ['Edge CDR (acc)', $acc]] as [$label, $row])
                                @if (is_array($row))
                                    <tr>
                                        <td class="py-2 pr-4 font-medium text-gray-900">{{ $label }}</td>
                                        <td class="py-2 pr-4 text-gray-700">{{ $row['at'] ?? '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-700">{{ $row['days'] ?? '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-700">{{ ! empty($row['dry_run']) ? 'dry-run' : 'purge' }}</td>
                                        <td class="py-2 text-gray-700">
                                            @if (! empty($row['tables']) && is_array($row['tables']))
                                                <ul class="list-inside list-disc">
                                                    @foreach ($row['tables'] as $t)
                                                        <li>
                                                            {{ $t['table'] ?? '?' }}:
                                                            eligible {{ $t['eligible'] ?? 0 }},
                                                            deleted {{ $t['deleted'] ?? 0 }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
