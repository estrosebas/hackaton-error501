<?php

use App\Services\EmpathyRewards;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('events:award-points', function () {
    $count = 0;
    DB::table('map_event_attendances')->whereNotNull('verified_at')->whereNull('processed_at')->orderBy('id')->chunkById(100, function ($rows) use (&$count) {
        foreach ($rows as $row) {
            if (app(EmpathyRewards::class)->process($row->id)) {
                $count++;
            }
        }
    });
    $this->info("Asistencias procesadas: $count");
})->purpose('Acredita puntos de empatía por asistencias verificadas sin duplicados');

Schedule::command('events:award-points')->everyMinute()->withoutOverlapping();

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
