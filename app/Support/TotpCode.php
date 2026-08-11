<?php

namespace App\Support;

/**
 * Authenticator apps show codes as 123 456 or 123-456; Breezy placeholders
 * used to ghost XXX-XXX and invite typing the hyphen. Normalize to digits.
 */
final class TotpCode
{
    public static function digitsOnly(?string $code): string
    {
        return preg_replace('/\D+/', '', (string) $code) ?? '';
    }
}
