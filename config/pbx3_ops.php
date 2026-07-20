<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fleet ops — Fail2ban ban → Gatekeeper email
    |--------------------------------------------------------------------------
    |
    | Polls the SIP jail for newly banned IPs and POSTs fail2ban_ban events to
    | Gatekeeper (same notify plane as instance-down / misconfig REGISTER).
    | Enable with PBX3_OPS_FAIL2BAN_BAN_NOTIFY=true + Gatekeeper URL/token.
    |
    */

    'fail2ban_ban_notify_enabled' => filter_var(
        env('PBX3_OPS_FAIL2BAN_BAN_NOTIFY', false),
        FILTER_VALIDATE_BOOL
    ),

    'state_path' => env(
        'PBX3_OPS_FAIL2BAN_BAN_STATE',
        storage_path('app/ops-fail2ban-ban.json')
    ),

    /** Cap emits per poll tick (scan-storm guard). */
    'max_emits_per_run' => (int) env('PBX3_OPS_FAIL2BAN_BAN_MAX_EMITS', 10),

    'sbc_fqdn' => env('PBX3_OPS_SBC_FQDN', env('APP_URL', '')),

    'gatekeeper_url' => env('PBX3_OPS_GATEKEEPER_URL', env('PBX3_GATEKEEPER_URL', '')),

    'gatekeeper_token' => env('PBX3_OPS_GATEKEEPER_TOKEN', env('PBX3_GATEKEEPER_TOKEN', '')),

    'gatekeeper_http_verify' => filter_var(
        env('PBX3_OPS_GATEKEEPER_HTTP_VERIFY', env('PBX3_GATEKEEPER_HTTP_VERIFY', true)),
        FILTER_VALIDATE_BOOL
    ),

];
