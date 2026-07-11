<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbAlias extends Model
{
    protected $table = 'dbaliases';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'alias_username',
        'alias_domain',
        'username',
        'domain',
    ];
}
