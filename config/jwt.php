<?php

return [
    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),
    'ttl_seconds' => (int) env('JWT_TTL_SECONDS', 3600),
];
