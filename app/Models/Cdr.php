<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cdr extends Model
{
    protected $table = 'acc';
    
    public $timestamps = false;
    
    protected $fillable = [
        'method',
        'callid',
        'from_tag',
        'to_tag',
        'from_uri',
        'to_uri',
        'sip_code',
        'sip_reason',
        'time',
        'created',
        'duration',
        'ms_duration',
        'setuptime',
    ];
    
    protected $casts = [
        'created' => 'datetime',
        'time' => 'datetime',
        'duration' => 'integer',
        'ms_duration' => 'integer',
        'setuptime' => 'integer',
        'sip_code' => 'integer',
    ];
    
    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('sip_code', 200);
    }
    
    public function scopeFailed($query)
    {
        return $query->where('sip_code', '!=', 200);
    }
    
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created', [$start, $end]);
    }
    
    public function scopeFromUri($query, $uri)
    {
        return $query->where('from_uri', 'like', "%{$uri}%");
    }
    
    public function scopeToUri($query, $uri)
    {
        return $query->where('to_uri', 'like', "%{$uri}%");
    }
    
    public function scopeCallId($query, $callId)
    {
        return $query->where('callid', 'like', "%{$callId}%");
    }
    
    public function scopeDurationRange($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('duration', '>=', $min);
        }
        if ($max !== null) {
            $query->where('duration', '<=', $max);
        }
        return $query;
    }
    
    // Accessors
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return '0:00';
        }
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
    
    public function getStatusAttribute()
    {
        return $this->sip_code == 200 ? 'success' : 'failed';
    }
    
    public function getStatusBadgeAttribute()
    {
        return $this->sip_code == 200 ? 'success' : 'danger';
    }
}
