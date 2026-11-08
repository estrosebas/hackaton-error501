<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NeighborEndorsementsTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(string $zone = '150101'): User
    {
        return User::factory()->create(['ubigeo' => $zone]);
    }

    public function test_backing_is_unique_retractable_not_self_and_respects_districts_and_blocks(): void
    {
        $a = $this->neighbor();
        $b = $this->neighbor();
        $other = $this->neighbor('080101');
        $this->putJson("/api/v1/neighbors/$b->id/endorsement", ['selected' => true])->assertUnauthorized();
        Sanctum::actingAs($a);
        for ($i = 0; $i < 3; $i++) {
            $this->putJson("/api/v1/neighbors/$b->id/endorsement", ['selected' => true])->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.selected', true);
        }
        $this->assertDatabaseCount('neighbor_endorsements', 1);
        $this->putJson("/api/v1/neighbors/$a->id/endorsement", ['selected' => true])->assertUnprocessable();
        $this->putJson("/api/v1/neighbors/$other->id/endorsement", ['selected' => true])->assertNotFound();
        $this->putJson("/api/v1/neighbors/$b->id/endorsement", ['selected' => false])->assertOk()->assertJsonPath('data.count', 0);
        DB::table('neighbor_blocks')->insert(['user_id' => $b->id, 'blocked_user_id' => $a->id]);
        $this->putJson("/api/v1/neighbors/$b->id/endorsement", ['selected' => true])->assertNotFound();
        $this->getJson("/api/v1/neighbors/$b->id/endorsement")->assertNotFound();
        $this->assertDatabaseCount('empathy_rewards', 0);
    }

    public function test_category_ranks_neighbors_and_search_matches_accented_words(): void
    {
        $viewer = $this->neighbor();
        $popular = $this->neighbor();
        $newer = $this->neighbor();
        DB::table('neighbor_endorsements')->insert(['user_id' => $viewer->id, 'neighbor_id' => $popular->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$popular, $newer] as $user) {
            $p = CommunityPost::create(['user_id' => $user->id, 'ubigeo' => '150101', 'body' => 'Reparación de llantas para vecinos', 'kind' => 'offer_help']);
            $p->syncTagsWithType(['Mecánica'], 'category');
        }
        CommunityPost::create(['user_id' => $popular->id, 'ubigeo' => '150101', 'body' => 'Reparación de bicicletas', 'kind' => 'offer_help']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/feed?category=Mec%C3%A1nica&q=reparacion')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.author_id', $popular->id);
        $this->getJson('/api/v1/feed?q=llantas')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/feed?q=llantas%20reparacion')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/feed?category=Tecnologia')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_ranking_cursor_preserves_order_when_backing_changes_and_rejects_other_filters_or_users(): void
    {
        $viewer = $this->neighbor();
        $author = $this->neighbor();
        for ($i = 0; $i < 23; $i++) {
            $p = CommunityPost::create(['user_id' => $author->id, 'ubigeo' => '150101', 'body' => 'Trabajo '.$i, 'kind' => 'offer_help']);
            $p->syncTagsWithType(['Mecánica'], 'category');
        }
        Sanctum::actingAs($viewer);
        $first = $this->getJson('/api/v1/feed?category=Mec%C3%A1nica')->assertOk()->assertJsonCount(10, 'data');
        $cursor = $first->json('next_cursor');
        $seen = array_column($first->json('data'), 'id');
        $this->putJson("/api/v1/neighbors/$author->id/endorsement", ['selected' => true])->assertOk();
        $next = $this->getJson('/api/v1/feed?category=Mec%C3%A1nica&cursor='.urlencode($cursor))->assertOk();
        $this->assertEmpty(array_intersect($seen, array_column($next->json('data'), 'id')));
        $this->getJson('/api/v1/feed?category=Otra&cursor='.urlencode($cursor))->assertUnprocessable();
        Sanctum::actingAs($author);
        $this->getJson('/api/v1/feed?category=Mec%C3%A1nica&cursor='.urlencode($cursor))->assertUnprocessable();
    }
}
