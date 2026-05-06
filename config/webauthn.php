<?php

return [
    'rp_id'   => env('WEBAUTHN_RP_ID', 'localhost'),
    'rp_name' => env('WEBAUTHN_RP_NAME', 'Data Tracker'),
    'origin'  => env('WEBAUTHN_ORIGIN', 'http://localhost:8000'),
];
