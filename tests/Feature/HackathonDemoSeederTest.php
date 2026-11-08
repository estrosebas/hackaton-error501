<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Database\Seeders\HackathonDemoSeeder;
use Database\Seeders\HackathonMapRangeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HackathonDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_showcase_is_explicit_idempotent_and_uses_existing_visibility_rules(): void
    {
        Http::preventStrayRequests();
        $anchor = User::factory()->create(['ubigeo' => '040122', 'district' => 'Socabaya', 'neighborhood' => '4 de octubre', 'latitude' => -16.46, 'longitude' => -71.53]);
        config(['demo-feed.showcase' => true, 'demo-feed.map_anchor_user_id' => $anchor->id]);
        $this->seed(HackathonDemoSeeder::class);
        $post = CommunityPost::whereNotNull('demo_key')->firstOrFail();
        $post->comments()->create(['user_id' => $anchor->id, 'body' => 'Comentario real que debe conservarse']);
        $this->seed(HackathonDemoSeeder::class);
        $this->assertDatabaseCount('community_posts', 72);
        $this->assertSame(20, User::whereNotNull('demo_key')->count());
        $this->assertDatabaseHas('community_comments', ['body' => 'Comentario real que debe conservarse']);
        $this->assertDatabaseCount('map_events', 12);
        $this->assertDatabaseCount('empathy_rewards', 0);
        $this->assertDatabaseCount('face_profiles', 0);
        $this->assertGreaterThan(8, CommunityPost::whereNotNull('photo_path')->count());
        $near = DB::selectOne('SELECT count(*) AS total FROM map_events WHERE ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?),4326)::geography,300)', [$anchor->longitude, $anchor->latitude]);
        $this->assertEquals(12, $near->total);
        Sanctum::actingAs($anchor);
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonPath('data.0.is_demo', true);
        $this->getJson('/api/v1/map-events')->assertOk()->assertJsonCount(12, 'data');
        $this->get('/api/v1/demo/avatars/01.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        Sanctum::actingAs(User::factory()->create(['ubigeo' => '150101']));
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/map-events')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_demo_has_distinct_counts_at_each_map_range_and_remains_repeatable(): void
    {
        Http::preventStrayRequests();
        $anchor = User::factory()->create(['ubigeo' => '040122', 'district' => 'Socabaya', 'latitude' => -16.46, 'longitude' => -71.53]);
        config(['demo-feed.showcase' => true, 'demo-feed.map_anchor_user_id' => $anchor->id]);
        $this->seed(HackathonMapRangeSeeder::class);
        $this->seed(HackathonMapRangeSeeder::class);
        $this->assertDatabaseCount('map_events', 36);
        foreach ([300 => 12, 1000 => 24, 3000 => 36] as $meters => $expected) {
            $result = DB::selectOne('SELECT count(*) AS total FROM map_events WHERE ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?),4326)::geography, ?)', [$anchor->longitude, $anchor->latitude, $meters]);
            $this->assertEquals($expected, $result->total);
        }
        $this->assertDatabaseCount('community_posts', 72);
        $this->assertDatabaseCount('empathy_rewards', 0);
    }

    public function test_showcase_cannot_be_seeded_accidentally(): void
    {
        config(['demo-feed.showcase' => false]);
        $this->expectException(\RuntimeException::class);
        $this->seed(HackathonDemoSeeder::class);
    }
}
