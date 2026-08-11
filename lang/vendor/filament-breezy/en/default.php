<?php

$base = require base_path('vendor/jeffgreco13/filament-breezy/resources/lang/en/default.php');

$base['two_factor']['code_placeholder'] = 'XXXXXX';
// Recovery codes may contain hyphens; keep a hyphen-free hint so users type the code as shown on screen.
$base['two_factor']['recovery_code_placeholder'] = 'abcdef98765';

return $base;
