<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(string $district = '150101'): User
    {
        $user = User::factory()->create(['ubigeo' => $district]);
        $user->assignRole('neighbor');

        return $user;
    }

    public function test_notices_come_from_real_district_posts_and_reads_belong_to_the_viewer(): void
    {
        $this->postJson('/api/v1/notifications/sync')->assertUnauthorized();
        $viewer = $this->neighbor();
        $reader = $this->neighbor();
        $author = $this->neighbor();
        $otherDistrict = $this->neighbor('080101');
        $blocked = $this->neighbor();

        Sanctum::actingAs($author);
        $activity = $this->postJson('/api/v1/feed', ['kind' => 'activity', 'body' => 'Feria en el parque'])
            ->assertCreated()->json('data.id');
        $help = $this->postJson('/api/v1/feed', ['kind' => 'need_help', 'body' => 'Necesito ayuda'])
            ->assertCreated()->json('data.id');
        $alert = $this->postJson('/api/v1/feed', ['kind' => 'alert', 'body' => 'Alerta del distrito'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/feed', ['kind' => 'moment', 'body' => 'Otro tipo de publicación'])->assertCreated();
        CommunityPost::create(['user_id' => $author->id, 'ubigeo' => $author->ubigeo, 'kind' => 'alert', 'body' => 'Ejemplo', 'demo_key' => 'sample-alert']);
        CommunityPost::create(['user_id' => $author->id, 'ubigeo' => $author->ubigeo, 'kind' => 'alert', 'body' => 'Antigua', 'created_at' => now()->subDays(8)]);

        Sanctum::actingAs($otherDistrict);
        $foreign = $this->postJson('/api/v1/feed', ['kind' => 'alert', 'body' => 'Otro distrito'])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/feed/$alert")->assertNotFound();
        Sanctum::actingAs($blocked);
        $hidden = $this->postJson('/api/v1/feed', ['kind' => 'alert', 'body' => 'Bloqueado'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/neighbors/{$viewer->id}/block", ['blocked' => true])->assertOk();

        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/feed', ['kind' => 'need_help', 'body' => 'Mi propio aviso'])->assertCreated();
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $alert)->assertJsonPath('data.0.read', false)->assertJsonPath('data.0.author.avatar', null)
            ->assertJsonPath('data.1.id', $help)->assertJsonPath('data.2.id', $activity);
        $this->getJson("/api/v1/feed/$alert")->assertOk()->assertJsonPath('data.paragraphs.0', 'Alerta del distrito');
        $this->putJson("/api/v1/notifications/$foreign/read")->assertNotFound();
        $this->putJson("/api/v1/notifications/$hidden/read")->assertNotFound();
        $this->getJson("/api/v1/feed/$hidden")->assertNotFound();
        $this->putJson("/api/v1/notifications/$alert/read")->assertNoContent();
        $this->putJson("/api/v1/notifications/$alert/read")->assertNoContent();
        $this->assertDatabaseCount('community_notification_reads', 1);
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonPath('data.0.read', true);

        Sanctum::actingAs($reader);
        $items = $this->postJson('/api/v1/notifications/sync')->assertOk()->json('data');
        $this->assertFalse(collect($items)->firstWhere('id', $alert)['read']);

        Sanctum::actingAs($author);
        $this->deleteJson("/api/v1/feed/$alert")->assertNoContent();
        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_presence_requires_opt_in_and_expires_without_heartbeats(): void
    {
        $viewer = $this->neighbor();
        $author = $this->neighbor();
        $outside = $this->neighbor('080101');

        Sanctum::actingAs($author);
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonPath('sharing_presence', false);
        $this->assertNull($author->fresh()->community_active_at);
        $this->putJson('/api/v1/notifications/presence', ['sharing' => true])->assertNoContent();
        Sanctum::actingAs($outside);
        $this->putJson('/api/v1/notifications/presence', ['sharing' => true])->assertNoContent();

        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonCount(1, 'active')
            ->assertJsonPath('active.0.id', $author->id)->assertJsonMissingPath('active.0.latitude');
        $this->travel(60)->seconds();
        Sanctum::actingAs($author);
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonPath('sharing_presence', true);
        $this->travel(40)->seconds();
        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonCount(1, 'active');
        $this->travel(51)->seconds();
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonCount(0, 'active');

        Sanctum::actingAs($author);
        $this->putJson('/api/v1/notifications/presence', ['sharing' => false])->assertNoContent();
        $this->postJson('/api/v1/notifications/sync')->assertOk()->assertJsonPath('sharing_presence', false);
        $this->assertNull($author->fresh()->community_active_at);
    }

    public function test_community_avatar_is_an_explicit_scoped_thumbnail_and_can_be_removed(): void
    {
        Storage::fake('local');
        $owner = $this->neighbor();
        $viewer = $this->neighbor();
        $url = "/api/v1/neighbors/{$owner->id}/community-avatar";
        $this->getJson($url)->assertUnauthorized();
        $owner->addMedia(UploadedFile::fake()->image('private.jpg'))->toMediaCollection('avatar', 'local');

        Sanctum::actingAs($viewer);
        $this->getJson($url)->assertNotFound();
        Sanctum::actingAs($owner);
        $this->post('/api/v1/profile/community-avatar', ['photo' => UploadedFile::fake()->image('community.jpg', 400, 300)])
            ->assertNoContent();
        $original = $owner->fresh()->community_avatar_path;
        $this->postJson('/api/v1/feed', ['kind' => 'alert', 'body' => 'Aviso con imagen'])->assertCreated();

        Sanctum::actingAs($this->neighbor('080101'));
        $this->getJson($url)->assertNotFound();
        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/notifications/sync')->assertOk()
            ->assertJsonPath('data.0.author.avatar', pathinfo($original, PATHINFO_FILENAME))
            ->assertJsonMissingPath('data.0.author.community_avatar_path');
        $response = $this->get($url)->assertOk()->assertHeaderContains('Cache-Control', 'no-store');
        $size = getimagesizefromstring($response->streamedContent());
        $this->assertSame([160, 120, 'image/webp'], [$size[0], $size[1], $size['mime']]);
        $this->putJson("/api/v1/neighbors/{$owner->id}/block", ['blocked' => true])->assertOk();
        $this->getJson($url)->assertNotFound();
        $this->putJson("/api/v1/neighbors/{$owner->id}/block", ['blocked' => false])->assertOk();

        Sanctum::actingAs($owner);
        $this->post('/api/v1/profile/community-avatar', ['photo' => UploadedFile::fake()->image('other.jpg')])->assertNoContent();
        Storage::disk('local')->assertMissing($original);
        $replacement = $owner->fresh()->community_avatar_path;
        $this->deleteJson('/api/v1/profile/community-avatar')->assertNoContent();
        Storage::disk('local')->assertMissing($replacement);
        $this->assertNotNull($owner->fresh()->getFirstMedia('avatar'));
        Sanctum::actingAs($viewer);
        $this->getJson($url)->assertNotFound();
    }
}
