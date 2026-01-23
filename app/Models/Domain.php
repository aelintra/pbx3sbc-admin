<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $table = 'domain';
    
    public $timestamps = false;
    
    protected $fillable = ['domain', 'setid', 'attrs', 'accept_subdomain'];

    /**
     * Get all dispatcher destinations for this domain's setid
     */
    public function dispatchers(): HasMany
    {
        return $this->hasMany(Dispatcher::class, 'setid', 'setid');
    }
}
