<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityFeedTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(string $zone = '150101'): User
    {
        $user = User::factory()->create(['ubigeo' => $zone, 'neighborhood' => 'Barrio de prueba']);
        $user->assignRole('neighbor');

        return $user;
    }

    public function test_feed_is_authenticated_and_bound_to_registered_district(): void
    {
        $this->getJson('/api/v1/feed')->assertUnauthorized();
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $created = $this->postJson('/api/v1/feed', ['kind' => 'need_help', 'body' => 'Necesito ayuda con mi computadora #colaboracion', 'ubigeo' => '080101', 'user_id' => 999])
            ->assertCreated()->assertJsonPath('data.author', $owner->name)->assertJsonPath('data.tags.0', 'colaboracion');
        $id = $created->json('data.id');
        $this->assertDatabaseHas('community_posts', ['id' => $id, 'ubigeo' => '150101', 'user_id' => $owner->id]);
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.dni')->assertJsonMissingPath('data.0.latitude');
        Sanctum::actingAs($this->neighbor('080101'));
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/feed/$id/comments")->assertNotFound();
        $this->postJson("/api/v1/feed/$id/comments", ['body' => 'No permitido'])->assertNotFound();
        $this->putJson("/api/v1/feed/$id/reaction", ['selected' => true])->assertNotFound();
    }

    public function test_interactions_are_persistent_idempotent_and_owner_controls_status(): void
    {
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/feed', ['kind' => 'offer_help', 'body' => 'Puedo apoyar con clases gratuitas'])->assertCreated()->json('data.id');
        $other = $this->neighbor();
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/feed/$id/comments", ['body' => '¿Qué horario tienes?'])->assertCreated();
        $this->getJson("/api/v1/feed/$id/comments")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.author', $other->name);
        for ($i = 0; $i < 2; $i++) {
            $this->putJson("/api/v1/feed/$id/reaction", ['selected' => true])->assertOk()->assertJsonPath('data.reactions', 1);
            $this->putJson("/api/v1/feed/$id/bookmark", ['selected' => true])->assertOk()->assertJsonPath('data.saved', true);
        }
        $this->assertDatabaseCount('community_reactions', 1);
        $this->getJson('/api/v1/feed?saved=1')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/feed/$id", ['status' => 'resolved'])->assertForbidden();
        $this->deleteJson("/api/v1/feed/$id")->assertForbidden();
        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/feed/$id", ['status' => 'resolved'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->deleteJson("/api/v1/feed/$id")->assertNoContent();
        $this->getJson("/api/v1/feed/$id/comments")->assertNotFound();
    }

    public function test_cursor_pagination_filters_and_content_validation(): void
    {
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        for ($i = 0; $i < 12; $i++) {
            CommunityPost::create(['user_id' => $owner->id, 'ubigeo' => $owner->ubigeo, 'kind' => 'initiative', 'body' => 'Actividad '.$i]);
        }
        $first = $this->getJson('/api/v1/feed?kind=initiative')->assertOk()->assertJsonCount(10, 'data');
        $second = $this->getJson('/api/v1/feed?kind=initiative&cursor='.urlencode($first->json('next_cursor')))->assertOk()->assertJsonCount(2, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->postJson('/api/v1/feed', ['kind' => 'sale', 'body' => ' '])->assertUnprocessable();
        $this->postJson('/api/v1/feed', ['kind' => 'collaboration', 'body' => '#a #b #c #d #e #f'])->assertUnprocessable();
    }

    public function test_post_photo_is_shared_only_with_authenticated_neighbors_in_the_district(): void
    {
        Storage::fake('local');
        $this->getJson('/api/v1/feed/1/photo')->assertUnauthorized();

        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/feed', ['kind' => 'moment'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->post('/api/v1/feed', [
            'kind' => 'moment', 'photo' => UploadedFile::fake()->create('texto.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $id = $this->post('/api/v1/feed', [
            'kind' => 'moment', 'photo' => UploadedFile::fake()->image('original.jpg', 120, 90),
        ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.has_photo', true)->assertJsonPath('data.paragraphs', [])
            ->assertJsonMissingPath('data.photo_path')->json('data.id');
        $path = CommunityPost::findOrFail($id)->photo_path;
        Storage::disk('local')->assertExists($path);

        Sanctum::actingAs($this->neighbor());
        $this->getJson('/api/v1/feed?kind=moment')->assertOk()->assertJsonPath('data.0.has_photo', true);
        $response = $this->get("/api/v1/feed/$id/photo")->assertOk()
            ->assertHeader('Content-Type', 'image/webp')->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertSame('image/webp', getimagesizefromstring($response->streamedContent())['mime']);

        Sanctum::actingAs($this->neighbor('080101'));
        $this->getJson("/api/v1/feed/$id/photo")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/feed/$id")->assertNoContent();
        $this->getJson("/api/v1/feed/$id/photo")->assertNotFound();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_activity_can_include_a_future_date_and_meeting_point(): void
    {
        DB::statement("SET LOCAL TIME ZONE 'America/Guatemala'");
        Sanctum::actingAs($this->neighbor());
        $details = [
            'kind' => 'activity', 'body' => 'Trae agua.',
            'activity_title' => 'Caminata vecinal', 'activity_location' => 'Entrada del parque',
            'activity_starts_at' => now()->addDays(2)->startOfSecond()->toISOString(),
        ];
        $this->postJson('/api/v1/feed', array_diff_key($details, ['activity_location' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('activity_location');
        $this->postJson('/api/v1/feed', array_replace($details, ['activity_starts_at' => now()->subDay()->toISOString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('activity_starts_at');
        $this->postJson('/api/v1/feed', array_replace($details, ['kind' => 'moment']))
            ->assertUnprocessable()->assertJsonValidationErrors('activity_title');
        $this->postJson('/api/v1/feed', $details)->assertCreated()
            ->assertJsonPath('data.activity.title', $details['activity_title'])
            ->assertJsonPath('data.activity.starts_at', $details['activity_starts_at'])
            ->assertJsonPath('data.activity.location', $details['activity_location']);
        $this->getJson('/api/v1/feed?kind=activity')->assertOk()
            ->assertJsonPath('data.0.activity.title', $details['activity_title'])
            ->assertJsonPath('data.0.activity.starts_at', $details['activity_starts_at']);
        $this->postJson('/api/v1/feed', ['kind' => 'activity', 'body' => 'Actividad anterior'])
            ->assertCreated()->assertJsonPath('data.activity', null);
        $detailsWithCoords = array_merge($details, [
            'latitude' => -12.046374,
            'longitude' => -77.042793,
        ]);
        $this->postJson('/api/v1/feed', $detailsWithCoords)->assertCreated()
            ->assertJsonPath('data.activity.latitude', -12.046374)
            ->assertJsonPath('data.activity.longitude', -77.042793);
    }

    public function test_a_photo_gallery_keeps_order_and_removes_all_files_with_the_post(): void
    {
        Storage::fake('local');
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $id = $this->post('/api/v1/feed', [
            'kind' => 'moment',
            'photos' => [
                UploadedFile::fake()->image('primera.jpg', 100, 80),
                UploadedFile::fake()->image('segunda.png', 140, 90),
            ],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.photo_count', 2)
            ->assertJsonMissingPath('data.photo_paths')
            ->json('data.id');
        $paths = CommunityPost::findOrFail($id)->publishedPhotoPaths();
        Storage::disk('local')->assertExists($paths);
        $first = $this->get("/api/v1/feed/$id/photo")->assertOk()->streamedContent();
        $second = $this->get("/api/v1/feed/$id/photo/1")->assertOk()->streamedContent();
        $this->assertSame(100, getimagesizefromstring($first)[0]);
        $this->assertSame(140, getimagesizefromstring($second)[0]);
        $this->getJson("/api/v1/feed/$id/photo/2")->assertNotFound();

        Sanctum::actingAs($this->neighbor('080101'));
        $this->getJson("/api/v1/feed/$id/photo/1")->assertNotFound();
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/feed/$id")->assertNoContent();
        Storage::disk('local')->assertMissing($paths);
    }
}
