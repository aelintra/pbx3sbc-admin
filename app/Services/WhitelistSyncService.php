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
        $credsFile = null;

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

            // Never pass the DB password as a sudo/script command-line argument —
            // argv is visible to any local user via `ps`/`/proc` for the life of
            // the process. Write a private (mode 0600) MySQL
            // "--defaults-extra-file" instead and hand the sync script only its
            // path; root (via sudo) can still read it regardless of the 0600
            // owner-only mode. The file is removed again once the script exits.
            $credsFile = $this->writeMysqlDefaultsExtraFile($dbUser, $dbPass);
            if ($credsFile === null) {
                Log::error('Failed to write MySQL credentials file for Fail2Ban sync');
                return false;
            }

            // Arguments: script_path DB_NAME CREDS_FILE (no password on argv)
            $result = Process::run([
                'sudo',
                $scriptPath,
                $dbName,
                $credsFile,
            ]);

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
        } finally {
            if ($credsFile !== null && file_exists($credsFile)) {
                @unlink($credsFile);
            }
        }
    }

    /**
     * Write a MySQL "--defaults-extra-file" style ini file containing the
     * client credentials, mode 0600, in the system temp dir. Returns null on
     * failure. Caller is responsible for deleting the file after use.
     */
    protected function writeMysqlDefaultsExtraFile(?string $dbUser, ?string $dbPass): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'f2bwl');
        if ($path === false) {
            return null;
        }

        if (!chmod($path, 0600)) {
            @unlink($path);

            return null;
        }

        $contents = "[client]\n"
            .'user='.($dbUser ?? '')."\n"
            .'password='.($dbPass ?? '')."\n";

        if (file_put_contents($path, $contents) === false) {
            @unlink($path);

            return null;
        }

        return $path;
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
