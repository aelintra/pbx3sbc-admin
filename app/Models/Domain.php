<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $table = 'domain';
    
    public $timestamps = false;
    
    protected $fillable = ['domain', 'setid', 'attrs', 'accept_subdomain', 'last_modified'];

    /**
     * Get all dispatcher destinations for this domain's setid
     */
    public function dispatchers(): HasMany
    {
        return $this->hasMany(Dispatcher::class, 'setid', 'setid');
    }
    
    /**
     * Get count of dispatchers for this domain
     * Uses eager-loaded relationship if available, otherwise queries
     */
    public function getDispatchersCountAttribute(): int
    {
        // Use eager-loaded collection if available (from ->with('dispatchers'))
        if ($this->relationLoaded('dispatchers')) {
            return $this->dispatchers->count();
        }
        // Otherwise query the relationship
        return $this->dispatchers()->count();
    }
    
    /**
     * Get formatted list of dispatcher destinations (with status indicators)
     * Uses eager-loaded relationship if available, otherwise queries
     */
    public function getDispatchersListAttribute(): string
    {
        // Accessing $this->dispatchers as property uses eager-loaded collection if available,
        // or queries the relationship if not loaded
        $destinations = $this->dispatchers;
        
        if ($destinations->isEmpty()) {
            return 'No destinations';
        }
        
        return $destinations->take(3)->map(function ($dispatcher) {
            $status = $dispatcher->state == 0 ? '✓' : '✗';
            return "{$status} {$dispatcher->destination}";
        })->join(', ') . ($destinations->count() > 3 ? '...' : '');
    }
}
