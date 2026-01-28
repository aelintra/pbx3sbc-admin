<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fail2banWhitelist extends Model
{
    protected $table = 'fail2ban_whitelist';

    protected $fillable = [
        'ip_or_cidr',
        'comment',
        'created_by',
    ];

    /**
     * User who created this whitelist entry
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
