<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\MapEvent;
use App\Models\User;
use App\Services\CommunityPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Fictional showcase stories. Stock images illustrate activities, not real local venues. */
class NeighborhoodStoriesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo-feed.showcase')) {
            throw new \RuntimeException('Activa DEMO_SHOWCASE_ENABLED para sembrar estas historias ficticias.');
        }
        $anchor = User::whereNull('demo_key')->whereKey(config('demo-feed.map_anchor_user_id'))
            ->whereNotNull('ubigeo')->whereNotNull('latitude')->whereNotNull('longitude')->firstOrFail();
        $neighbors = User::whereNotNull('demo_key')->where('ubigeo', $anchor->ubigeo)->orderBy('id')->limit(10)->get();
        if ($neighbors->count() < 10) {
            throw new \RuntimeException('Carga primero los perfiles de HackathonDemoSeeder.');
        }
        $stories = [
            [
                'key' => 'chess', 'name' => 'Ernesto Valdivia', 'avatar' => '18.jpg',
                'bio' => 'Vecino jubilado que comparte su afición por el ajedrez. Personaje sintético de demostración.',
                'title' => 'Ajedrez en el parque con don Ernesto y doña Carmen',
                'body' => "¡Buenos días, vecinos! Soy don Ernesto, tengo 72 años y con mi esposa Carmen queremos abrir un pequeño taller de ajedrez en el parque.\nVamos a enseñar desde cómo se mueven las piezas hasta nuestras primeras partidas. Pueden venir niños con un adulto, jóvenes y otros abuelitos; nunca es tarde para aprender. Es gratuito y no hace falta saber jugar.\nLlevaremos dos tableros. Si alguien tiene uno para prestar, nos vendría muy bien. Inscríbanse en la actividad y coordinamos por aquí. #ajedrez #talleres #adultosmayores #comunidad",
                'categories' => ['Ajedrez', 'Clases', 'Educación', 'Adultos mayores'], 'topics' => ['aprendizaje', 'voluntariado'],
                'category' => 'community', 'type' => 'Otro evento comunitario', 'distance' => 135, 'bearing' => 48,
                'photo' => 23894011,
                'source' => 'https://www.pexels.com/photo/giant-chess-pieces-standing-in-a-park-23894011/',
                'comments' => [
                    'Don Ernesto me enseñó a jugar cuando era niña. Tiene una paciencia enorme; le dejo mi visto bueno y llevo a mi sobrino.',
                    'Yo puedo prestar dos tableros y unas sillas plegables. Los acerco antes de comenzar para que puedan acomodarse.',
                    '¡Qué linda iniciativa! Mi papá tiene 76 y quiere volver a jugar. ¿Podemos ir juntos aunque estemos un poco oxidados?',
                    'Claro, vecina, acá todos estamos para aprender y pasar un buen rato. Carmen llevará unas fichas para practicar los movimientos.',
                ],
            ],
            [
                'key' => 'kites', 'name' => 'Manuel Cárdenas', 'avatar' => '10.jpg',
                'bio' => 'Artesano de cometas hechas a mano. Personaje sintético de demostración.',
                'title' => 'Cometas de don Manuel: hechas a mano y a volar en el parque',
                'body' => "Hola, vecinos. Soy don Manuel y fabrico cometas en mi casa, una por una, con varillas livianas, papel de colores y bastante cariño. Llevo años haciéndolas y ahora quiero compartirlas con el barrio.\nTengo pequeñas desde S/ 12 y grandes desde S/ 18. También puedo hacer una con los colores que les gusten. Pueden escribirme por aquí para coordinar; no publico mi dirección de casa.\nNos encontraremos en el parque para mostrar cómo se arman y se hacen volar. La demostración es gratis y comprar es opcional. ¡Vengan con sus peques! #cometas #artesania #hechoamano #emprendimientos",
                'categories' => ['Artesanía', 'Cometas', 'Emprendimientos', 'Familia'], 'topics' => ['emprendimientos', 'arte'],
                'category' => 'trend', 'type' => 'Otra tendencia', 'distance' => 218, 'bearing' => 232,
                'photo' => 32646282,
                'source' => 'https://www.pexels.com/photo/colorful-kites-flying-high-in-clear-sky-32646282/',
                'comments' => [
                    'Le compré una para mi hija y quedó feliz. Se nota que las hace con cuidado; recomendado, don Manuel. Le dejo mi visto bueno vecinal.',
                    'Mi cometa quedó enganchada y don Manuel me ayudó a reparar la cola sin cobrarme. Es muy amable con los niños.',
                    '¿Puede hacer una roja y blanca? Me gustaría llevar dos para mis sobrinos. Le escribo por mensaje para coordinar.',
                    'Sí, vecina, roja y blanca queda bonita. Escríbame y vemos el tamaño. En el parque también les enseño a cuidar el hilo.',
                ],
            ],
        ];
        foreach ($stories as $index => $story) {
            $photo = $this->photo($story['photo']);
            DB::transaction(function () use ($anchor, $neighbors, $index, $story, $photo) {
                $key = 'showcase-'.$anchor->ubigeo.'-story-'.$story['key'];
                $author = User::firstOrCreate(['demo_key' => $key.'-author'], [
                    'name' => $story['name'], 'email' => $key.'@demo.masi.test', 'password' => Str::random(64),
                    'demo_avatar' => $story['avatar'], 'ubigeo' => $anchor->ubigeo,
                    'region' => $anchor->region, 'province' => $anchor->province, 'district' => $anchor->district,
                    'neighborhood' => $anchor->neighborhood ?: $anchor->district,
                    'neighbor_profile' => ['bio' => $story['bio'], 'skills' => [$story['categories'][0]], 'interests' => [$story['categories'][0]], 'availability' => 'A coordinar'],
                ]);
                $author->assignRole('neighbor');
                $location = DB::selectOne('SELECT ST_Y(p::geometry) AS latitude, ST_X(p::geometry) AS longitude FROM (SELECT ST_Project(ST_SetSRID(ST_MakePoint(?,?),4326)::geography,?::double precision,?::double precision) AS p) t', [$anchor->longitude, $anchor->latitude, $story['distance'], deg2rad($story['bearing'])]);
                $starts = now()->addHours(2 + $index)->startOfMinute();
                $place = 'Parque del barrio · '.($anchor->neighborhood ?: $anchor->district);
                $post = CommunityPost::firstOrCreate(['demo_key' => $key.'-post'], [
                    'user_id' => $author->id, 'ubigeo' => $anchor->ubigeo, 'kind' => 'activity', 'body' => $story['body'],
                    'photo_path' => $photo, 'activity_title' => $story['title'], 'activity_location' => $place,
                    'activity_starts_at' => $starts->toIso8601String(), 'latitude' => $location->latitude, 'longitude' => $location->longitude,
                    'created_at' => now()->subMinutes(5 + $index * 3), 'updated_at' => now(),
                ]);
                $post->syncTagsWithType($story['categories'], 'category');
                $post->syncTagsWithType($story['topics'], 'feed_topic');
                preg_match_all('/#([\p{L}\p{N}_]+)/u', $story['body'], $tags);
                $post->syncTagsWithType($tags[1], 'hashtag');
                foreach ($story['comments'] as $j => $body) {
                    $commenter = $j === 3 ? $author : $neighbors[$j + $index * 3];
                    $post->comments()->firstOrCreate(['demo_key' => $key.'-comment-'.$j], [
                        'user_id' => $commenter->id, 'body' => $body,
                        'created_at' => now()->subMinutes(4 - $j), 'updated_at' => now(),
                    ]);
                    foreach ($neighbors->slice(4, 3 + $index) as $recommender) {
                        if ($recommender->id !== $commenter->id) {
                            DB::table('neighbor_endorsements')->insertOrIgnore(['user_id' => $recommender->id, 'neighbor_id' => $commenter->id, 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
                foreach ($neighbors->take(5 + $index * 3) as $liker) {
                    DB::table('community_reactions')->insertOrIgnore(['user_id' => $liker->id, 'post_id' => $post->id]);
                }
                MapEvent::firstOrCreate(['demo_key' => $key.'-event'], [
                    'user_id' => $author->id, 'ubigeo' => $anchor->ubigeo, 'category' => $story['category'], 'type' => $story['type'],
                    'title' => $story['title'], 'description' => $story['body'], 'place' => $place,
                    'starts_at' => $post->activity_starts_at, 'ends_at' => $post->activity_starts_at->addHours(2),
                    'has_cost' => false, 'points' => config('map-events.categories.'.$story['category'].'.points'), 'photo_path' => $photo,
                    'location' => DB::raw('ST_SetSRID(ST_MakePoint('.(float) $location->longitude.','.(float) $location->latitude.'),4326)::geography'),
                ]);
            });
        }
        $this->command?->info('Dos historias, ocho comentarios y dos actividades preparadas. Vistos buenos solo entre cuentas sintéticas; sin otorgar puntos.');
    }

    private function photo(int $id): string
    {
        // Pexels License: https://www.pexels.com/license/; source pages are listed with each fixture.
        $path = 'demo-showcase/pexels-'.$id.'.webp';
        if (! Storage::disk('local')->exists($path)) {
            $response = Http::timeout(30)->retry(2, 300)->get('https://images.pexels.com/photos/'.$id.'/pexels-photo-'.$id.'.jpeg', ['auto' => 'compress', 'cs' => 'tinysrgb', 'w' => 1000])->throw();
            $temporary = tempnam(sys_get_temp_dir(), 'masi-story-');
            try {
                file_put_contents($temporary, $response->body());
                $stored = app(CommunityPhoto::class)->store(new UploadedFile($temporary, 'story.jpg', 'image/jpeg', null, true));
                if (! Storage::disk('local')->move($stored, $path)) {
                    throw new \RuntimeException('No se pudo guardar la foto de la historia.');
                }
            } finally {
                @unlink($temporary);
            }
        }

        return $path;
    }
}
