<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\MapEvent;
use App\Models\User;
use App\Services\CommunityPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AltoSelvaAlegreDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo-feed.showcase')) {
            throw new \RuntimeException('Activa DEMO_SHOWCASE_ENABLED para sembrar actividades ficticias.');
        }
        $fixture = json_decode(file_get_contents(database_path('data/demo/alto-selva-alegre.json')), true, flags: JSON_THROW_ON_ERROR);
        $district = DB::table('districts')->where('code', $fixture['ubigeo'])->firstOrFail();
        $province = DB::table('provinces')->where('code', $district->province_code)->firstOrFail();
        $region = DB::table('regions')->where('code', $province->region_code)->firstOrFail();
        $names = (require database_path('data/demo/feed.php'))['names'];
        $directory = is_file(database_path('data/demo/showcase-photos/manifest.json')) ? 'showcase-photos' : 'events';
        $images = collect(json_decode(file_get_contents(database_path('data/demo/'.$directory.'/manifest.json')), true, flags: JSON_THROW_ON_ERROR)['images'])->keyBy('key');
        foreach ($fixture['activities'] as $i => $item) {
            $photo = $this->photo($item['photo'], $images, $directory);
            DB::transaction(function () use ($i, $item, $district, $province, $region, $names, $photo) {
                $key = 'showcase-040102-osm-'.$item['key'];
                $author = User::firstOrCreate(['demo_key' => $key.'-author'], [
                    'name' => $names[$i], 'email' => $key.'@demo.masi.test', 'password' => Str::random(64),
                    'demo_avatar' => sprintf('%02d.jpg', $i + 1), 'ubigeo' => $district->code,
                    'district' => $district->name, 'province' => $province->name, 'region' => $region->name,
                    'neighborhood' => 'Alto Selva Alegre', 'latitude' => $item['latitude'], 'longitude' => $item['longitude'],
                    'neighbor_profile' => ['bio' => 'Perfil sintético para la demostración de masi.', 'skills' => ['Organización vecinal'], 'interests' => ['Comunidad'], 'availability' => 'A coordinar'],
                ]);
                $author->assignRole('neighbor');
                $starts = now('America/Lima')->setTime(17, 0)->addMinutes(($i * 37) % 240);
                if ($starts->lte(now())) {
                    $starts->addDay();
                }
                $description = $item['body']."\nActividad ficticia de demostración; no representa una convocatoria del parque ni de la municipalidad. Foto ilustrativa.";
                $post = CommunityPost::firstOrCreate(['demo_key' => $key.'-post'], [
                    'user_id' => $author->id, 'ubigeo' => $district->code, 'kind' => 'activity',
                    'body' => $item['title']."\n".$description, 'photo_path' => $photo,
                    'activity_title' => $item['title'], 'activity_location' => $item['place'],
                    'activity_starts_at' => $starts->toIso8601String(), 'latitude' => $item['latitude'], 'longitude' => $item['longitude'],
                    'created_at' => now()->subMinutes(3 + $i * 7), 'updated_at' => now(),
                ]);
                $post->syncTagsWithType(['Comunidad', $item['category'] === 'support' ? 'Ayuda vecinal' : 'Actividades'], 'category');
                $post->syncTagsWithType(['voluntariado', 'aprendizaje'], 'feed_topic');
                MapEvent::firstOrCreate(['demo_key' => $key.'-event'], [
                    'user_id' => $author->id, 'ubigeo' => $district->code, 'category' => $item['category'], 'type' => $item['type'],
                    'title' => $item['title'], 'description' => $description, 'place' => $item['place'],
                    'starts_at' => $post->activity_starts_at, 'ends_at' => $post->activity_starts_at->addMinutes(90 + ($i * 17) % 90),
                    'has_cost' => false, 'points' => config('map-events.categories.'.$item['category'].'.points'), 'photo_path' => $photo,
                    'location' => DB::raw('ST_SetSRID(ST_MakePoint('.(float) $item['longitude'].','.(float) $item['latitude'].'),4326)::geography'),
                ]);
            });
        }
        $this->command?->info(count($fixture['activities']).' actividades de Alto Selva Alegre listas en mapa y feed. Sin cambiar cuentas reales ni otorgar puntos.');
    }

    private function photo(string $key, $images, string $directory): string
    {
        if (in_array($key, ['chess', 'kites'], true)) {
            $path = 'demo-showcase/pexels-'.($key === 'chess' ? '23894011' : '32646282').'.webp';
            if (Storage::disk('local')->exists($path)) {
                return $path;
            }
            $key = $key === 'chess' ? 'trend-2' : 'trend-1';
        }
        $image = $images->get($key);
        if (! $image) {
            throw new \RuntimeException('Falta la fotografía ilustrativa '.$key);
        }
        $path = 'demo-showcase/'.$image['sha256'].'.webp';
        if (! Storage::disk('local')->exists($path)) {
            $source = database_path('data/demo/'.$directory.'/'.basename($image['file']));
            if (! is_file($source) || hash_file('sha256', $source) !== $image['sha256']) {
                throw new \RuntimeException('Falta una imagen válida para '.$key);
            }
            $stored = app(CommunityPhoto::class)->store(new UploadedFile($source, $image['file'], 'image/jpeg', null, true));
            if (! Storage::disk('local')->move($stored, $path)) {
                throw new \RuntimeException('No se pudo guardar la imagen de '.$key);
            }
        }

        return $path;
    }
}
