<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NeighborChatTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(string $district = '150101'): User
    {
        return User::factory()->create(['ubigeo' => $district, 'neighborhood' => 'Mi barrio', 'neighbor_profile' => ['bio' => 'Me gusta colaborar', 'skills' => ['Reparaciones']]]);
    }

    public function test_public_neighbor_profiles_hide_identity_and_are_district_scoped(): void
    {
        $actor = $this->neighbor();
        $neighbor = $this->neighbor();
        $outsider = $this->neighbor('080101');
        $this->getJson('/api/v1/neighbors/'.$neighbor->id)->assertUnauthorized();
        Sanctum::actingAs($actor);
        $this->getJson('/api/v1/neighbors/'.$neighbor->id)->assertOk()->assertJsonPath('data.profile.skills.0', 'Reparaciones')
            ->assertJsonMissingPath('data.dni')->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.latitude')->assertJsonMissingPath('data.face_profile');
        $this->getJson('/api/v1/neighbors/'.$outsider->id)->assertNotFound();
        $this->getJson('/api/v1/neighbors')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_direct_room_is_unique_and_private_and_has_read_tracking(): void
    {
        [$a, $b, $other] = [$this->neighbor(), $this->neighbor(), $this->neighbor()];
        Sanctum::actingAs($a);
        $id = $this->postJson('/api/v1/rooms/direct/'.$b->id)->assertOk()->json('data.id');
        $this->postJson('/api/v1/rooms/direct/'.$b->id)->assertOk()->assertJsonPath('data.id', $id);
        $message = $this->postJson("/api/v1/rooms/$id/messages", ['body' => '¿Coordinamos la colecta?'])->assertCreated()->json('data.id');
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/rooms/direct/'.$a->id)->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/v1/rooms')->assertOk()->assertJsonPath('data.0.unread', 1);
        $this->putJson("/api/v1/rooms/$id/read", ['message_id' => $message])->assertNoContent();
        $this->getJson('/api/v1/rooms')->assertOk()->assertJsonPath('data.0.unread', 0);
        $this->getJson("/api/v1/rooms/$id/messages?after_id=0")->assertOk()->assertJsonPath('data.0.body', '¿Coordinamos la colecta?');
        $this->assertDatabaseCount('chat_rooms', 1);
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/rooms')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/rooms/$id")->assertNotFound();
        $this->getJson("/api/v1/rooms/$id/messages")->assertNotFound();
        $this->postJson("/api/v1/rooms/$id/messages", ['body' => 'Intrusión'])->assertNotFound();
        $this->putJson("/api/v1/rooms/$id/read", ['message_id' => $message])->assertNotFound();
    }

    public function test_block_stops_both_directions_and_invitations_and_unblock_is_idempotent(): void
    {
        [$a, $b, $c] = [$this->neighbor(), $this->neighbor(), $this->neighbor()];
        Sanctum::actingAs($a);
        $room = $this->postJson('/api/v1/rooms/direct/'.$b->id)->json('data.id');
        $this->postJson("/api/v1/rooms/$room/messages", ['body' => 'Historial anterior'])->assertCreated();
        $group = $this->postJson('/api/v1/rooms', ['name' => 'Ayuda', 'members' => [$c->id]])->assertCreated()->json('data.id');
        for ($i = 0; $i < 2; $i++) {
            $this->putJson('/api/v1/neighbors/'.$b->id.'/block', ['blocked' => true])->assertOk();
        }
        $this->assertDatabaseCount('neighbor_blocks', 1);
        $this->getJson('/api/v1/neighbors/blocked')->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/rooms/$room/messages", ['body' => 'No permitido'])->assertForbidden();
        $this->getJson("/api/v1/rooms/$room/messages")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/rooms/$group/members", ['user_id' => $b->id])->assertForbidden();
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/rooms/direct/'.$a->id)->assertForbidden();
        $this->postJson("/api/v1/rooms/$room/messages", ['body' => 'No permitido tampoco'])->assertForbidden();
        $this->putJson('/api/v1/neighbors/'.$a->id.'/block', ['blocked' => false])->assertOk();
        $this->postJson("/api/v1/rooms/$room/messages", ['body' => 'Sigue bloqueado'])->assertForbidden();
        Sanctum::actingAs($a);
        $this->putJson('/api/v1/neighbors/'.$b->id.'/block', ['blocked' => false])->assertOk();
        $this->postJson("/api/v1/rooms/$room/messages", ['body' => 'Podemos conversar de nuevo'])->assertCreated();
    }

    public function test_group_membership_history_and_owner_transfer(): void
    {
        [$a, $b, $c, $external] = [$this->neighbor(), $this->neighbor(), $this->neighbor(), $this->neighbor('080101')];
        Sanctum::actingAs($a);
        $this->postJson('/api/v1/rooms', ['name' => 'Inválido', 'members' => [$external->id]])->assertNotFound();
        $id = $this->postJson('/api/v1/rooms', ['name' => 'Taller vecinal', 'members' => [$b->id]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/rooms/$id/members", ['user_id' => $c->id])->assertOk()->assertJsonCount(3, 'data.members');
        $this->postJson("/api/v1/rooms/$id/messages", ['body' => 'Bienvenidos'])->assertCreated();
        Sanctum::actingAs($b);
        $this->postJson("/api/v1/rooms/$id/members", ['user_id' => $external->id])->assertForbidden();
        $this->putJson('/api/v1/neighbors/'.$c->id.'/block', ['blocked' => true])->assertOk();
        $this->postJson("/api/v1/rooms/$id/messages", ['body' => 'Bloqueo de grupo'])->assertForbidden();
        Sanctum::actingAs($a);
        $this->deleteJson("/api/v1/rooms/$id/membership")->assertNoContent();
        $this->getJson("/api/v1/rooms/$id/messages")->assertNotFound();
        $this->assertSame($b->id, ChatRoom::findOrFail($id)->owner_id);
        Sanctum::actingAs($b);
        $this->deleteJson("/api/v1/rooms/$id/membership")->assertNoContent();
        Sanctum::actingAs($c);
        $this->deleteJson("/api/v1/rooms/$id/membership")->assertNoContent();
        $this->assertDatabaseMissing('chat_rooms', ['id' => $id]);
    }

    public function test_comment_likes_are_idempotent_and_cannot_target_another_post_or_district(): void
    {
        $a = $this->neighbor();
        $post = CommunityPost::create(['user_id' => $a->id, 'ubigeo' => $a->ubigeo, 'kind' => 'need_help', 'body' => 'Ayuda']);
        $other = CommunityPost::create(['user_id' => $a->id, 'ubigeo' => $a->ubigeo, 'kind' => 'offer_help', 'body' => 'Puedo ayudar']);
        $comment = $post->comments()->create(['user_id' => $a->id, 'body' => '¡Me apunto!']);
        Sanctum::actingAs($this->neighbor());
        $path = "/api/v1/feed/$post->id/comments/$comment->id/reaction";
        for ($i = 0; $i < 2; $i++) {
            $this->putJson($path, ['selected' => true])->assertOk()->assertJsonPath('likes', 1);
        }
        $this->getJson("/api/v1/feed/$post->id/comments")->assertOk()->assertJsonPath('data.0.liked', true)->assertJsonPath('data.0.likes', 1)->assertJsonPath('data.0.author_id', $a->id);
        $this->putJson("/api/v1/feed/$other->id/comments/$comment->id/reaction", ['selected' => true])->assertNotFound();
        $this->putJson($path, ['selected' => false])->assertOk()->assertJsonPath('likes', 0);
        Sanctum::actingAs($this->neighbor('080101'));
        $this->putJson($path, ['selected' => true])->assertNotFound();
    }

    public function test_message_pagination_does_not_skip_or_duplicate_messages(): void
    {
        [$a, $b] = [$this->neighbor(), $this->neighbor()];
        $room = ChatRoom::create(['kind' => 'direct', 'owner_id' => $a->id, 'direct_key' => "$a->id:$b->id"]);
        $room->members()->attach([$a->id, $b->id]);
        for ($i = 0; $i < 65; $i++) {
            $room->messages()->create(['user_id' => $a->id, 'body' => 'Mensaje '.$i]);
        }
        Sanctum::actingAs($b);
        $first = $this->getJson("/api/v1/rooms/$room->id/messages")->assertOk()->assertJsonCount(30, 'data');
        $older = $this->getJson("/api/v1/rooms/$room->id/messages?before_id=".$first->json('older_cursor'))->assertOk()->assertJsonCount(30, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($older->json('data'), 'id')));
        $this->getJson("/api/v1/rooms/$room->id/messages?after_id=0")->assertOk()->assertJsonCount(50, 'data');
        $this->postJson("/api/v1/rooms/$room->id/messages", ['body' => ' '])->assertUnprocessable();
        $this->putJson("/api/v1/rooms/$room->id/read", ['message_id' => 999999])->assertUnprocessable();
    }
}
