<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                {{ $logPath }}
            </x-slot>
            <x-slot name="description">
                Last {{ $lineCount }} line{{ $lineCount === 1 ? '' : 's' }} (newest at bottom). Ban / unban actions remain under Fail2Ban → Status.
            </x-slot>

            <div class="mb-4 flex flex-wrap items-end gap-3">
                <div class="w-40">
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="linesRequested">
                        Lines
                    </label>
                    <select
                        id="linesRequested"
                        wire:model.live="linesRequested"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                        <option value="2000">2000</option>
                    </select>
                </div>
                <x-filament::button wire:click="loadLog" color="gray" icon="lucide-refresh-cw">
                    Refresh
                </x-filament::button>
            </div>

            @if ($error)
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    {{ $error }}
                </div>
            @elseif (empty($lines))
                <div class="py-8 text-center text-sm text-gray-500">
                    Log is empty or unavailable.
                </div>
            @else
                <div
                    class="max-h-[70vh] overflow-auto rounded-lg border border-gray-200 bg-slate-950 p-4"
                    x-data
                    x-init="$el.scrollTop = $el.scrollHeight"
                >
                    <pre class="whitespace-pre-wrap break-all font-mono text-xs leading-5 text-slate-100">@foreach ($lines as $line){{ $line }}
@endforeach</pre>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
