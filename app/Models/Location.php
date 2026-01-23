<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'location';
    
    protected $primaryKey = 'contact_id';
    
    public $incrementing = true;
    
    public $timestamps = false;
    
    public function getRouteKeyName()
    {
        return 'contact_id';
    }
    
    protected $fillable = [
        'username',
        'domain',
        'contact',
        'received',
        'path',
        'expires',
        'q',
        'callid',
        'cseq',
        'last_modified',
        'flags',
        'cflags',
        'user_agent',
        'socket',
        'methods',
        'sip_instance',
        'kv_store',
        'attr',
    ];
    
    protected $casts = [
        'expires' => 'integer',
        'q' => 'float',
        'cseq' => 'integer',
        'flags' => 'integer',
        'methods' => 'integer',
        'last_modified' => 'datetime',
    ];
}
