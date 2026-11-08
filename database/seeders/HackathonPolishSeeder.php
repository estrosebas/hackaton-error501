<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HackathonPolishSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo-feed.showcase')) {
            throw new \RuntimeException('Activa DEMO_SHOWCASE_ENABLED explícitamente.');
        }
        $this->call(HackathonMapRangeSeeder::class);
        $anchor = User::whereNull('demo_key')->whereKey(config('demo-feed.map_anchor_user_id'))->firstOrFail();
        $prefix = 'showcase-'.$anchor->ubigeo.'-';
        $users = User::where('demo_key', 'like', $prefix.'neighbor-%')->orderBy('id')->get();
        $photos = DB::table('map_events')->where('demo_key', 'like', $prefix.'event-%')->pluck('photo_path', 'demo_key');
        $base = Carbon::parse(DB::table('map_events')->where('demo_key', 'like', $prefix.'event-%')->min('created_at'));
        $number = fn (string $key): int => (int) hexdec(substr(hash('sha256', $key), 0, 7));
        DB::transaction(function () use ($anchor, $prefix, $users, $photos, $base, $number) {
            $drill = CommunityPost::withTrashed()->firstOrCreate(['demo_key' => $prefix.'emergency-drill'], [
                'user_id' => $users[1]->id, 'ubigeo' => $anchor->ubigeo, 'kind' => 'emergency', 'status' => 'resolved',
                'body' => 'SIMULACRO DE DEMOSTRACIÓN · Coordinación vecinal ante lluvias intensas. No hay una emergencia real. Practicamos cómo ubicar un punto seguro y organizar apoyo para personas mayores. Ejercicio concluido; consulta los próximos simulacros en el mapa. #simulacro #prevencion',
                'created_at' => $base->copy()->addMinute(), 'updated_at' => $base,
            ]);
            $drill->syncTagsWithType(['Emergencias', 'Prevención'], 'category');
            $elapsed = 0;
            foreach (CommunityPost::where('demo_key', 'like', $prefix.'%')->orderByDesc('id')->get() as $index => $post) {
                $seed = $number($post->demo_key);
                $elapsed += 8 + $seed % 157;
                $created = $base->copy()->subMinutes($elapsed);
                $post->forceFill(['created_at' => $created])->save();
                // Rebalance only synthetic reactions. Real neighbors' likes remain untouched.
                DB::table('community_reactions')->where('post_id', $post->id)->whereIn('user_id', $users->pluck('id'))->delete();
                $likes = [0, 4, 1, 7, 2, 5, 0, 3, 8, 2][$seed % 10];
                for ($i = 0; $i < $likes; $i++) {
                    DB::table('community_reactions')->insertOrIgnore(['post_id' => $post->id, 'user_id' => $users[($seed + $i * 3) % 20]->id]);
                }
                $desired = [0, 1, 3, 2, 4, 1, 0, 2][$seed % 8];
                $comments = $post->comments()->where('demo_key', 'like', $prefix.'%')->orderBy('id')->get();
                $post->comments()->whereIn('id', $comments->slice($desired)->pluck('id'))->delete();
                for ($i = $comments->count(); $i < $desired; $i++) {
                    $post->comments()->firstOrCreate(['demo_key' => $prefix.'polish-comment-'.$post->id.'-'.$i], [
                        'user_id' => $users[($seed + $i * 7 + 1) % 20]->id,
                        'body' => ['¿Qué horario te viene bien? Puedo coordinar después del trabajo.', 'Yo puedo apoyar un rato el fin de semana. Te escribo para organizarnos.', 'Gracias por compartirlo, justo estaba buscando una orientación por la zona.', 'Tengo algo de experiencia con eso; conversemos y revisamos qué hace falta.'][$i % 4],
                        'created_at' => $created->copy()->addMinutes(3 + $i * 11), 'updated_at' => $created,
                    ]);
                }
            }
            $activityIndex = 0;
            foreach (CommunityPost::where('demo_key', 'like', $prefix.'%')->where('kind', 'activity')->orderBy('id')->get() as $post) {
                $starts = $base->copy()->setTimezone('America/Lima')->startOfDay()->addDays(1 + $activityIndex)->setTime($activityIndex ? 10 : 17, $activityIndex ? 35 : 20)->utc();
                $photo = $photos[$prefix.'event-community-'.($activityIndex ? 2 : 1)];
                $post->forceFill(['activity_starts_at' => $starts->toIso8601String(), 'photo_path' => $photo, 'photo_paths' => [$photo]])->save();
                $activityIndex++;
            }
            foreach (DB::table('map_events')->where('demo_key', 'like', $prefix.'%')->get() as $event) {
                $event = DB::table('map_events')->where('id', $event->id)->lockForUpdate()->first();
                if (DB::table('map_event_attendances')->where('map_event_id', $event->id)->exists() || DB::table('empathy_rewards')->where('map_event_id', $event->id)->exists()) {
                    continue;
                }
                $seed = $number($event->demo_key);
                $distance = 71 + $seed % 206;
                if (str_contains($event->demo_key, '-range-')) {
                    $slot = (int) substr($event->demo_key, -1);
                    $distance = [340, 540, 780, 1120, 1530, 2150][$slot] + $seed % [180, 220, 190, 320, 400, 650][$slot];
                }
                $point = DB::selectOne('SELECT ST_AsEWKT(ST_Project(ST_SetSRID(ST_MakePoint(?, ?),4326)::geography, ?::double precision, ?::double precision)::geometry) AS location', [$anchor->longitude, $anchor->latitude, $distance, deg2rad(($seed % 36000) / 100)]);
                $starts = $base->copy()->addMinutes(45 + $number($event->demo_key.'time') % 2040);
                $references = ['Entrada del parque vecinal', 'Patio del local comunal', 'Jardineras del sector norte', 'Losa deportiva del barrio', 'Pasaje peatonal del sector', 'Área de juegos de la plazoleta', 'Portón del salón comunal', 'Zona de estacionamiento vecinal', 'Punto de encuentro junto a la cancha', 'Patio del taller comunitario'];
                $changes = [];
                $overrides = [
                    $prefix.'event-emergency-2' => ['community', 'Taller familiar de seguridad vial'],
                    $prefix.'range-emergency-4' => ['community', 'Taller vecinal de prevención y evacuación'],
                    $prefix.'range-support-1' => ['trend', 'Encuentro de educación vial entre vecinos'],
                ];
                if (isset($overrides[$event->demo_key])) {
                    [$category, $title] = $overrides[$event->demo_key];
                    $types = config('map-events.categories.'.$category.'.types');
                    $changes = ['category' => $category, 'title' => $title, 'type' => $types[array_key_last($types)], 'points' => config('map-events.categories.'.$category.'.points')];
                }
                DB::table('map_events')->where('id', $event->id)->update([
                    ...$changes,
                    'place' => 'DEMO · '.$references[$seed % count($references)].' · '.($anchor->neighborhood ?: $anchor->district),
                    'location' => DB::raw("ST_GeogFromText('".$point->location."')"), 'starts_at' => $starts,
                    'ends_at' => $starts->copy()->addMinutes(60 + $seed % 161),
                ]);
            }
            // Reputation belongs only to explicitly synthetic profiles, never to the real account.
            foreach ($users as $i => $neighbor) {
                for ($j = 1; $j <= $number($neighbor->demo_key) % 8; $j++) {
                    DB::table('neighbor_endorsements')->insertOrIgnore(['user_id' => $users[($i + $j) % 20]->id, 'neighbor_id' => $neighbor->id, 'created_at' => $base, 'updated_at' => $base]);
                }
            }
        });
        $this->command?->info('Demo refinada: interacciones variadas, horarios y distancias irregulares, dos actividades diferenciadas y un simulacro concluido en el feed. Se preservaron las interacciones reales y los eventos inscritos.');
    }
}
