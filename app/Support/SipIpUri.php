<?php

namespace App\Support;

/**
 * Fleet Asterisk backends (dispatcher destinations / Asterisk Peers) must be
 * literal IPs — OpenSIPS reverse path matches $si against destination / attrs.
 * Carrier outbound Peers may still use DNS names.
 */
final class SipIpUri
{
    /** sip:IPv4[:port] or sip:[IPv6]:port */
    public const REGEX = '/^sip:((\[([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\])|(([0-9]{1,3}\.){3}[0-9]{1,3}))(:[0-9]{1,5})?$/';

    public const MESSAGE = 'Use a literal IP (sip:a.b.c.d:5060). DNS names break Asterisk source-IP reverse lookup — see FLEET_TRUNK_PEERING_DECISION (dispatcher destinations).';

    public static function isValid(string $uri): bool
    {
        return (bool) preg_match(self::REGEX, strtolower(trim($uri)));
    }
}
