<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class Fail2banService
{
    protected string $jailName = 'opensips-brute-force';
    
    /**
     * Get jail status
     */
    public function getStatus(): array
    {
        $result = Process::run(['sudo', 'fail2ban-client', 'status', $this->jailName]);
        
        if (!$result->successful()) {
            throw new \Exception('Failed to get Fail2Ban status: ' . $result->errorOutput());
        }
        
        return $this->parseStatus($result->output());
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
        
        // Extract banned IPs
        if (preg_match('/Banned IP list:\s*(.+)/', $output, $matches)) {
            $ips = trim($matches[1]);
            $status['banned_ips'] = $ips ? explode(' ', $ips) : [];
        }
        
        // Extract counts
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
        $status['enabled'] = strpos($output, 'Status for the jail') !== false;
        
        return $status;
    }
}
