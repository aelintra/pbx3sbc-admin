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
     * Get the path to the sync script
     */
    protected function getSyncScriptPath(): string
    {
        // Allow override via environment variable
        $scriptPath = env('FAIL2BAN_SYNC_SCRIPT_PATH');
        
        if ($scriptPath && file_exists($scriptPath)) {
            return $scriptPath;
        }
        
        // Try common locations
        $commonPaths = [
            '/home/ubuntu/pbx3sbc/scripts/sync-fail2ban-whitelist.sh',
            '/opt/pbx3sbc/scripts/sync-fail2ban-whitelist.sh',
            '/usr/local/pbx3sbc/scripts/sync-fail2ban-whitelist.sh',
            base_path('../pbx3sbc/scripts/sync-fail2ban-whitelist.sh'),
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default fallback
        return '/home/ubuntu/pbx3sbc/scripts/sync-fail2ban-whitelist.sh';
    }
    
    /**
     * Sync whitelist from database to Fail2Ban config file
     */
    public function sync(): bool
    {
        try {
            $scriptPath = $this->getSyncScriptPath();
            
            if (!file_exists($scriptPath)) {
                Log::error('Fail2Ban sync script not found', ['path' => $scriptPath]);
                return false;
            }
            
            // Get database credentials from Laravel config
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            
            // Prepare environment variables for the script
            $env = [
                'DB_NAME' => $dbName,
                'DB_USER' => $dbUser,
                'DB_PASS' => $dbPass,
            ];
            
            // Call the sync script via sudo
            $result = Process::env($env)
                ->run(['sudo', $scriptPath]);
            
            if (!$result->successful()) {
                Log::error('Failed to sync Fail2Ban whitelist', [
                    'script_path' => $scriptPath,
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                    'output' => $result->output(),
                ]);
                return false;
            }
            
            // Get count of whitelist entries for logging
            $ipCount = Fail2banWhitelist::count();
            
            Log::info('Fail2Ban whitelist synced successfully', [
                'script_path' => $scriptPath,
                'ip_count' => $ipCount,
                'user' => auth()->id(),
                'output' => $result->output(),
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
