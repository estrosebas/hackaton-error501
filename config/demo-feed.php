<?php

return [
    // APP_ENV must also be exactly "local"; this flag cannot override it.
    'enabled' => env('DEMO_FEED_ENABLED', true),
    // Explicit opt-in for the public hackathon showcase; local seeders remain local-only.
    'showcase' => env('DEMO_SHOWCASE_ENABLED', false),
    'ubigeo' => env('DEMO_FEED_UBIGEO'),
    'map_anchor_user_id' => env('DEMO_MAP_ANCHOR_USER_ID'),
];
