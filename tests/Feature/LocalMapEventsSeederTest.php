<?php

namespace Tests\Feature;

use App\Models\MapEvent;
use App\Models\User;
use Database\Seeders\LocalMapEventsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalMapEventsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_seed_creates_three_events_per_category_near_anchor_without_rewards_or_duplicates(): void
    {
        Storage::fake('local');
        $anchor = User::factory()->create(['ubigeo' => '150101', 'latitude' => -12.0464, 'longitude' => -77.0428]);
        for ($i = 0; $i < 12; $i++) {
            User::factory()->create(['demo_key' => 'map-neighbor-'.$i, 'demo_avatar' => '01.jpg']);
        }
        config(['demo-feed.enabled' => true, 'demo-feed.map_anchor_user_id' => $anchor->id]);
        $this->app->detectEnvironment(fn () => 'local');
        try {
            $this->seed(LocalMapEventsSeeder::class);
            $this->seed(LocalMapEventsSeeder::class);
            $this->assertDatabaseCount('map_events', 12);
            foreach (['community', 'emergency', 'trend', 'support'] as $category) {
                $this->assertSame(3, MapEvent::where('category', $category)->count());
            }
            $this->assertSame(12, (int) DB::table('map_events')->where('ubigeo', $anchor->ubigeo)->whereRaw('ST_DWithin(location, ST_SetSRID(ST_MakePoint(?,?),4326)::geography,500)', [$anchor->longitude, $anchor->latitude])->count());
            $this->assertSame(12, MapEvent::whereHas('author', fn ($query) => $query->whereNotNull('demo_key'))->count());
            $this->assertSame(3, MapEvent::where('category', 'emergency')->where('title', 'like', 'Simulacro%')->count());
            $this->assertDatabaseCount('empathy_rewards', 0);
            $this->assertDatabaseCount('map_event_attendances', 0);
            $this->assertDatabaseHas('map_events', ['category' => 'support', 'has_cost' => true, 'points' => 0]);
            foreach (MapEvent::all() as $event) {
                Storage::disk('local')->assertExists($event->photo_path);
                $this->assertStringStartsWith('demo-event-photos/', $event->photo_path);
                $this->assertNotNull($event->demo_photo_hash);
                $this->assertStringNotContainsString('vecino sintético', $event->description);
            }
            $this->assertCount(12, Storage::disk('local')->allFiles('demo-event-photos'));
            $reserved = MapEvent::firstOrFail();
            DB::table('map_event_attendances')->insert(['map_event_id' => $reserved->id, 'user_id' => $anchor->id, 'joined_at' => now()]);
            $reserved->update(['title' => 'Evento con inscripción que debe conservarse', 'photo_path' => 'community-photos/legacy-person.webp', 'demo_photo_hash' => null]);
            $this->seed(LocalMapEventsSeeder::class);
            $this->assertSame('Evento con inscripción que debe conservarse', $reserved->fresh()->title);
            $this->assertStringStartsWith('demo-event-photos/', $reserved->fresh()->photo_path);
            $this->assertNotNull($reserved->fresh()->demo_photo_hash);
            $this->assertDatabaseCount('map_events', 12);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_map_seed_is_disabled_outside_local_and_without_an_anchor(): void
    {
        config(['demo-feed.enabled' => true]);
        $this->seed(LocalMapEventsSeeder::class);
        $this->assertDatabaseCount('map_events', 0);
        $this->app->detectEnvironment(fn () => 'local');
        try {
            $this->seed(LocalMapEventsSeeder::class);
            $this->assertDatabaseCount('map_events', 0);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_seed_uses_the_developers_latest_saved_location_and_allows_an_explicit_account(): void
    {
        Storage::fake('local');
        $older = User::factory()->create(['ubigeo' => '150101', 'latitude' => -12.0464, 'longitude' => -77.0428, 'location_captured_at' => now()->subDays(2)]);
        $developer = User::factory()->create(['ubigeo' => '080101', 'latitude' => -13.5134, 'longitude' => -71.9779, 'location_captured_at' => now()]);
        for ($i = 0; $i < 12; $i++) {
            User::factory()->create(['demo_key' => 'developer-neighbor-'.$i]);
        }
        config(['demo-feed.enabled' => true, 'demo-feed.map_anchor_user_id' => null]);
        $this->app->detectEnvironment(fn () => 'local');
        try {
            $this->seed(LocalMapEventsSeeder::class);
            $this->assertSame(12, MapEvent::where('ubigeo', $developer->ubigeo)->count());
            $this->assertSame(12, DB::table('map_events')->whereRaw('ST_DWithin(location, ST_SetSRID(ST_MakePoint(?,?),4326)::geography,500)', [$developer->longitude, $developer->latitude])->count());
            config(['demo-feed.map_anchor_user_id' => $older->id]);
            $this->seed(LocalMapEventsSeeder::class);
            $this->assertSame(12, MapEvent::where('ubigeo', $older->ubigeo)->count());
            $this->assertSame(12, DB::table('map_events')->whereRaw('ST_DWithin(location, ST_SetSRID(ST_MakePoint(?,?),4326)::geography,500)', [$older->longitude, $older->latitude])->count());
            $this->assertCount(12, Storage::disk('local')->allFiles('demo-event-photos'));
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
