<?php

return [
    'url' => env('FACE_PHOTO_CHECK_URL', 'http://127.0.0.1:8782'),
    'token' => env('FACE_PHOTO_CHECK_TOKEN'),
    'timeout' => 15,
    'match_threshold' => (float) env('FACE_MATCH_THRESHOLD', 0.5),
];
