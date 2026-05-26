<?php

return [
    'availability_cache_ttl_seconds' => (int) env('AVAILABILITY_CACHE_TTL_SECONDS', 10),
    'availability_lock_seconds' => (int) env('AVAILABILITY_LOCK_SECONDS', 5),
    'availability_wait_seconds' => (int) env('AVAILABILITY_WAIT_SECONDS', 3),
    'hold_ttl_seconds' => (int) env('HOLD_TTL_SECONDS', 300),
];
