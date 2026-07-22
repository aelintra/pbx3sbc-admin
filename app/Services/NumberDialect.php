<?php

namespace App\Services;

/**
 * PSTN number dialect: normalize carrier wire forms to +E.164 / digit keys,
 * and render +E.164 for a Peer preset. Spec: NUMBER_DIALECT_REQUIREMENTS.md
 */
class NumberDialect
{
    public const PRESET_NONE = 'none';

    public const PRESET_UK_MAGRATHEA = 'uk-magrathea';

    public const PRESET_UK_GAMMA = 'uk-gamma';

    public const PRESET_STRICT_PLUS_E164 = 'strict-plus-e164';

    /** @var array<string, array<string, mixed>> */
    private const PRESETS = [
        self::PRESET_UK_MAGRATHEA => [
            'label' => 'UK — Magrathea',
            'inbound_accept' => ['plus_e164', 'e164_digits', 'uk_national', 'uk_idd'],
            'outbound_dial' => 'plus_e164',
            'outbound_cli_network' => ['format' => 'plus_e164', 'header' => 'paid'],
            'outbound_cli_presentation' => ['format' => 'plus_e164', 'header' => 'rpid'],
            'default_cc' => '44',
            'privacy' => 'privacy_id',
        ],
        self::PRESET_UK_GAMMA => [
            'label' => 'UK — Gamma',
            'inbound_accept' => ['plus_e164', 'e164_digits', 'uk_national', 'uk_idd'],
            'outbound_dial' => 'plus_e164',
            'outbound_cli_network' => ['format' => 'plus_e164', 'header' => 'paid'],
            'outbound_cli_presentation' => ['format' => 'same', 'header' => 'from'],
            'default_cc' => '44',
            'privacy' => 'privacy_id',
        ],
        self::PRESET_STRICT_PLUS_E164 => [
            'label' => 'Strict +E.164 (Teams-style)',
            'inbound_accept' => ['plus_e164'],
            'outbound_dial' => 'plus_e164',
            'outbound_cli_network' => ['format' => 'plus_e164', 'header' => 'paid'],
            'outbound_cli_presentation' => ['format' => 'same', 'header' => 'from'],
            'default_cc' => '44',
            'privacy' => 'privacy_id',
        ],
        self::PRESET_NONE => [
            'label' => 'None (best-effort UK inbound)',
            'inbound_accept' => ['plus_e164', 'e164_digits', 'uk_national', 'uk_idd'],
            'outbound_dial' => 'passthrough',
            'outbound_cli_network' => ['format' => 'passthrough', 'header' => 'from'],
            'outbound_cli_presentation' => ['format' => 'same', 'header' => 'from'],
            'default_cc' => '44',
            'privacy' => 'privacy_id',
        ],
    ];

