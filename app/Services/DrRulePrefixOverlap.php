<?php

namespace App\Services;

use App\Models\DrRule;
use Illuminate\Support\Collection;

class DrRulePrefixOverlap
{
    public static function normalize(?string $prefix): string
    {
        return trim((string) $prefix);
    }

    /**
     * Same direction + same prefix (including empty catch-all) as another rule.
     */
    public static function findDuplicate(string|int $groupid, ?string $prefix, ?int $excludeRuleid = null): ?DrRule
    {
        $norm = self::normalize($prefix);

        $query = DrRule::query()->where('groupid', (string) $groupid);

        if ($excludeRuleid !== null) {
            $query->where('ruleid', '!=', $excludeRuleid);
        }

        if ($norm === '') {
            $query->where(function ($q): void {
                $q->whereNull('prefix')->orWhere('prefix', '');
            });
        } else {
            $query->where('prefix', $norm);
        }

        return $query->orderBy('ruleid')->first();
    }

    /**
     * @return array{parents: Collection<int, DrRule>, children: Collection<int, DrRule>}
     */
    public static function nesting(string|int $groupid, ?string $prefix, ?int $excludeRuleid = null): array
    {
        $norm = self::normalize($prefix);
        if ($norm === '') {
            return ['parents' => collect(), 'children' => collect()];
        }

        $others = DrRule::query()
            ->where('groupid', (string) $groupid)
            ->when($excludeRuleid !== null, fn ($q) => $q->where('ruleid', '!=', $excludeRuleid))
            ->orderBy('ruleid')
            ->get()
            ->map(function (DrRule $rule): DrRule {
                $rule->setAttribute('_norm', self::normalize($rule->prefix));

                return $rule;
            })
            ->filter(fn (DrRule $rule) => $rule->getAttribute('_norm') !== '');

        $classified = self::classify($norm, $others->map(fn (DrRule $rule) => [
            'ruleid' => (int) $rule->ruleid,
            'prefix' => (string) $rule->getAttribute('_norm'),
            'description' => (string) ($rule->description ?? ''),
            'model' => $rule,
        ])->all());

        return [
            'parents' => collect($classified['parents'])->pluck('model')->values(),
            'children' => collect($classified['children'])->pluck('model')->values(),
        ];
    }

    /**
     * Pure string classification for unit tests and form hints.
     *
     * @param  list<array{ruleid:int, prefix:string, description?:string, model?:mixed}>  $others
     * @return array{parents: list<array{ruleid:int, prefix:string, description:string, model?:mixed}>, children: list<array{ruleid:int, prefix:string, description:string, model?:mixed}>}
     */
    public static function classify(string $prefix, array $others): array
    {
        $norm = self::normalize($prefix);
        $parents = [];
        $children = [];

        if ($norm === '') {
            return ['parents' => [], 'children' => []];
        }

        foreach ($others as $other) {
            $otherPrefix = self::normalize($other['prefix'] ?? '');
            if ($otherPrefix === '' || $otherPrefix === $norm) {
                continue;
            }

            $row = [
                'ruleid' => (int) ($other['ruleid'] ?? 0),
                'prefix' => $otherPrefix,
                'description' => (string) ($other['description'] ?? ''),
            ];
            if (array_key_exists('model', $other)) {
                $row['model'] = $other['model'];
            }

            if (strlen($otherPrefix) < strlen($norm) && str_starts_with($norm, $otherPrefix)) {
                $parents[] = $row;
            } elseif (strlen($otherPrefix) > strlen($norm) && str_starts_with($otherPrefix, $norm)) {
                $children[] = $row;
            }
        }

        usort($parents, fn ($a, $b) => strlen($b['prefix']) <=> strlen($a['prefix']));
        usort($children, fn ($a, $b) => strlen($a['prefix']) <=> strlen($b['prefix']));

        return ['parents' => $parents, 'children' => $children];
    }

    public static function nestingHint(string|int $groupid, ?string $prefix, ?int $excludeRuleid = null): ?string
    {
        $nest = self::nesting($groupid, $prefix, $excludeRuleid);
        $bits = [];

        if ($nest['parents']->isNotEmpty()) {
            /** @var DrRule $parent */
            $parent = $nest['parents']->first();
            $parentPrefix = self::normalize((string) $parent->getAttribute('_norm') ?: $parent->prefix);
            $label = $parent->description ? " — {$parent->description}" : '';
            $bits[] = "Nested under rule {$parent->ruleid} (prefix “{$parentPrefix}”{$label}). OpenSIPS prefers this longer prefix when both match.";
        }

        if ($nest['children']->isNotEmpty()) {
            /** @var DrRule $child */
            $child = $nest['children']->first();
            $childPrefix = self::normalize((string) $child->getAttribute('_norm') ?: $child->prefix);
            $label = $child->description ? " — {$child->description}" : '';
            $extra = $nest['children']->count() - 1;
            $more = $extra > 0 ? " (+{$extra} more)" : '';
            $bits[] = "Shorter than rule {$child->ruleid} (prefix “{$childPrefix}”{$label}){$more} — that longer rule wins when it matches.";
        }

        return $bits === [] ? null : implode(' ', $bits);
    }

    public static function formatClassifyHint(array $classified): ?string
    {
        $bits = [];

        if ($classified['parents'] !== []) {
            $parent = $classified['parents'][0];
            $label = $parent['description'] !== '' ? " — {$parent['description']}" : '';
            $bits[] = "Nested under rule {$parent['ruleid']} (prefix “{$parent['prefix']}”{$label}). OpenSIPS prefers this longer prefix when both match.";
        }

        if ($classified['children'] !== []) {
            $child = $classified['children'][0];
            $label = $child['description'] !== '' ? " — {$child['description']}" : '';
            $extra = count($classified['children']) - 1;
            $more = $extra > 0 ? " (+{$extra} more)" : '';
            $bits[] = "Shorter than rule {$child['ruleid']} (prefix “{$child['prefix']}”{$label}){$more} — that longer rule wins when it matches.";
        }

        return $bits === [] ? null : implode(' ', $bits);
    }
}
