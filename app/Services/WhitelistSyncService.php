<?php

namespace App\Services;

use App\Models\Fail2banWhitelist;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class WhitelistSyncService
{
    protected string $jailConfig = '/etc/fail2ban/jail.d/opensips-brute-force.conf';
    
    /**
     * Sync whitelist from database to Fail2Ban config file
     */
    public function sync(): bool
    {
        try {
            // Get all whitelist entries from database
            $whitelistEntries = Fail2banWhitelist::orderBy('created_at')->get();
            
            // Build ignoreip line
            $ips = $whitelistEntries->pluck('ip_or_cidr')->filter()->toArray();
            $ignoreipValue = implode(' ', $ips);
            
            // Read current config file
            if (!File::exists($this->jailConfig)) {
                Log::error('Fail2Ban jail config not found', ['path' => $this->jailConfig]);
                return false;
            }
            
            $configContent = File::get($this->jailConfig);
            
            // Update ignoreip line
            if (preg_match('/^ignoreip\s*=/m', $configContent)) {
                // Replace existing ignoreip line
                $configContent = preg_replace(
                    '/^ignoreip\s*=.*$/m',
                    'ignoreip = ' . $ignoreipValue,
                    $configContent
                );
            } else {
                // Add new ignoreip line after commented ignoreip or before Notes section
                if (preg_match('/^# ignoreip/m', $configContent)) {
                    $configContent = preg_replace(
                        '/^# ignoreip.*$/m',
                        "# ignoreip\nignoreip = " . $ignoreipValue,
                        $configContent
                    );
                } else {
                    // Insert before Notes section
                    $configContent = preg_replace(
                        '/^# Notes:/m',
                        "ignoreip = " . $ignoreipValue . "\n\n# Notes:",
                        $configContent
                    );
                }
            }
            
            // Add comments for each IP
            $comments = [];
            foreach ($whitelistEntries as $entry) {
                if ($entry->comment) {
                    $comments[] = "#   {$entry->ip_or_cidr} - {$entry->comment}";
                }
            }
            
            // Remove old comments and add new ones after ignoreip line
            $configContent = preg_replace('/^#\s+\d+\.\d+\.\d+\.\d+.*$/m', '', $configContent);
            if (!empty($comments)) {
                $configContent = preg_replace(
                    '/^(ignoreip\s*=.*)$/m',
                    '$1' . "\n" . implode("\n", $comments),
                    $configContent
                );
            }
            
            // Write updated config
            File::put($this->jailConfig, $configContent);
            
            // Restart Fail2Ban to apply changes
            $result = Process::run(['sudo', 'systemctl', 'restart', 'fail2ban']);
            
            if (!$result->successful()) {
                Log::error('Failed to restart Fail2Ban after whitelist sync', [
                    'error' => $result->errorOutput()
                ]);
                return false;
            }
            
            Log::info('Fail2Ban whitelist synced successfully', [
                'ip_count' => count($ips),
                'user' => auth()->id()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to sync Fail2Ban whitelist', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Get current whitelist from config file (for comparison)
     */
    public function getCurrentWhitelistFromConfig(): array
    {
        if (!File::exists($this->jailConfig)) {
            return [];
        }
        
        $configContent = File::get($this->jailConfig);
        
        if (preg_match('/^ignoreip\s*=\s*(.+)$/m', $configContent, $matches)) {
            $ips = trim($matches[1]);
            return $ips ? explode(' ', $ips) : [];
        }
        
        return [];
    }
}
