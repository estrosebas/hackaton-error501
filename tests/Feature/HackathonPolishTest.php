<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Database\Seeders\HackathonMapRangeSeeder;
use Database\Seeders\HackathonPolishSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HackathonPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_refinement_is_varied_repeatable_and_preserves_real_interactions_and_enrollments(): void
    {
        Http::preventStrayRequests();
        $anchor = User::factory()->create(['ubigeo' => '040122', 'district' => 'Socabaya', 'latitude' => -16.46, 'longitude' => -71.53, 'feed_interests' => []]);
        config(['demo-feed.showcase' => true, 'demo-feed.map_anchor_user_id' => $anchor->id]);
        $this->seed(HackathonMapRangeSeeder::class);
        $post = CommunityPost::firstOrFail();
        $post->comments()->create(['user_id' => $anchor->id, 'body' => 'Conservar comentario real']);
        DB::table('community_reactions')->insert(['user_id' => $anchor->id, 'post_id' => $post->id]);
        $event = DB::table('map_events')->first();
        DB::table('map_event_attendances')->insert(['map_event_id' => $event->id, 'user_id' => $anchor->id, 'joined_at' => now()]);
        $this->seed(HackathonPolishSeeder::class);
        $commentCount = DB::table('community_comments')->count();
        $votes = DB::table('neighbor_endorsements')->count();
        $this->seed(HackathonPolishSeeder::class);
        $this->assertDatabaseCount('community_posts', 73);
        $this->assertDatabaseCount('community_comments', $commentCount);
        $this->assertDatabaseCount('neighbor_endorsements', $votes);
        $this->assertDatabaseHas('community_comments', ['body' => 'Conservar comentario real']);
        $this->assertDatabaseHas('community_reactions', ['user_id' => $anchor->id, 'post_id' => $post->id]);
        $after = DB::table('map_events')->find($event->id);
        $this->assertEquals($event->starts_at, $after->starts_at);
        $this->assertEquals($event->location, $after->location);
        $this->assertGreaterThan(3, CommunityPost::withCount('comments')->get()->pluck('comments_count')->unique()->count());
        $this->assertGreaterThan(0, $votes);
        $this->assertSame(0, DB::table('neighbor_endorsements')->where('neighbor_id', $anchor->id)->count());
        $activities = CommunityPost::where('kind', 'activity')->get();
        $this->assertCount(2, $activities->pluck('activity_starts_at')->unique());
        $this->assertCount(2, $activities->pluck('photo_path')->unique());
        $this->assertDatabaseCount('empathy_rewards', 0);
        CommunityPost::create(['user_id' => $anchor->id, 'ubigeo' => $anchor->ubigeo, 'kind' => 'moment', 'body' => 'Una publicación más nueva que el simulacro']);
        Sanctum::actingAs($anchor);
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonPath('data.0.kind', 'emergency')->assertJsonPath('data.0.is_demo', true)->assertJsonPath('data.0.status', 'resolved');
        $anchor->forceFill(['feed_interests' => ['musica']])->save();
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonPath('data.0.kind', 'emergency');
    }
}
