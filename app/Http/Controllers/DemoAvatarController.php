<?php

namespace App\Http\Controllers;

class DemoAvatarController extends Controller
{
    public function __invoke(string $filename)
    {
        abort_unless(app()->environment('local') && config('demo-feed.enabled'), 404);
        abort_unless(preg_match('/^(0[1-9]|1[0-9]|20)\.jpg$/', $filename), 404);
        $path = database_path('data/demo/avatars/'.$filename);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }
}
