<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Database\Seeders\LocalCommunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalCommunitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_seed_is_repeatable_and_visible_in_the_real_neighbors_district(): void
    {
        Http::preventStrayRequests();
        $real = User::factory()->create(['ubigeo' => '080101', 'neighborhood' => 'Mi barrio']);
        $this->app->detectEnvironment(fn () => 'local');
        try {
            $this->seed(LocalCommunitySeeder::class);
            $post = CommunityPost::where('demo_key', 'feed-080101-0')->firstOrFail();
            $post->comments()->create(['user_id' => $real->id, 'body' => 'Una respuesta real que debe conservarse']);
            $this->seed(LocalCommunitySeeder::class);
            $this->assertSame(20, User::whereNotNull('demo_key')->count());
            $this->assertDatabaseCount('community_posts', 12);
            $this->assertDatabaseCount('community_comments', 25);
            $this->assertDatabaseCount('community_reactions', 36);
            $this->assertDatabaseCount('face_profiles', 0);
            $this->assertSame(0, User::whereNotNull('demo_key')->whereNotNull('dni')->count());
            $this->assertDatabaseHas('community_comments', ['body' => 'Una respuesta real que debe conservarse']);
            Sanctum::actingAs($real);
            $feed = $this->getJson('/api/v1/feed')->assertOk()->assertJsonCount(10, 'data')
                ->assertJsonPath('data.0.avatar', '/api/v1/demo/avatars/01.jpg');
            $this->assertContains('Mecánica', $feed->json('data.0.categories'));
            $this->assertContains('carro', $feed->json('data.0.tags'));
            $this->getJson('/api/v1/feed/'.$post->id.'/comments')->assertOk()->assertJsonCount(3, 'data');
            $this->get('/api/v1/demo/avatars/01.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
            $this->get('/api/v1/demo/avatars/99.jpg')->assertNotFound();
            $this->assertSame(0, DB::table('users')->whereNotNull('demo_key')->where('ubigeo', '!=', '080101')->count());
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_seed_and_demo_photos_are_disabled_outside_local_even_when_enabled(): void
    {
        config(['demo-feed.enabled' => true]);
        foreach (['testing', 'development', 'staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            try {
                app(LocalCommunitySeeder::class)->run();
                $this->artisan('demo:seed')->assertSuccessful();
                $this->get('/api/v1/demo/avatars/01.jpg')->assertNotFound();
                $this->assertDatabaseCount('users', 0);
                $this->assertDatabaseCount('community_posts', 0);
            } finally {
                $this->app->detectEnvironment(fn () => 'testing');
            }
        }
    }

    public function test_local_seed_can_be_disabled(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['demo-feed.enabled' => false]);
        try {
            $this->seed(LocalCommunitySeeder::class);
            $this->assertDatabaseCount('users', 0);
            $this->get('/api/v1/demo/avatars/01.jpg')->assertNotFound();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
