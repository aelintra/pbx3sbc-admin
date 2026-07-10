<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrGateway extends Model
{
    protected $table = 'dr_gateways';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'gwid',
        'type',
        'address',
        'strip',
        'pri_prefix',
        'attrs',
        'probe_mode',
        'state',
        'socket',
        'description',
    ];

    protected $casts = [
        'type' => 'integer',
        'strip' => 'integer',
        'probe_mode' => 'integer',
        'state' => 'integer',
    ];
}
