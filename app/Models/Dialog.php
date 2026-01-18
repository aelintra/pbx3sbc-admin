<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dialog extends Model
{
    protected $table = 'dialog';
    
    protected $primaryKey = 'dlg_id';
    
    public $incrementing = false;
    
    public $timestamps = false;
    
    public function getRouteKeyName()
    {
        return 'dlg_id';
    }
    
    protected $fillable = [
        'dlg_id',
        'callid',
        'from_uri',
        'to_uri',
        'from_tag',
        'to_tag',
        'state',
        'start_time',
        'created',
        'modified',
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'created' => 'datetime',
        'modified' => 'datetime',
        'state' => 'integer',
        'dlg_id' => 'integer',
    ];
    
    // Relationships
    public function cdr()
    {
        return $this->hasOne(Cdr::class, 'callid', 'callid');
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('state', 4); // Established (active/running calls)
    }
    
    public function scopeUnconfirmed($query)
    {
        return $query->where('state', 1);
    }
    
    public function scopeEarly($query)
    {
        return $query->where('state', 2);
    }
    
    public function scopeConfirmed($query)
    {
        return $query->where('state', 3);
    }
    
    public function scopeEstablished($query)
    {
        return $query->where('state', 4);
    }
    
    public function scopeEnded($query)
    {
        return $query->where('state', 5);
    }
    
    // Accessors
    public function getStateLabelAttribute()
    {
        return match($this->state) {
            1 => 'Unconfirmed',
            2 => 'Early',
            3 => 'Confirmed',
            4 => 'Established',
            5 => 'Ended',
            default => 'Unknown',
        };
    }
    
    public function getStateBadgeAttribute()
    {
        return match($this->state) {
            1 => 'gray',    // Unconfirmed
            2 => 'warning', // Early
            3 => 'info',    // Confirmed
            4 => 'success', // Established (active call)
            5 => 'gray',    // Ended
            default => 'gray',
        };
    }
    
    public function getLiveDurationAttribute()
    {
        if (!$this->start_time) {
            return 0;
        }
        
        $now = now();
        $start = $this->start_time;
        
        return $start->diffInSeconds($now);
    }
    
    public function getFormattedLiveDurationAttribute()
    {
        $seconds = $this->getLiveDurationAttribute();
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
