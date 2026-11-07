<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalCommunitySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('demo-feed.enabled')) {
            $this->command?->info('Feed sintético omitido: exclusivo de APP_ENV=local.');

            return;
        }
        $zone = config('demo-feed.ubigeo') ?: User::whereNull('demo_key')->whereNotNull('ubigeo')->orderBy('id')->value('ubigeo') ?: '150101';
        $district = DB::table('districts')->join('provinces', 'districts.province_code', '=', 'provinces.code')
            ->join('regions', 'provinces.region_code', '=', 'regions.code')->where('districts.code', $zone)
            ->select('districts.name as district', 'provinces.name as province', 'regions.name as region')->first();
        if (! $district) {
            throw new \RuntimeException('DEMO_FEED_UBIGEO no existe. Carga primero el catálogo UBIGEO.');
        }
        $manifest = json_decode(file_get_contents(database_path('data/demo/avatars/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest['images'] as $image) {
            $path = database_path('data/demo/avatars/'.$image['file']);
            if (! is_file($path) || hash_file('sha256', $path) !== $image['sha256']) {
                throw new \RuntimeException('Falta un retrato sintético válido: '.$image['file']);
            }
        }
        $fixture = require database_path('data/demo/feed.php');
        DB::transaction(function () use ($fixture, $zone, $district) {
            $users = [];
            foreach ($fixture['names'] as $index => $name) {
                $key = 'neighbor-'.sprintf('%02d', $index + 1);
                $user = User::where('demo_key', $key)->first();
                if (! $user) {
                    $user = new User;
                    $user->forceFill([
                        'demo_key' => $key, 'demo_avatar' => sprintf('%02d.jpg', $index + 1),
                        'name' => $name, 'email' => $key.'@demo.masi.test', 'password' => Str::random(64),
                        'ubigeo' => $zone, 'region' => $district->region, 'province' => $district->province,
                        'district' => $district->district, 'neighborhood' => $district->district,
                        'neighbor_profile' => ['skills' => [config('neighbor-profile.skills')[$index % 8]], 'interests' => ['Voluntariado'], 'availability' => 'A coordinar'],
                    ])->save();
                    $user->assignRole('neighbor');
                }
                $users[] = $user;
            }
            // Insert oldest first so the tire conversation is the newest item.
            foreach (array_reverse($fixture['posts'], true) as $index => $item) {
                $created = now()->subHours($index * 3 + 1);
                $post = CommunityPost::withTrashed()->firstOrCreate(['demo_key' => 'feed-'.$zone.'-'.$index], [
                    'user_id' => $users[$index]->id, 'ubigeo' => $zone, 'kind' => $item['kind'], 'body' => $item['body'],
                    'created_at' => $created, 'updated_at' => $created,
                    'activity_title' => $item['title'] ?? null, 'activity_location' => $item['location'] ?? null,
                    'activity_starts_at' => isset($item['title']) ? now()->addDays(3 + $index)->setTime(15, 0)->toIso8601String() : null,
                ]);
                if ($post->wasRecentlyCreated) {
                    preg_match_all('/#([\p{L}\p{N}_]+)/u', $item['body'], $matches);
                    $post->syncTagsWithType($matches[1], 'hashtag');
                    $post->syncTagsWithType($item['categories'], 'category');
                    foreach ($item['comments'] as $commentIndex => [$author, $body]) {
                        $post->comments()->create([
                            'demo_key' => 'comment-'.$zone.'-'.$index.'-'.$commentIndex,
                            'user_id' => $users[$author]->id, 'body' => $body,
                            'created_at' => $created->copy()->addMinutes(10 + $commentIndex * 7),
                            'updated_at' => $created->copy()->addMinutes(10 + $commentIndex * 7),
                        ]);
                    }
                    foreach ([($index + 2) % 20, ($index + 5) % 20, ($index + 9) % 20] as $liker) {
                        DB::table('community_reactions')->insertOrIgnore(['post_id' => $post->id, 'user_id' => $users[$liker]->id]);
                    }
                }
            }
        });
        $this->command?->info('Feed local preparado: 20 vecinos sintéticos y 12 conversaciones en '.$district->district.'.');
    }
}
