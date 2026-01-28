<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoorKnockAttempt extends Model
{
    protected $table = 'door_knock_attempts';
    
    public $timestamps = false;
    
    protected $fillable = [
        'domain', 'source_ip', 'source_port', 'user_agent',
        'method', 'request_uri', 'reason', 'attempt_time'
    ];
    
    protected $casts = [
        'attempt_time' => 'datetime',
        'source_port' => 'integer',
    ];
    
    // Scopes
    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', 'like', "%{$domain}%");
    }
    
    public function scopeBySourceIp($query, $ip)
    {
        return $query->where('source_ip', 'like', "%{$ip}%");
    }
    
    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }
    
    public function scopeByMethod($query, $method)
    {
        return $query->where('method', $method);
    }
    
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('attempt_time', [$start, $end]);
    }
    
    // Accessors
    public function getReasonBadgeAttribute()
    {
        return match($this->reason) {
            'scanner_detected' => 'danger',
            'domain_not_found' => 'warning',
            'query_failed' => 'warning',
            'domain_mismatch' => 'warning',
            'method_not_allowed' => 'info',
            'max_forwards_exceeded' => 'info',
            default => 'gray',
        };
    }
    
    public function getReasonLabelAttribute()
    {
        return match($this->reason) {
            'scanner_detected' => 'Scanner Detected',
            'domain_not_found' => 'Domain Not Found',
            'query_failed' => 'Query Failed',
            'domain_mismatch' => 'Domain Mismatch',
            'method_not_allowed' => 'Method Not Allowed',
            'max_forwards_exceeded' => 'Max Forwards Exceeded',
            default => ucfirst(str_replace('_', ' ', $this->reason ?? 'Unknown')),
        };
    }
}
