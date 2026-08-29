<?php

return [
    /*
    | Fleet control-plane → SBC admin API (S8.10 SbcFleetAdapter backend).
    | Bearer token for /api/fleet/* — not Filament session auth.
    |
    | Non-empty token ⇒ fleet-joined Magrathea: Filament hides inbound Number
    | route authoring (use Fleet → DIDs). Projector + /api/fleet/project-dids
    | still write groupid 1. Empty token ⇒ general/standalone SBC (both directions).
    */
    'service_token' => env('PBX3_FLEET_SERVICE_TOKEN', ''),
];
