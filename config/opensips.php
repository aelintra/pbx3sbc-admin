<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenSIPS Management Interface URL
    |--------------------------------------------------------------------------
    |
    | The URL for the OpenSIPS Management Interface (MI) endpoint.
    | This is used to send commands to OpenSIPS, such as reloading
    | modules after database changes.
    |
    | Example: http://192.168.1.58:8888/mi
    |
    */

    'mi_url' => env('OPENSIPS_MI_URL', 'http://127.0.0.1:8888/mi'),

    /*
    |--------------------------------------------------------------------------
    | OpenSIPS config path (read-only UI)
    |--------------------------------------------------------------------------
    |
    | Used by DialogTimeoutReader / System → Call limits to display
    | modparam("dialog", "default_timeout", …). Not written by Filament.
    |
    */
    'cfg_path' => env('OPENSIPS_CFG_PATH', '/etc/opensips/opensips.cfg'),
];
