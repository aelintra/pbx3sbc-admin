<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class Fail2banService
{
    protected string $jailName = 'opensips-brute-force';
    
    /**
     * Check if Fail2ban service is running
     */
    public function isServiceRunning(): bool
    {
        $result = Process::run(['sudo', 'systemctl', 'is-active', 'fail2ban']);
        return $result->successful() && trim($result->output()) === 'active';
    }
    
    /**
     * Get jail status
     */
    public function getStatus(): array
    {
        // First check if Fail2ban service is running
        if (!$this->isServiceRunning()) {
            Log::warning('Fail2ban service is not running');
            return [
                'jail_name' => $this->jailName,
                'enabled' => false,
                'service_running' => false,
                'currently_failed' => 0,
                'total_failed' => 0,
                'currently_banned' => 0,
                'total_banned' => 0,
                'banned_ips' => [],
                'error' => 'Fail2ban service is not running. Start it with: sudo systemctl start fail2ban',
            ];
        }
        
        $result = Process::run(['sudo', 'fail2ban-client', 'status', $this->jailName]);
        
        if (!$result->successful()) {
            $errorOutput = $result->errorOutput();
            $output = $result->output();
            
            Log::error('Fail2ban status command failed', [
                'exit_code' => $result->exitCode(),
                'error_output' => $errorOutput,
                'output' => $output,
            ]);
            
            // Check if it's a socket error (service not running)
            if (strpos($errorOutput, 'Failed to access socket') !== false || 
                strpos($errorOutput, 'Is fail2ban running') !== false) {
                return [
                    'jail_name' => $this->jailName,
                    'enabled' => false,
                    'service_running' => false,
                    'currently_failed' => 0,
                    'total_failed' => 0,
                    'currently_banned' => 0,
                    'total_banned' => 0,
                    'banned_ips' => [],
                    'error' => 'Fail2ban service is not running. Start it with: sudo systemctl start fail2ban',
                ];
            }
            
            throw new \Exception('Failed to get Fail2Ban status: ' . $errorOutput);
        }
        
        $output = $result->output();
        Log::info('Fail2ban status command succeeded', [
            'output' => $output,
            'exit_code' => $result->exitCode(),
        ]);
        
        $status = $this->parseStatus($output);
        $status['service_running'] = true;
        
        Log::info('Parsed Fail2ban status', [
            'enabled' => $status['enabled'],
            'currently_banned' => $status['currently_banned'],
            'total_banned' => $status['total_banned'],
        ]);
        
        return $status;
    }
    
    /**
     * Get list of banned IPs
     */
    public function getBannedIPs(): array
    {
        $status = $this->getStatus();
        return $status['banned_ips'] ?? [];
    }
    
    /**
     * Unban a specific IP
     */
    public function unbanIP(string $ip): bool
    {
        $result = Process::run([
            'sudo',
            'fail2ban-client',
            'set',
            $this->jailName,
            'unbanip',
            $ip
        ]);
        
        if (!$result->successful()) {
            Log::error('Failed to unban IP', [
                'ip' => $ip,
                'error' => $result->errorOutput()
            ]);
            return false;
        }
        
        Log::info('IP unbanned via admin panel', [
            'ip' => $ip,
            'user' => auth()->id()
        ]);
        
        return true;
    }
    
    /**
     * Unban all IPs
     */
    public function unbanAll(): bool
    {
        $result = Process::run([
            'sudo',
            'fail2ban-client',
            'set',
            $this->jailName,
            'unban',
            '--all'
        ]);
        
        if (!$result->successful()) {
            Log::error('Failed to unban all IPs', [
                'error' => $result->errorOutput()
            ]);
            return false;
        }
        
        Log::warning('All IPs unbanned via admin panel', [
            'user' => auth()->id()
        ]);
        
        return true;
    }
    
    /**
     * Ban an IP manually
     */
    public function banIP(string $ip): bool
    {
        $result = Process::run([
            'sudo',
            'fail2ban-client',
            'set',
            $this->jailName,
            'banip',
            $ip
        ]);
        
        if (!$result->successful()) {
            Log::error('Failed to ban IP', [
                'ip' => $ip,
                'error' => $result->errorOutput()
            ]);
            return false;
        }
        
        Log::info('IP banned via admin panel', [
            'ip' => $ip,
            'user' => auth()->id()
        ]);
        
        return true;
    }
    
    /**
     * Parse Fail2Ban status output
     */
    protected function parseStatus(string $output): array
    {
        $status = [
            'jail_name' => $this->jailName,
            'enabled' => false,
            'currently_failed' => 0,
            'total_failed' => 0,
            'currently_banned' => 0,
            'total_banned' => 0,
            'banned_ips' => [],
        ];
        
        // Extract banned IPs (handle empty list)
        if (preg_match('/Banned IP list:\s*(.+)/', $output, $matches)) {
            $ips = trim($matches[1]);
            $status['banned_ips'] = $ips ? preg_split('/\s+/', $ips) : [];
        }
        
        // Extract counts (handle both tabs and spaces)
        if (preg_match('/Currently banned:\s*(\d+)/', $output, $matches)) {
            $status['currently_banned'] = (int)$matches[1];
        }
        
        if (preg_match('/Total banned:\s*(\d+)/', $output, $matches)) {
            $status['total_banned'] = (int)$matches[1];
        }
        
        if (preg_match('/Currently failed:\s*(\d+)/', $output, $matches)) {
            $status['currently_failed'] = (int)$matches[1];
        }
        
        if (preg_match('/Total failed:\s*(\d+)/', $output, $matches)) {
            $status['total_failed'] = (int)$matches[1];
        }
        
        // Check if enabled (jail exists and has status)
        // If fail2ban-client status succeeds, the jail exists and is enabled
        // Look for status indicators in the output - if command succeeded, jail is enabled
        $hasStatusHeader = (
            stripos($output, 'Status for the jail') !== false ||
            stripos($output, 'Filter') !== false ||
            stripos($output, 'Actions') !== false ||
            stripos($output, 'Currently failed') !== false ||
            stripos($output, 'Currently banned') !== false
        );
        
        // Jail is enabled if we see status indicators (command succeeded = jail exists)
        // Default to true if we got valid output (command wouldn't succeed if jail didn't exist)
        $status['enabled'] = $hasStatusHeader || !empty($output);
        
        return $status;
    }
}
