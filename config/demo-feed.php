<?php

return [
    // APP_ENV must also be exactly "local"; this flag cannot override it.
    'enabled' => env('DEMO_FEED_ENABLED', true),
    'ubigeo' => env('DEMO_FEED_UBIGEO'),
];
