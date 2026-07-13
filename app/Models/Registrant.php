<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registrant extends Model
{
    protected $table = 'registrant';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'registrar',
        'proxy',
        'aor',
        'third_party_registrant',
        'username',
        'password',
        'binding_URI',
        'binding_params',
        'expiry',
        'forced_socket',
        'cluster_shtag',
        'state',
    ];

    protected $casts = [
        'expiry' => 'integer',
        'state' => 'integer',
    ];
}