    /**
     * @return array<string, string> preset id => label
     */
    public static function presetOptions(): array
    {
        $out = [];
        foreach (self::PRESETS as $id => $cfg) {
            $out[$id] = (string) $cfg['label'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolve(?string $preset): array
    {
        $id = trim((string) $preset);
        if ($id === '' || ! isset(self::PRESETS[$id])) {
            return self::PRESETS[self::PRESET_NONE];
        }

        return self::PRESETS[$id];
    }

    public static function isKnownPreset(?string $preset): bool
    {
        $id = trim((string) $preset);

        return $id !== '' && isset(self::PRESETS[$id]);
    }

    /**
     * Normalize a raw userpart to +E.164 using the preset's inbound_accept list.
     *
     * @throws \InvalidArgumentException when no parser matches
     */
    public static function normalizeToPlusE164(string $raw, ?string $preset = self::PRESET_NONE): string
    {
        $cfg = self::resolve($preset);
        $s = self::sanitizeUserpart($raw);
        if ($s === '') {
            throw new \InvalidArgumentException('empty number');
        }

        foreach ($cfg['inbound_accept'] as $parser) {
            $digits = self::parseToDigits($s, (string) $parser, (string) $cfg['default_cc']);
            if ($digits !== null) {
                return '+'.$digits;
            }
        }

        throw new \InvalidArgumentException("number does not match dialect parsers: {$s}");
    }

    /**
     * Digit-only E.164 key (inventory / drouting prefix).
     */
    public static function toE164Key(string $raw, ?string $preset = self::PRESET_NONE): string
    {
        return ltrim(self::normalizeToPlusE164($raw, $preset), '+');
    }

    /**
     * Render a +E.164 (or raw) number for outbound dialled R-URI.
     */
    public static function renderDial(string $plusOrRaw, ?string $preset = self::PRESET_NONE): string
    {
        $cfg = self::resolve($preset);
        $plus = self::ensurePlusE164($plusOrRaw, (string) $cfg['default_cc']);

        return self::renderFormat($plus, (string) $cfg['outbound_dial'], (string) $cfg['default_cc']);
    }

    /**
     * Render CLI for network number (PAID / From).
     *
     * @return array{user: string, header: string}
     */
    public static function renderCliNetwork(string $plusOrRaw, ?string $preset = self::PRESET_NONE): array
    {
        $cfg = self::resolve($preset);
        $spec = $cfg['outbound_cli_network'];
        $plus = self::ensurePlusE164($plusOrRaw, (string) $cfg['default_cc']);
        $user = self::renderFormat($plus, (string) $spec['format'], (string) $cfg['default_cc']);

        return ['user' => $user, 'header' => (string) $spec['header']];
    }

    /**
     * Render CLI for presentation number. If format is "same", returns network render.
     *
     * @return array{user: string, header: string}
     */
    public static function renderCliPresentation(
        string $presentationOrRaw,
        string $networkPlusOrRaw,
        ?string $preset = self::PRESET_NONE
    ): array {
        $cfg = self::resolve($preset);
        $spec = $cfg['outbound_cli_presentation'];
        if (($spec['format'] ?? '') === 'same') {
            $net = self::renderCliNetwork($networkPlusOrRaw, $preset);

            return ['user' => $net['user'], 'header' => (string) $spec['header']];
        }
        $plus = self::ensurePlusE164($presentationOrRaw, (string) $cfg['default_cc']);
        $user = self::renderFormat($plus, (string) $spec['format'], (string) $cfg['default_cc']);

        return ['user' => $user, 'header' => (string) $spec['header']];
    }

    /**
     * Example strings for Filament helper text.
     *
     * @return list<string>
     */
    public static function examples(?string $preset): array
    {
        $id = trim((string) $preset);
        if ($id === '' || $id === self::PRESET_NONE) {
            return [
                'Inbound: accepts +E.164, digits, UK 0…, UK 00… (best-effort)',
                'Outbound dial: unchanged (passthrough after routing)',
            ];
        }
        try {
            $inNat = self::normalizeToPlusE164('01924918076', $id);
            $dial = self::renderDial($inNat, $id);
            $cli = self::renderCliNetwork($inNat, $id);

            return [
                "Inbound example: 01924918076 → {$inNat}",
                "Outbound dial: {$dial}",
                'Outbound CLI ('.$cli['header']."): {$cli['user']}",
            ];
        } catch (\InvalidArgumentException) {
            return ['Preset loaded — see NUMBER_DIALECT_REQUIREMENTS.md'];
        }
    }

    public static function sanitizeUserpart(string $raw): string
    {
        $s = trim($raw);
        // SIP URI → userpart
        if (str_starts_with(strtolower($s), 'sip:')) {
            $s = substr($s, 4);
            $s = explode('@', $s, 2)[0];
        }
        // Drop visual separators; keep leading +
        $s = preg_replace('/[\s\-().]/', '', $s) ?? $s;

        return $s;
    }

    /**
     * @return string|null digit E.164 without +
     */
    private static function parseToDigits(string $s, string $parser, string $defaultCc): ?string
    {
        return match ($parser) {
            'plus_e164' => preg_match('/^\+[1-9]\d{1,14}$/', $s)
                ? ltrim($s, '+')
                : null,
            'e164_digits' => preg_match('/^[1-9]\d{1,14}$/', $s) ? $s : null,
            'uk_national' => preg_match('/^0([1-9]\d{8,9})$/', $s, $m)
                ? $defaultCc.$m[1]
                : null,
            'uk_idd' => preg_match('/^00([1-9]\d{1,14})$/', $s, $m) ? $m[1] : null,
            default => null,
        };
    }

    private static function ensurePlusE164(string $raw, string $defaultCc): string
    {
        $s = self::sanitizeUserpart($raw);
        if ($s === '') {
            throw new \InvalidArgumentException('empty number');
        }
        // Already +E.164
        if (preg_match('/^\+[1-9]\d{1,14}$/', $s)) {
            return $s;
        }
        // Try UK-friendly coercion when rendering from node habits
        foreach (['plus_e164', 'e164_digits', 'uk_national', 'uk_idd'] as $parser) {
            $digits = self::parseToDigits($s, $parser, $defaultCc);
            if ($digits !== null) {
                return '+'.$digits;
            }
        }
        throw new \InvalidArgumentException("cannot coerce to +E.164: {$s}");
    }

    private static function renderFormat(string $plusE164, string $format, string $defaultCc): string
    {
        $digits = ltrim($plusE164, '+');

        return match ($format) {
            'plus_e164' => '+'.$digits,
            'e164_digits' => $digits,
            'uk_national' => self::toUkNational($digits, $defaultCc),
            'uk_idd' => '00'.$digits,
            'passthrough' => $plusE164,
            'same' => $plusE164,
            default => $plusE164,
        };
    }

    private static function toUkNational(string $digits, string $defaultCc): string
    {
        if (str_starts_with($digits, $defaultCc)) {
            return '0'.substr($digits, strlen($defaultCc));
        }

        return $digits;
    }
}
