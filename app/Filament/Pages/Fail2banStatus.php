<?php

namespace App\Filament\Pages;

use App\Services\Fail2banService;
use App\Services\WhitelistSyncService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class Fail2banStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    
    protected static string $view = 'filament.pages.fail2ban-status';
    
    protected static ?string $navigationLabel = 'Fail2Ban Status';
    
    protected static ?int $navigationSort = 15;
    
    public array $status = [];
    public array $bannedIPs = [];
    public string $quickUnbanIP = '';
    public bool $addToWhitelist = false;
    public string $manualBanIP = '';
    
    protected Fail2banService $fail2banService;
    
    public function mount(): void
    {
        $this->fail2banService = app(Fail2banService::class);
        $this->loadStatus();
    }
    
    public function loadStatus(): void
    {
        try {
            // Cache status for 5 seconds to avoid too many calls
            $this->status = Cache::remember('fail2ban_status', 5, function () {
                return $this->fail2banService->getStatus();
            });
            $this->bannedIPs = $this->status['banned_ips'] ?? [];
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to load Fail2Ban status')
                ->body($e->getMessage())
                ->danger()
                ->send();
            
            $this->status = [
                'enabled' => false,
                'currently_banned' => 0,
                'banned_ips' => [],
            ];
            $this->bannedIPs = [];
        }
    }
    
    public function unbanIP(string $ip): void
    {
        try {
            if ($this->fail2banService->unbanIP($ip)) {
                Notification::make()
                    ->title('IP Unbanned')
                    ->body("IP {$ip} has been unbanned successfully.")
                    ->success()
                    ->send();
                
                // Optionally add to whitelist
                if ($this->addToWhitelist) {
                    $this->addToWhitelist($ip, "Auto-whitelisted after unban");
                }
                
                // Clear cache and reload
                Cache::forget('fail2ban_status');
                $this->loadStatus();
            } else {
                Notification::make()
                    ->title('Failed to Unban IP')
                    ->body("Could not unban IP {$ip}. Check logs for details.")
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function unbanAll(): void
    {
        try {
            if ($this->fail2banService->unbanAll()) {
                Notification::make()
                    ->title('All IPs Unbanned')
                    ->body('All banned IPs have been unbanned.')
                    ->warning()
                    ->send();
                
                Cache::forget('fail2ban_status');
                $this->loadStatus();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function quickUnban(): void
    {
        if (empty($this->quickUnbanIP)) {
            Notification::make()
                ->title('IP Required')
                ->body('Please enter an IP address to unban.')
                ->warning()
                ->send();
            return;
        }
        
        $this->unbanIP($this->quickUnbanIP);
        $this->quickUnbanIP = '';
        $this->addToWhitelist = false;
    }
    
    public function manualBan(): void
    {
        if (empty($this->manualBanIP)) {
            Notification::make()
                ->title('IP Required')
                ->body('Please enter an IP address to ban.')
                ->warning()
                ->send();
            return;
        }
        
        try {
            if ($this->fail2banService->banIP($this->manualBanIP)) {
                Notification::make()
                    ->title('IP Banned')
                    ->body("IP {$this->manualBanIP} has been banned.")
                    ->success()
                    ->send();
                
                $this->manualBanIP = '';
                Cache::forget('fail2ban_status');
                $this->loadStatus();
            } else {
                Notification::make()
                    ->title('Failed to Ban IP')
                    ->body("Could not ban IP {$this->manualBanIP}. Check logs for details.")
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function addToWhitelist(string $ip, string $comment): void
    {
        try {
            $whitelist = \App\Models\Fail2banWhitelist::create([
                'ip_or_cidr' => $ip,
                'comment' => $comment,
                'created_by' => auth()->id(),
            ]);
            
            // Sync to Fail2Ban
            app(WhitelistSyncService::class)->sync();
            
            Notification::make()
                ->title('Added to Whitelist')
                ->body("IP {$ip} has been added to whitelist.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to Add to Whitelist')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }
}
