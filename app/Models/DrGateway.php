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

    /**
     * Human label for selects/tables: "Magrathea inbound … (sip:…)" — gwid only as secondary.
     */
    public function displayLabel(): string
    {
        $name = trim((string) ($this->description ?: ''));
        $addr = (string) $this->address;
        if ($name !== '') {
            return "{$name} — {$addr}";
        }

        return $addr !== '' ? $addr : "Gateway {$this->gwid}";
    }

    public static function optionsForSelect(): array
    {
        return static::query()
            ->orderByRaw('CAST(gwid AS UNSIGNED), gwid')
            ->get()
            ->mapWithKeys(fn (self $g) => [(string) $g->gwid => $g->displayLabel()])
            ->all();
    }

    /**
     * Resolve a comma-separated gwlist to display labels (preserve order).
     */
    public static function labelsForGwlist(?string $gwlist): string
    {
        $tokens = array_values(array_filter(array_map('trim', explode(',', (string) $gwlist))));
        if ($tokens === []) {
            return '—';
        }

        $byGwid = static::query()
            ->whereIn('gwid', $tokens)
            ->get()
            ->keyBy(fn (self $g) => (string) $g->gwid);

        $parts = [];
        foreach ($tokens as $token) {
            if (str_starts_with($token, '#')) {
                $parts[] = "carrier {$token}";
                continue;
            }
            $gw = $byGwid->get($token);
            $parts[] = $gw ? $gw->displayLabel() : "unknown ({$token})";
        }

        return implode(' → ', $parts);
    }
}
