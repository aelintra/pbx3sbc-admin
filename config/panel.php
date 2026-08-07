<?php

return [
    'inactivity_timeout_minutes' => (float) env('PBX3_ADMIN_INACTIVITY_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | TOTP issuer (authenticator app label)
    |--------------------------------------------------------------------------
    |
    | Shown as the issuer in otpauth QR codes. Keep distinct from future SPA /
    | instance MFA (e.g. "Aelintra PBX") so the same email yields two clear rows.
    |
    */
    'totp_issuer' => env('PBX3_TOTP_ISSUER', 'Aelintra SBC'),
];
