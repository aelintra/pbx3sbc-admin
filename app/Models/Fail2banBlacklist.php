<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fail2banBlacklist extends Model
{
    protected $table = 'fail2ban_blacklist';

    protected $fillable = [
        'ip_or_cidr',
        'reason',
        'created_by',
    ];

    /**
     * User who created this blacklist entry
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
