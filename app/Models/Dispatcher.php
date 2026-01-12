<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatcher extends Model
{
    protected $table = 'dispatcher';
    
    public $timestamps = false;
    
    protected $fillable = ['setid', 'destination', 'socket', 'state', 'probe_mode', 'weight', 'priority', 'attrs', 'description'];
}
