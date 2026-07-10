<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrRule extends Model
{
    protected $table = 'dr_rules';

    protected $primaryKey = 'ruleid';

    public $timestamps = false;

    protected $fillable = [
        'groupid',
        'prefix',
        'timerec',
        'priority',
        'routeid',
        'gwlist',
        'sort_alg',
        'sort_profile',
        'attrs',
        'description',
    ];

    protected $casts = [
        'priority' => 'integer',
        'sort_profile' => 'integer',
    ];
}
