<?php

return [
    /*
    | Fleet control-plane → SBC admin API (S8.10 SbcFleetAdapter backend).
    | Bearer token for /api/fleet/* — not Filament session auth.
    */
    'service_token' => env('PBX3_FLEET_SERVICE_TOKEN', ''),
];
