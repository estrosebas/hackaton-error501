<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HackathonMapRangeSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo-feed.showcase')) {
            throw new \RuntimeException('Activa DEMO_SHOWCASE_ENABLED explícitamente.');
        }
        $this->call(HackathonDemoSeeder::class);
        $anchor = User::whereNull('demo_key')->whereKey(config('demo-feed.map_anchor_user_id'))->firstOrFail();
        $prefix = 'showcase-'.$anchor->ubigeo.'-';
        $titles = [
            'community' => ['Taller de huerto comunitario', 'Colecta de útiles escolares', 'Jornada de limpieza del pasaje', 'Intercambio de libros y juguetes', 'Reunión para cuidar las áreas verdes', 'Feria de iniciativas del barrio'],
            'emergency' => ['Simulacro: rutas seguras ante lluvias', 'Simulacro de encuentro familiar', 'Práctica de evacuación del local comunal', 'Simulacro de coordinación ante un sismo', 'Taller de preparación ante incendios', 'Simulacro de respuesta vecinal organizada'],
            'trend' => ['Tarde de cuentos y chocolate', 'Encuentro de música entre vecinos', 'Ronda de ajedrez para principiantes', 'Tarde de juegos de mesa', 'Cine familiar en comunidad', 'Festival de talentos del barrio'],
            'support' => ['Organicemos cajas para una mudanza', 'Orientación para conductores principiantes', 'Diagnóstico básico de laptops', 'Apoyo para trasladar muebles', 'Práctica para estacionar con seguridad', 'Aprendamos a respaldar documentos'],
        ];
        DB::transaction(function () use ($anchor, $prefix, $titles) {
            $n = 0;
            foreach ($titles as $category => $items) {
                foreach ($items as $slot => $title) {
                    $key = $prefix.'range-'.$category.'-'.$slot;
                    if (DB::table('map_events')->where('demo_key', $key)->exists()) {
                        $n++;

                        continue;
                    }
                    $source = DB::table('map_events')->where('demo_key', $prefix.'event-'.$category.'-'.($slot % 3))->first();
                    if (! $source) {
                        throw new \RuntimeException('Falta un evento base de la demostración.');
                    }
                    $point = DB::selectOne('SELECT ST_AsEWKT(ST_Project(ST_SetSRID(ST_MakePoint(?, ?),4326)::geography, ?::double precision, ?::double precision)::geometry) AS location', [$anchor->longitude, $anchor->latitude, [400, 650, 900, 1250, 1800, 2600][$slot], deg2rad($n * 137.5)]);
                    $values = (array) $source;
                    unset($values['id']);
                    $starts = now()->addHours(1 + $n % 12);
                    DB::table('map_events')->insert([
                        ...$values, 'demo_key' => $key, 'title' => $title,
                        'location' => DB::raw("ST_GeogFromText('".$point->location."')"),
                        'starts_at' => $starts, 'ends_at' => $starts->copy()->addHours(3),
                        'cancelled_at' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $n++;
                }
            }
        });
        $this->command?->info('Mapa demo ampliado: 36 eventos; 12 a menos de 300 m, 24 a menos de 1 km y 36 en todo el distrito, respecto a la ubicación guardada.');
    }
}
