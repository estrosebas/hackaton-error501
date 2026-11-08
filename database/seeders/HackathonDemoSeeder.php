<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\CommunityPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HackathonDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo-feed.showcase')) {
            throw new \RuntimeException('Activa DEMO_SHOWCASE_ENABLED explícitamente para sembrar la demostración.');
        }
        $anchor = User::whereNull('demo_key')->whereKey(config('demo-feed.map_anchor_user_id'))->whereNotNull('ubigeo')->whereNotNull('latitude')->whereNotNull('longitude')->firstOrFail();
        $zone = $anchor->ubigeo;
        $prefix = 'showcase-'.$zone.'-';
        $fixture = require database_path('data/demo/feed.php');
        $extra = require database_path('data/demo/showcase.php');
        $photoDir = is_file(database_path('data/demo/showcase-photos/manifest.json')) ? 'showcase-photos' : 'events';
        $images = collect(json_decode(file_get_contents(database_path('data/demo/'.$photoDir.'/manifest.json')), true, flags: JSON_THROW_ON_ERROR)['images'])->keyBy('key');
        foreach (['avatars', $photoDir] as $directory) {
            $manifest = json_decode(file_get_contents(database_path('data/demo/'.$directory.'/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach ($manifest['images'] as $image) {
                $path = database_path('data/demo/'.$directory.'/'.basename($image['file']));
                if (! is_file($path) || hash_file('sha256', $path) !== $image['sha256']) {
                    throw new \RuntimeException('Falta una imagen de demostración válida: '.$image['file']);
                }
            }
        }
        $photo = function (string $key) use ($images, $photoDir): string {
            $image = $images->get($key);
            $path = 'demo-showcase/'.$image['sha256'].'.webp';
            if (! Storage::disk('local')->exists($path)) {
                $created = app(CommunityPhoto::class)->store(new UploadedFile(database_path('data/demo/'.$photoDir.'/'.basename($image['file'])), $image['file'], 'image/jpeg', null, true));
                if (! Storage::disk('local')->move($created, $path)) {
                    throw new \RuntimeException('No se pudo preparar la foto de demostración.');
                }
            }

            return $path;
        };
        DB::transaction(function () use ($anchor, $zone, $prefix, $fixture, $extra, $photo) {
            $users = [];
            foreach ($fixture['names'] as $i => $name) {
                $user = User::firstOrCreate(['demo_key' => $prefix.'neighbor-'.$i], [
                    'name' => $name, 'email' => $prefix.'neighbor-'.$i.'@demo.masi.test', 'password' => Str::random(64),
                    'demo_avatar' => sprintf('%02d.jpg', $i + 1), 'ubigeo' => $zone, 'district' => $anchor->district,
                    'region' => $anchor->region, 'province' => $anchor->province, 'neighborhood' => $anchor->neighborhood ?: $anchor->district,
                    'neighbor_profile' => ['bio' => 'Perfil sintético para la demostración de masi.', 'skills' => [config('neighbor-profile.skills')[$i % 8]], 'interests' => ['Voluntariado'], 'availability' => 'A coordinar'],
                ]);
                $user->assignRole('neighbor');
                $users[] = $user;
            }
            $posts = $fixture['posts'];
            foreach ($extra as [$category, $topic, $bodies]) {
                foreach ($bodies as $i => $body) {
                    $posts[] = ['kind' => ['need_help', 'offer_help', 'moment'][$i], 'body' => $body.' #'.$topic, 'categories' => [$category], 'topic' => $topic];
                }
            }
            foreach (array_reverse($posts, true) as $i => $item) {
                $created = now()->subMinutes(8 + $i * 41);
                $image = match ($item['topic'] ?? '') {
                    'comida' => 'community-0', 'jardineria' => 'community-1',
                    default => str_contains($item['body'], 'computadora') || str_contains($item['body'], 'laptop') ? 'support-2' : null,
                };
                if (in_array('Mecánica', $item['categories'], true)) {
                    $image = 'support-1';
                }
                if (isset($item['title'])) {
                    $image = 'community-2';
                }
                $post = CommunityPost::withTrashed()->firstOrCreate(['demo_key' => $prefix.'post-'.$i], [
                    'user_id' => $users[$i % 20]->id, 'ubigeo' => $zone, 'kind' => $item['kind'], 'body' => $item['body'],
                    'photo_path' => $image ? $photo($image) : null, 'created_at' => $created, 'updated_at' => $created,
                    'activity_title' => $item['title'] ?? null, 'activity_location' => $item['location'] ?? null,
                    'activity_starts_at' => isset($item['title']) ? now()->addDays(2)->toIso8601String() : null,
                    'latitude' => isset($item['title']) ? $anchor->latitude : null, 'longitude' => isset($item['title']) ? $anchor->longitude : null,
                ]);
                if (! $post->wasRecentlyCreated) {
                    continue;
                }
                $post->syncTagsWithType($item['categories'], 'category');
                preg_match_all('/#([\p{L}\p{N}_]+)/u', $item['body'], $matches);
                $post->syncTagsWithType($matches[1], 'hashtag');
                if (isset($item['topic'])) {
                    $post->syncTagsWithType([$item['topic']], 'feed_topic');
                }
                $comments = $item['comments'] ?? [
                    [($i + 3) % 20, ['¡Me interesa! Te escribo para coordinar el horario.', 'Yo también quería aprender. ¿Podemos organizarnos entre varios?', 'Qué buena idea, vecino. Cuenta conmigo para apoyar.'][$i % 3]],
                    [($i + 7) % 20, ['Puedo ayudar el fin de semana. Coordinemos por mensaje.', 'Tengo algo de experiencia, podemos revisar primero y ver qué hace falta.', 'Gracias por compartirlo. Me viene bien tener a alguien cerca para consultar.'][$i % 3]],
                ];
                foreach ($comments as $j => [$author, $body]) {
                    $post->comments()->create(['demo_key' => $prefix.'comment-'.$i.'-'.$j, 'user_id' => $users[$author]->id, 'body' => $body, 'created_at' => $created->copy()->addMinutes(2 + $j * 2), 'updated_at' => $created]);
                }
                foreach ([($i + 2) % 20, ($i + 5) % 20, ($i + 9) % 20] as $liker) {
                    DB::table('community_reactions')->insertOrIgnore(['post_id' => $post->id, 'user_id' => $users[$liker]->id]);
                }
            }
            $titles = [
                'community' => ['Pollada solidaria del barrio', 'Voluntariado: cuidemos el parque', 'Encuentro de propuestas vecinales'],
                'emergency' => ['Simulacro de preparación ante inundación', 'Simulacro vecinal de evacuación', 'Práctica de seguridad vial'],
                'trend' => ['Chocolatada y cuentos para niños', 'Tarde de cumpleaños comunitarios', 'Encuentro infantil de ajedrez'],
                'support' => ['Ayuda para organizar una mudanza', 'Práctica de conducción acompañada', 'Revisión de computadoras entre vecinos'],
            ];
            $n = 0;
            foreach ($titles as $category => $items) {
                foreach ($items as $slot => $title) {
                    $key = $prefix.'event-'.$category.'-'.$slot;
                    if (DB::table('map_events')->where('demo_key', $key)->exists()) {
                        $n++;

                        continue;
                    }
                    $starts = now()->addMinutes(45 + $n * 90);
                    $cost = $category === 'support' && $slot === 2;
                    $location = DB::selectOne('SELECT ST_AsEWKT(ST_Project(ST_SetSRID(ST_MakePoint(?, ?),4326)::geography, ?::double precision, ?::double precision)::geometry) AS point', [$anchor->longitude, $anchor->latitude, 80 + $n * 15, deg2rad($n * 137.5)]);
                    DB::table('map_events')->insert([
                        'demo_key' => $key, 'user_id' => $users[$n]->id, 'ubigeo' => $zone, 'category' => $category,
                        'type' => config('map-events.categories.'.$category.'.types')[$slot] ?? null, 'title' => $title,
                        'description' => 'DEMO · Evento ficticio para presentar masi. '.($category === 'emergency' ? 'Es un simulacro; no existe una emergencia real. ' : '').'Imagen ilustrativa; el lugar del encuentro es de demostración.',
                        'place' => 'Encuentro de demostración · '.($anchor->neighborhood ?: $anchor->district),
                        'starts_at' => $starts, 'ends_at' => $starts->copy()->addHours(3), 'has_cost' => $cost,
                        'points' => $cost ? 0 : config('map-events.categories.'.$category.'.points'), 'photo_path' => $photo($category.'-'.$slot),
                        'location' => DB::raw("ST_GeogFromText('".$location->point."')"), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $n++;
                }
            }
        });
        $this->command?->info('Demostración preparada: 20 perfiles sintéticos, 72 publicaciones y 12 eventos. No se otorgaron puntos ni se modificaron cuentas reales.');
    }
}
