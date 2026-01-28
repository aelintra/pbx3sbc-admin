<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Status Card -->
        <x-filament::section>
            <x-slot name="heading">
                Fail2Ban Status - {{ $status['jail_name'] ?? 'opensips-brute-force' }}
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Jail Status</div>
                    <div class="text-2xl font-bold">
                        @if($status['enabled'] ?? false)
                            <span class="text-green-600 dark:text-green-400">✓ Enabled</span>
                        @else
                            <span class="text-red-600 dark:text-red-400">✗ Disabled</span>
                        @endif
                    </div>
                </div>
                
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Currently Banned</div>
                    <div class="text-2xl font-bold">{{ $status['currently_banned'] ?? 0 }} IPs</div>
                </div>
                
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Banned</div>
                    <div class="text-2xl font-bold">{{ $status['total_banned'] ?? 0 }}</div>
                </div>
            </div>
        </x-filament::section>

        <!-- Quick Unban Form -->
        <x-filament::section>
            <x-slot name="heading">
                Quick Unban
            </x-slot>
            
            <form wire:submit="quickUnban" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="quickUnbanIP"
                            placeholder="Enter IP address (e.g., 192.168.1.100)"
                        />
                    </x-filament::input.wrapper>
                    
                    <x-filament::input.wrapper>
                        <x-filament::input.checkbox
                            wire:model="addToWhitelist"
                            label="Also add to whitelist"
                        />
                    </x-filament::input.wrapper>
                    
                    <x-filament::button type="submit" color="warning">
                        Unban IP
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <!-- Banned IPs List -->
        <x-filament::section>
            <x-slot name="heading">
                Currently Banned IPs
            </x-slot>
            
            @if(empty($bannedIPs))
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    No IPs currently banned
                </div>
            @else
                <div class="space-y-2">
                    @foreach($bannedIPs as $ip)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <span class="font-mono text-sm">{{ $ip }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-filament::button
                                    wire:click="unbanIP('{{ $ip }}')"
                                    color="warning"
                                    size="sm"
                                >
                                    Unban
                                </x-filament::button>
                                <x-filament::button
                                    wire:click="unbanIP('{{ $ip }}'); $set('addToWhitelist', true)"
                                    color="success"
                                    size="sm"
                                >
                                    Unban & Whitelist
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            
            @if(!empty($bannedIPs))
                <div class="mt-4 flex justify-end">
                    <x-filament::button
                        wire:click="unbanAll"
                        color="danger"
                        wire:confirm="Are you sure you want to unban ALL IPs?"
                    >
                        Unban All
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        <!-- Manual Ban Form -->
        <x-filament::section>
            <x-slot name="heading">
                Manual Ban
            </x-slot>
            
            <form wire:submit="manualBan" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="manualBanIP"
                            placeholder="Enter IP address to ban"
                        />
                    </x-filament::input.wrapper>
                    
                    <x-filament::button type="submit" color="danger">
                        Ban IP
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <!-- Refresh Button -->
        <div class="flex justify-end">
            <x-filament::button wire:click="loadStatus" color="gray">
                Refresh Status
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
