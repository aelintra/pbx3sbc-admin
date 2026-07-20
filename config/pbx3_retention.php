<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SBC MySQL aging (append-only tables)
    |--------------------------------------------------------------------------
    |
    | Spec: pbx3/pbx3-directory/docs/SBC_DATA_RETENTION_REQUIREMENTS.md
    | Purge-only v1 — no cold export. Root cron; not Filament-triggered delete.
    | Filament Logs → Data retention writes override_path (WS3).
    |
    */

    'security_events' => [
        'local_days' => (int) env('PBX3_SBC_SECURITY_EVENTS_LOCAL_DAYS', 30),
        'batch_size' => (int) env('PBX3_SBC_PURGE_BATCH_SIZE', 1000),
    ],

    'acc' => [
        'local_days' => (int) env('PBX3_SBC_ACC_LOCAL_DAYS', 90),
        'batch_size' => (int) env('PBX3_SBC_PURGE_BATCH_SIZE', 1000),
    ],

    'override_path' => env(
        'PBX3_SBC_RETENTION_OVERRIDE',
        storage_path('app/pbx3-retention-override.json')
    ),

    'status_path' => env(
        'PBX3_SBC_RETENTION_STATUS',
        storage_path('app/pbx3-retention-status.json')
    ),

];
