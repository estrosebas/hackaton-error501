<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
