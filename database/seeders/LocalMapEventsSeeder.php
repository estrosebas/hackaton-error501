<?php

namespace Database\Seeders;

use App\Models\MapEvent;
use App\Models\User;
use App\Services\CommunityPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LocalMapEventsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('demo-feed.enabled')) {
            return;
        }
        $query = User::whereNull('demo_key')->whereNotNull('ubigeo')->whereNotNull('latitude')->whereNotNull('longitude');
        if ($id = config('demo-feed.map_anchor_user_id')) {
            $query->whereKey($id);
        }
        $anchor = $query->orderByRaw('location_captured_at DESC NULLS LAST')->orderByDesc('id')->first();
        if (! $anchor) {
            $this->command?->info('Mapa demo omitido: falta una cuenta real con distrito y ubicación guardada.');

            return;
        }
        $users = User::whereNotNull('demo_key')->orderBy('demo_key')->get();
        if ($users->count() < 12) {
            $this->command?->warn('Mapa demo omitido: carga primero LocalCommunitySeeder.');

            return;
        }
        $manifest = json_decode(file_get_contents(database_path('data/demo/events/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $images = collect($manifest['images'])->keyBy('key');
        foreach ($images as $image) {
            $path = database_path('data/demo/events/'.basename($image['file']));
            if (! is_file($path) || hash_file('sha256', $path) !== $image['sha256']) {
                throw new \RuntimeException('Falta una foto ilustrativa válida: '.$image['file'].'. Ejecuta scripts/download-demo-event-photos.ps1.');
            }
        }
        $fixtures = [
            'community' => [
                ['Pollada por la salud de una vecina', 'Pollada pro salud', 'Nos organizamos para apoyar los gastos de recuperación. Puedes colaborar preparando y repartiendo las porciones.', false],
                ['Voluntariado: recuperemos nuestro parque', 'Voluntariado', 'Trae guantes y ganas de ayudar. Limpiaremos las áreas verdes y sembraremos algunas plantas.', false],
                ['Encuentro para mejorar el barrio', 'Otro evento comunitario', 'Compartamos propuestas para cuidar los espacios comunes. Todas las ideas son bienvenidas.', false],
            ],
            'emergency' => [
                ['Simulacro de apoyo ante inundación', 'Inundación', 'Práctica vecinal para ubicar rutas seguras y organizar suministros. Es un simulacro, no una emergencia real.', false],
                ['Simulacro: respuesta ante un incendio', 'Incendio', 'Punto de encuentro para practicar evacuación y coordinación vecinal. Es un simulacro, no hay un incendio real.', false],
                ['Simulacro de apoyo ante un accidente', 'Accidente', 'Actividad de preparación vecinal para coordinar ayuda. Es un simulacro, no hay personas accidentadas.', false],
            ],
            'trend' => [
                ['Chocolatada para los peques del barrio', 'Chocolatada para niños', 'Compartiremos chocolate y pan. Puedes ayudar organizando las mesas y repartiendo las tazas.', false],
                ['Un cumpleaños entre vecinos', 'Fiesta de cumpleaños', 'Una tarde para compartir, conversar y celebrar en comunidad. Trae tus ganas de pasarla bien.', false],
                ['Ajedrez infantil en el parque', 'Concurso de ajedrez infantil', 'Encuentro amistoso para niñas y niños que quieran aprender o practicar. No hace falta experiencia.', false],
            ],
            'support' => [
                ['¿Me ayudan a mover unas cajas?', 'Ayuda para mudanza', 'Necesito una mano para trasladar unas cajas y una mesa. Apoyo vecinal sin costo.', false],
                ['Practicamos conducción entre vecinos', 'Aprender a conducir', 'Coordinemos una sesión de orientación en un espacio autorizado, con acompañamiento responsable. Sin costo.', false],
                ['Revisión de computadoras del barrio', 'Reparar una computadora', 'Puedo revisar tu computadora y orientarte sobre una reparación. Servicio con costo a coordinar; no otorga puntos.', true],
            ],
        ];
        $index = 0;
        foreach ($fixtures as $category => $items) {
            foreach ($items as $slot => [$title, $type, $description, $hasCost]) {
                $key = 'map-demo-'.$anchor->id.'-'.$category.'-'.$slot;
                DB::transaction(function () use ($anchor, $users, $images, $category, $slot, $title, $type, $description, $hasCost, $key, $index) {
                    // Serializes repeated seeds without changing any real account.
                    User::whereKey($anchor->id)->lockForUpdate()->firstOrFail();
                    $existing = MapEvent::where('demo_key', $key)->first();
                    $image = $images->get($category.'-'.$slot);
                    if (! $image) {
                        throw new \RuntimeException('Falta la imagen del evento '.$category.'-'.$slot);
                    }
                    $photo = 'demo-event-photos/'.$image['sha256'].'.webp';
                    if (! Storage::disk('local')->exists($photo)) {
                        $source = database_path('data/demo/events/'.basename($image['file']));
                        $temporary = app(CommunityPhoto::class)->store(new UploadedFile($source, $image['file'], 'image/jpeg', null, true));
                        if (! Storage::disk('local')->move($temporary, $photo)) {
                            throw new \RuntimeException('No se pudo preparar la imagen del evento.');
                        }
                    }
                    if ($existing && (DB::table('map_event_attendances')->where('map_event_id', $existing->id)->exists() || DB::table('empathy_rewards')->where('map_event_id', $existing->id)->exists())) {
                        // Only replace the demo illustration; keep attendance evidence and event details.
                        DB::table('map_events')->where('id', $existing->id)->update([
                            'photo_path' => $photo, 'demo_photo_hash' => $image['sha256'],
                            'description' => str_replace('La imagen corresponde a un vecino sintético.', 'Imagen ilustrativa del espacio o actividad; no corresponde al punto real del mapa.', $existing->description),
                        ]);

                        return;
                    }
                    $author = $users[$index];
                    $meters = $slot === 0 ? 65 + intdiv($index, 3) * 8 : 200 + $index * 25;
                    $bearing = deg2rad($index * 137.5);
                    $location = DB::selectOne('SELECT ST_AsEWKT(ST_Project(ST_SetSRID(ST_MakePoint(?, ?),4326)::geography, ?::double precision, ?::double precision)::geometry) AS point', [$anchor->longitude, $anchor->latitude, $meters, $bearing]);
                    $starts = $slot === 0 ? now()->subMinutes(15) : now()->addHours($slot === 1 ? 2 : 26);
                    $values = [
                        'user_id' => $author->id, 'ubigeo' => $anchor->ubigeo, 'category' => $category, 'type' => $type,
                        'title' => $title, 'description' => 'DEMO · Evento sintético para probar el mapa. '.$description.' Imagen ilustrativa del espacio o actividad; no corresponde al punto real del mapa.',
                        'place' => 'Punto de encuentro de demostración · '.($anchor->neighborhood ?: $anchor->district),
                        'starts_at' => $starts, 'ends_at' => $starts->copy()->addHours(4), 'has_cost' => $hasCost,
                        'points' => $category === 'support' && $hasCost ? 0 : config('map-events.categories.'.$category.'.points'),
                        'photo_path' => $photo, 'demo_photo_hash' => $image['sha256'], 'updated_at' => now(),
                        'location' => DB::raw("ST_GeogFromText('".$location->point."')"),
                    ];
                    if ($existing) {
                        DB::table('map_events')->where('id', $existing->id)->update($values);
                    } else {
                        DB::table('map_events')->insert([...$values, 'demo_key' => $key, 'created_at' => now()]);
                    }
                });
                $index++;
            }
        }
        $this->command?->info('Mapa demo preparado: 12 eventos (3 por categoría), a 65–475 m de la ubicación guardada. No se acreditaron puntos.');
    }
}
