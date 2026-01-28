<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedRegistration extends Model
{
    protected $table = 'failed_registrations';
    
    public $timestamps = false;
    
    protected $fillable = [
        'username', 'domain', 'source_ip', 'source_port',
        'user_agent', 'response_code', 'response_reason',
        'attempt_time', 'expires_header'
    ];
    
    protected $casts = [
        'attempt_time' => 'datetime',
        'source_port' => 'integer',
        'response_code' => 'integer',
        'expires_header' => 'integer',
    ];
    
    // Scopes
    public function scopeByUsername($query, $username)
    {
        return $query->where('username', 'like', "%{$username}%");
    }
    
    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', 'like', "%{$domain}%");
    }
    
    public function scopeBySourceIp($query, $ip)
    {
        return $query->where('source_ip', 'like', "%{$ip}%");
    }
    
    public function scopeByResponseCode($query, $code)
    {
        return $query->where('response_code', $code);
    }
    
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('attempt_time', [$start, $end]);
    }
    
    // Accessors
    public function getResponseCodeBadgeAttribute()
    {
        if ($this->response_code >= 500) {
            return 'danger';
        } elseif ($this->response_code >= 400) {
            return 'warning';
        }
        return 'gray';
    }
}
