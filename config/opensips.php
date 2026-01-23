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
];
