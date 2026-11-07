<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:seed', function () {
    if (! app()->environment('local') || ! config('demo-feed.enabled')) {
        $this->info('Feed sintético desactivado (solo APP_ENV=local).');

        return 0;
    }
    $this->call('migrate', ['--force' => true]);
    $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    return 0;
})->purpose('Prepara migraciones y conversaciones sintéticas exclusivamente en desarrollo local');
