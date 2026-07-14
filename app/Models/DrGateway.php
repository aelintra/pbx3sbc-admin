<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrGateway extends Model
{
    protected $table = 'dr_gateways';

    protected $primaryKey = 'id';

    public $timestamps = false;

    public const ROLE_OUTBOUND = 'outbound';

    public const ROLE_INBOUND = 'inbound';

    public const ROLE_ASTERISK = 'asterisk';

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

    /**
     * Parse attrs as semicolon-separated k=v pairs (OpenSIPS opaque string).
     *
     * @return array<string, string>
     */
    public static function parseAttrs(?string $attrs): array
    {
        $out = [];
        $raw = trim((string) $attrs);
        if ($raw === '') {
            return $out;
        }
        foreach (explode(';', $raw) as $part) {
            $part = trim($part);
            if ($part === '' || ! str_contains($part, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $part, 2));
            if ($k !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public static function formatAttrs(array $pairs): string
    {
        $parts = [];
        foreach ($pairs as $k => $v) {
            $k = trim((string) $k);
            if ($k === '') {
                continue;
            }
            $parts[] = $k.'='.trim((string) $v);
        }

        return implode(';', $parts);
    }

    /** Normalize operator "Magrathea" / " magrathea " → slug magrathea */
    public static function normalizeCarrierSlug(?string $labelOrSlug): string
    {
        $s = strtolower(trim((string) $labelOrSlug));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');

        return $s;
    }

    public function carrierSlug(): string
    {
        $parsed = self::parseAttrs($this->attrs);

        return (string) ($parsed['carrier'] ?? '');
    }

    public function peerRole(): string
    {
        $parsed = self::parseAttrs($this->attrs);
        $role = (string) ($parsed['role'] ?? '');

        return in_array($role, [self::ROLE_OUTBOUND, self::ROLE_INBOUND, self::ROLE_ASTERISK], true)
            ? $role
            : '';
    }

    /** Title for Filament table groups */
    public function carrierGroupTitle(): string
    {
        $slug = $this->carrierSlug();
        if ($slug !== '') {
            return ucwords(str_replace('-', ' ', $slug));
        }

        return '(ungrouped)';
    }

    public function peerRoleLabel(): string
    {
        return match ($this->peerRole()) {
            self::ROLE_OUTBOUND => 'Outbound',
            self::ROLE_INBOUND => 'Inbound',
            self::ROLE_ASTERISK => 'Asterisk',
            default => '—',
        };
    }

    /**
     * Merge carrier + role into attrs; preserve other keys.
     */
    public function setCarrierAttrs(?string $carrierLabelOrSlug, ?string $role): void
    {
        $pairs = self::parseAttrs($this->attrs);
        $slug = self::normalizeCarrierSlug($carrierLabelOrSlug);
        if ($slug !== '') {
            $pairs['carrier'] = $slug;
        } else {
            unset($pairs['carrier']);
        }

        $role = trim((string) $role);
        if (in_array($role, [self::ROLE_OUTBOUND, self::ROLE_INBOUND, self::ROLE_ASTERISK], true)) {
            $pairs['role'] = $role;
        } else {
            unset($pairs['role']);
        }

        $this->attrs = self::formatAttrs($pairs) ?: null;
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
