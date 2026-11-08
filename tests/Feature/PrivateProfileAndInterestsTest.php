<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateProfileAndInterestsTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(array $attributes = []): User
    {
        $user = User::factory()->create([...['ubigeo' => '150101', 'dni' => fake()->unique()->numerify('########'), 'birth_date' => '1995-05-16', 'neighborhood' => 'Centro', 'password' => 'DemoPassword123!'], ...$attributes]);
        $user->assignRole('neighbor');

        return $user;
    }

    public function test_private_data_is_only_for_the_authenticated_owner_and_identity_cannot_be_changed(): void
    {
        $a = $this->neighbor();
        $b = $this->neighbor();
        $this->getJson('/api/v1/profile/private')->assertUnauthorized();
        Sanctum::actingAs($a);
        $this->getJson('/api/v1/profile/private')->assertOk()->assertJsonPath('data.dni', $a->dni)->assertJsonPath('data.email', $a->email)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/neighbors/'.$a->id)->assertOk()->assertJsonMissingPath('data.dni')->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.birth_date');
        $this->patchJson('/api/v1/profile/private', ['email' => $a->email, 'ubigeo' => '150101', 'neighborhood' => 'Nuevo', 'dni' => $b->dni, 'name' => 'Otro nombre', 'user_id' => $b->id])->assertUnprocessable();
        $this->assertSame($a->dni, $a->fresh()->dni);
        Sanctum::actingAs($b);
        $this->getJson('/api/v1/profile/private?user_id='.$a->id)->assertOk()->assertJsonPath('data.dni', $b->dni);
    }

    public function test_private_changes_derive_places_from_ubigeo_and_email_requires_the_current_password(): void
    {
        $user = $this->neighbor();
        $oldEmail = $user->email;
        Password::createToken($user);
        Sanctum::actingAs($user);
        $payload = ['email' => 'new-address@example.test', 'phone' => '999888777', 'ubigeo' => '080101', 'neighborhood' => 'San Blas'];
        $this->patchJson('/api/v1/profile/private', $payload)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/v1/profile/private', [...$payload, 'current_password' => 'wrong'])->assertUnprocessable();
        $this->patchJson('/api/v1/profile/private', [...$payload, 'current_password' => 'DemoPassword123!'])->assertOk()->assertJsonPath('data.ubigeo', '080101')->assertJsonPath('data.phone', '999888777');
        $this->assertSame(DB::table('districts')->where('code', '080101')->value('name'), $user->fresh()->district);
        $this->assertSame(DB::table('regions')->where('code', '08')->value('name'), $user->fresh()->region);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
        $this->assertSame('new-address@example.test', $user->fresh()->email);
        $this->patchJson('/api/v1/profile/private', [...$payload, 'ubigeo' => '999999'])->assertUnprocessable();
    }

    public function test_interests_are_persisted_optional_private_and_limited_to_the_catalog(): void
    {
        $user = $this->neighbor();
        $this->getJson('/api/v1/feed/topics')->assertOk()->assertJsonFragment(['id' => 'videojuegos', 'label' => 'Videojuegos']);
        $this->putJson('/api/v1/profile/interests', ['interests' => ['musica']])->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/profile/interests', ['interests' => ['musica', 'videojuegos']])->assertOk()->assertJsonPath('data.feed_interests.0', 'musica');
        $this->assertNotNull($user->fresh()->feed_interests_completed_at);
        $user->withAccessToken($user->createToken('test-profile', ['*'], now()->addHour())->accessToken);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('user.feed_interests.1', 'videojuegos');
        $this->getJson('/api/v1/neighbors/'.$user->id)->assertOk()->assertJsonMissingPath('data.feed_interests');
        $this->putJson('/api/v1/profile/interests', ['interests' => ['invalid-topic']])->assertUnprocessable();
        $this->putJson('/api/v1/profile/interests', ['interests' => ['musica', 'musica']])->assertUnprocessable();
        $this->putJson('/api/v1/profile/interests', ['interests' => []])->assertOk()->assertJsonCount(0, 'data.feed_interests');
    }

    public function test_recent_matches_are_prioritized_without_losing_other_posts_and_emergencies_remain_first(): void
    {
        $user = $this->neighbor(['feed_interests' => ['musica']]);
        $author = $this->neighbor();
        $music = CommunityPost::create(['user_id' => $author->id, 'ubigeo' => '150101', 'kind' => 'moment', 'body' => 'Ensayo de guitarra en la plaza']);
        $unrelated = CommunityPost::create(['user_id' => $author->id, 'ubigeo' => '150101', 'kind' => 'moment', 'body' => 'Reparación de tuberías']);
        $emergency = CommunityPost::create(['user_id' => $user->id, 'ubigeo' => '150101', 'kind' => 'emergency', 'body' => 'Necesitamos apoyo urgente']);
        CommunityPost::create(['user_id' => $author->id, 'ubigeo' => '080101', 'kind' => 'moment', 'body' => 'Música de otra ciudad']);
        Sanctum::actingAs($user);
        $feed = $this->getJson('/api/v1/feed')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame([(string) $emergency->id, (string) $music->id, (string) $unrelated->id], array_column($feed->json('data'), 'id'));
        $feed->assertJsonPath('data.1.recommended', true);
    }

    public function test_personalized_cursor_is_stable_across_new_posts_and_reactions_and_bound_to_preferences(): void
    {
        $user = $this->neighbor(['feed_interests' => ['videojuegos']]);
        $author = $this->neighbor();
        for ($i = 0; $i < 24; $i++) {
            $post = CommunityPost::create(['user_id' => $author->id, 'ubigeo' => '150101', 'kind' => 'moment', 'body' => 'Conversación '.$i]);
            if ($i % 2 === 0) {
                $post->syncTagsWithType(['videojuegos'], 'feed_topic');
            }
        }
        Sanctum::actingAs($user);
        $first = $this->getJson('/api/v1/feed')->assertOk()->assertJsonCount(10, 'data');
        $cursor = $first->json('next_cursor');
        $seen = array_column($first->json('data'), 'id');
        $newPost = CommunityPost::create(['user_id' => $author->id, 'ubigeo' => '150101', 'kind' => 'moment', 'body' => 'Videojuegos nuevos después de abrir el feed']);
        do {
            $page = $this->getJson('/api/v1/feed?cursor='.urlencode($cursor))->assertOk();
            $ids = array_column($page->json('data'), 'id');
            $this->assertEmpty(array_intersect($seen, $ids));
            $seen = [...$seen, ...$ids];
            $cursor = $page->json('next_cursor');
        } while ($cursor);
        $this->assertCount(24, $seen);
        $this->assertNotContains((string) $newPost->id, $seen);
        $user->forceFill(['feed_interests' => ['comida']])->save();
        $this->getJson('/api/v1/feed?cursor='.urlencode($first->json('next_cursor')))->assertUnprocessable();
    }

    public function test_topics_can_be_published_as_text_or_multipart_and_match_preferences(): void
    {
        $user = $this->neighbor(['feed_interests' => ['videojuegos']]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/feed', ['kind' => 'moment', 'body' => 'Una tarde en comunidad', 'topics' => ['videojuegos']])->assertCreated()->assertJsonPath('data.topics.0', 'videojuegos');
        $this->post('/api/v1/feed', ['kind' => 'moment', 'body' => 'Otra tarde compartida', 'topics' => ['videojuegos']], ['Accept' => 'application/json'])->assertCreated();
        $this->getJson('/api/v1/feed')->assertOk()->assertJsonPath('data.0.recommended', true);
        $this->postJson('/api/v1/feed', ['kind' => 'moment', 'body' => 'Inválido', 'topics' => ['not-a-topic']])->assertUnprocessable();
    }
}
