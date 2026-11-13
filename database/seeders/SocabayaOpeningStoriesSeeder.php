<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SocabayaOpeningStoriesSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo-feed.showcase')) {
            throw new \RuntimeException('Activa DEMO_SHOWCASE_ENABLED para sembrar historias ficticias.');
        }
        $anchor = User::whereNull('demo_key')->whereKey(config('demo-feed.map_anchor_user_id'))->where('ubigeo', '040122')->firstOrFail();
        $neighbors = User::whereNotNull('demo_key')->where('ubigeo', $anchor->ubigeo)->orderBy('id')->limit(10)->get();
        if ($neighbors->count() < 10) {
            throw new \RuntimeException('Faltan los perfiles sintéticos del distrito.');
        }
        $images = collect(json_decode(file_get_contents(database_path('data/demo/showcase-photos/manifest.json')), true, flags: JSON_THROW_ON_ERROR)['images'])->keyBy('key');
        $stories = [
            [
                'key' => 'rosa-kites', 'name' => 'Rosa Huamán', 'avatar' => '09.jpg',
                'kind' => 'offer_help', 'photo' => 'demo-showcase/pexels-32646282.webp',
                'body' => "Soy doña Rosa y hago cometas en mi casita de Socabaya 🪁\nLas preparo a mano con varillas livianas y papel de colores, como las que hacía con mis hijos cuando eran pequeños. Ahora quisiera vender unas cuantas para ayudar con los gastos de casa. Tengo pequeñas desde S/ 12 y grandes desde S/ 18.\nMi hija me está enseñando a usar el celular para recibir pedidos. Si quieren ver colores o encargar una para sus peques, escríbanme por el chat de masi. También puedo enseñarles a armar una; no necesitan comprar para aprender.\n¿Nos encontramos en el parque del barrio para hacerlas volar? #cometas #artesania #hechoamano #Socabaya",
                'categories' => ['Artesanía', 'Cometas', 'Emprendimientos', 'Familia'], 'topics' => ['emprendimientos', 'arte'], 'likes' => 5,
                'comments' => [
                    'Le compré dos para mis nietos y están preciosas. Se nota el cariño que les pone, doña Rosa. ¡Mi visto bueno vecinal para usted!',
                    'Vecina, yo puedo ayudarla a tomar fotos de las cometas para que más personas conozcan su trabajo. No le cobro nada.',
                    '¿Me hace una roja y blanca? Mi hija quiere aprender a armarla con usted. Le escribo por el chat para coordinar.',
                    '¡Claro que sí! Con gusto les enseño. Gracias por recomendarme, vecinos; me están dando mucho ánimo para seguir.',
                ],
            ],
            [
                'key' => 'julio-collection', 'name' => 'Patricia Rojas', 'avatar' => '13.jpg',
                'kind' => 'collaboration', 'photo' => 'demo-showcase/'.$images->get('community-0')['sha256'].'.webp',
                'body' => "Vecinos de Socabaya, nos organizamos por don Julio 🤝\nDon Julio sufrió una caída y, mientras se recupera, no puede salir a trabajar. Su familia necesita una mano con alimentos y los gastos de estos días. Estamos preparando una pollada solidaria y una colecta voluntaria entre vecinos.\nUnos vecinos que reparan computadoras se ofrecieron a hacer revisiones básicas durante la jornada para sumar apoyo. También necesitamos ayuda preparando las porciones, prestando mesas o llevando víveres; no todo tiene que ser dinero.\nQuien quiera colaborar puede escribir por el chat para coordinar. Cuidemos la privacidad de la familia: no compartamos su dirección ni sus documentos. Gracias por hacer barrio. #solidaridad #colecta #apoyovecinal #Socabaya",
                'categories' => ['Solidaridad', 'Colecta', 'Apoyo vecinal', 'Comunidad'], 'topics' => ['voluntariado', 'tecnologia'], 'likes' => 8,
                'comments' => [
                    'Yo pongo una bolsa de arroz y puedo ayudar a repartir las porciones. Les escribo para coordinar la entrega.',
                    'Tengo un taller de computadoras. Llevo mis herramientas para las revisiones básicas y dono lo recaudado de esa jornada al apoyo de don Julio.',
                    'Recomiendo al vecino del taller, ya me ayudó con mi laptop y fue muy claro con el precio. Le dejé mi visto bueno vecinal.',
                    'Gracias, vecinos. Ya contamos con dos mesas prestadas. Nos faltan manos para organizar las porciones; cualquier apoyo suma.',
                ],
            ],
        ];
        foreach ($stories as $story) {
            if (! Storage::disk('local')->exists($story['photo'])) {
                throw new \RuntimeException('Falta la foto ilustrativa de '.$story['key']);
            }
        }
        DB::transaction(function () use ($anchor, $neighbors, $stories) {
            foreach ($stories as $i => $story) {
                $key = 'showcase-040122-opening-'.$story['key'];
                $author = User::firstOrCreate(['demo_key' => $key.'-author'], [
                    'name' => $story['name'], 'email' => $key.'@demo.masi.test', 'password' => Str::random(64),
                    'demo_avatar' => $story['avatar'], 'ubigeo' => $anchor->ubigeo,
                    'region' => $anchor->region, 'province' => $anchor->province, 'district' => $anchor->district,
                    'neighborhood' => $anchor->neighborhood ?: $anchor->district,
                    'neighbor_profile' => ['bio' => 'Personaje ficticio de la demostración de masi.', 'skills' => [$story['categories'][0]], 'interests' => ['Comunidad'], 'availability' => 'A coordinar'],
                ]);
                $author->assignRole('neighbor');
                $post = CommunityPost::firstOrCreate(['demo_key' => $key.'-post'], [
                    'user_id' => $author->id, 'ubigeo' => $anchor->ubigeo, 'kind' => $story['kind'],
                    'body' => $story['body'], 'photo_path' => $story['photo'],
                    'created_at' => now()->subMinutes(3 - $i), 'updated_at' => now(),
                ]);
                $post->syncTagsWithType($story['categories'], 'category');
                $post->syncTagsWithType($story['topics'], 'feed_topic');
                preg_match_all('/#([\p{L}\p{N}_]+)/u', $story['body'], $tags);
                $post->syncTagsWithType($tags[1], 'hashtag');
                foreach ($story['comments'] as $j => $body) {
                    $commenter = $j === 3 ? $author : $neighbors[$j + 2 * $i];
                    $comment = $post->comments()->firstOrCreate(['demo_key' => $key.'-comment-'.$j], [
                        'user_id' => $commenter->id, 'body' => $body, 'created_at' => now()->subSeconds(90 - $j * 20), 'updated_at' => now(),
                    ]);
                    foreach ($neighbors->slice(5, 2 + $j % 2) as $liker) {
                        if ($liker->id !== $commenter->id) {
                            DB::table('community_comment_reactions')->insertOrIgnore(['user_id' => $liker->id, 'comment_id' => $comment->id]);
                        }
                    }
                    foreach ($neighbors->slice(4, 3 + $i) as $recommender) {
                        if ($recommender->id !== $commenter->id) {
                            DB::table('neighbor_endorsements')->insertOrIgnore(['user_id' => $recommender->id, 'neighbor_id' => $commenter->id, 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
                foreach ($neighbors->take($story['likes']) as $liker) {
                    DB::table('community_reactions')->insertOrIgnore(['user_id' => $liker->id, 'post_id' => $post->id]);
                }
            }
            // A historical drill is an informational notice, not an ongoing emergency.
            CommunityPost::where('ubigeo', $anchor->ubigeo)->whereNotNull('demo_key')->where('kind', 'emergency')
                ->where('body', 'like', 'SIMULACRO DE DEMOSTRACIÓN%')->update(['kind' => 'alert']);
        });
        $this->command?->info('Dos historias listas en Socabaya, con ocho comentarios. No se alteraron preferencias, puntos ni recomendaciones de cuentas reales.');
    }
}
