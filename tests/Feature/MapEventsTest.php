<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MapEventsTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(array $attributes = []): User
    {
        $user = User::factory()->create(['ubigeo' => '150101', ...$attributes]);
        $user->assignRole('neighbor');

        return $user;
    }

    private function payload(array $changes = []): array
    {
        return [
            'title' => 'Voluntariado en el parque', 'description' => 'Recogemos residuos y cuidamos el barrio.',
            'category' => 'community', 'place' => 'Parque del barrio', 'has_cost' => false,
            'latitude' => -12.0464, 'longitude' => -77.0428,
            'starts_at' => now()->addHour()->toIso8601String(), 'ends_at' => now()->addHours(3)->toIso8601String(),
            ...$changes,
        ];
    }

    private function createEvent(array $changes = []): int
    {
        return $this->postJson('/api/v1/map-events', $this->payload($changes))->assertCreated()->json('data.id');
    }

    private function position(array $changes = []): array
    {
        return ['latitude' => -12.0464, 'longitude' => -77.0428, 'accuracy' => 10, 'captured_at' => now()->toIso8601String(), ...$changes];
    }

    public function test_categories_and_frames_are_authenticated_and_creating_does_not_give_points(): void
    {
        $this->getJson('/api/v1/map-events/catalog')->assertUnauthorized();
        Sanctum::actingAs($this->neighbor());
        $this->getJson('/api/v1/map-events/catalog')->assertOk()->assertJsonPath('points', 0)->assertJsonCount(4, 'categories')->assertJsonCount(5, 'frames')->assertJsonPath('frames.0.unlocked', false);
        $this->createEvent(['points' => 1000]);
        $this->getJson('/api/v1/map-events/catalog')->assertJsonPath('points', 0);
        $this->getJson('/api/v1/map-events')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.points', 2)->assertJsonPath('data.0.latitude', -12.0464)->assertJsonMissingPath('data.0.location')->assertJsonMissingPath('data.0.photo_path');
    }

    public function test_attendance_requires_enrollment_live_location_event_time_and_within_100_meters(): void
    {
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $id = $this->createEvent();
        $participant = $this->neighbor();
        Sanctum::actingAs($participant);
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position())->assertUnprocessable();
        $this->postJson("/api/v1/map-events/$id/join")->assertOk()->assertJsonPath('data.joined', true);
        $this->postJson("/api/v1/map-events/$id/join")->assertOk()->assertJsonPath('data.participants', 1);
        $this->assertDatabaseCount('empathy_rewards', 0);
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position())->assertUnprocessable();
        $this->travel(70)->minutes();
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position(['longitude' => -77.04]))->assertUnprocessable();
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position(['captured_at' => now()->subMinutes(5)->toIso8601String()]))->assertUnprocessable();
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position(['accuracy' => 150]))->assertUnprocessable();
        foreach ([101, 99] as $meters) {
            $point = DB::selectOne('SELECT ST_X(p::geometry) AS lng, ST_Y(p::geometry) AS lat FROM (SELECT ST_Project(location, ?::double precision, pi()/2) AS p FROM map_events WHERE id = ?) AS position', [$meters, $id]);
            $response = $this->postJson("/api/v1/map-events/$id/check-in", $this->position(['latitude' => $point->lat, 'longitude' => $point->lng]));
            if ($meters > 100) {
                $response->assertUnprocessable();
            } else {
                $response->assertOk()->assertJsonPath('data.attended', true);
            }
        }
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position())->assertOk();
        $this->artisan('events:award-points')->assertSuccessful();
        $this->assertDatabaseCount('empathy_rewards', 1);
        $this->getJson('/api/v1/map-events/catalog')->assertJsonPath('points', 2);
        $this->deleteJson("/api/v1/map-events/$id/join")->assertUnprocessable();
    }

    public function test_subtypes_reward_only_the_server_defined_amount_and_frames_unlock_every_ten_points(): void
    {
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $events = [
            [$this->createEvent(['category' => 'community']), 2],
            [$this->createEvent(['category' => 'emergency']), 3],
            [$this->createEvent(['category' => 'trend']), 1],
            [$this->createEvent(['category' => 'support']), 1],
            [$this->createEvent(['category' => 'support', 'has_cost' => true]), 0],
            [$this->createEvent(['category' => 'emergency']), 3],
        ];
        $participant = $this->neighbor();
        Sanctum::actingAs($participant);
        foreach ($events as [$id]) {
            $this->postJson("/api/v1/map-events/$id/join")->assertOk();
        }
        $this->travel(70)->minutes();
        foreach ($events as [$id, $points]) {
            $this->postJson("/api/v1/map-events/$id/check-in", $this->position())->assertOk();
            $this->assertDatabaseHas('empathy_rewards', ['map_event_id' => $id, 'user_id' => $participant->id, 'points' => $points]);
        }
        $this->getJson('/api/v1/map-events/catalog')->assertJsonPath('points', 10)->assertJsonPath('frames.0.unlocked', true)->assertJsonPath('frames.1.unlocked', false);
        $this->createEvent(['frame' => 'neighbor']);
        $this->postJson('/api/v1/map-events', $this->payload(['frame' => 'friend']))->assertUnprocessable()->assertJsonValidationErrors('frame');
    }

    public function test_districts_blocks_cancellation_and_event_photos_are_enforced(): void
    {
        Storage::fake('local');
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $event = $this->post('/api/v1/map-events', $this->payload(['has_cost' => '0', 'photo' => UploadedFile::fake()->image('park.jpg', 300, 300)]), ['Accept' => 'application/json'])->assertCreated()->json('data');
        $id = $event['id'];
        $this->assertTrue($event['has_photo']);
        $this->assertFalse($event['author']['has_community_avatar']);
        $this->post('/api/v1/profile/community-avatar', ['photo' => UploadedFile::fake()->image('avatar.jpg')])->assertNoContent();
        $this->getJson("/api/v1/map-events/$id/photo")->assertOk()->assertHeader('Content-Type', 'image/webp');
        Sanctum::actingAs($this->neighbor(['ubigeo' => '080101']));
        $this->getJson('/api/v1/map-events')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/map-events/$id/photo")->assertNotFound();
        $this->postJson("/api/v1/map-events/$id/join")->assertNotFound();
        $participant = $this->neighbor();
        Sanctum::actingAs($participant);
        $this->getJson('/api/v1/map-events')->assertOk()->assertJsonPath('data.0.author.has_community_avatar', true)
            ->assertJsonPath('data.0.author.avatar', '')->assertJsonMissingPath('data.0.author.community_avatar_path');
        $this->getJson('/api/v1/neighbors/'.$owner->id.'/community-avatar')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->postJson("/api/v1/map-events/$id/cancel")->assertForbidden();
        DB::table('neighbor_blocks')->insert(['user_id' => $owner->id, 'blocked_user_id' => $participant->id]);
        $this->getJson('/api/v1/map-events')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/neighbors/'.$owner->id.'/community-avatar')->assertNotFound();
        $this->postJson("/api/v1/map-events/$id/join")->assertNotFound();
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/map-events/$id/cancel")->assertNoContent();
        $this->postJson("/api/v1/map-events/$id/join")->assertUnprocessable();
    }

    public function test_scheduler_recovers_a_verified_attendance_once_but_never_awards_an_unverified_signup(): void
    {
        Sanctum::actingAs($owner = $this->neighbor());
        $id = $this->createEvent();
        $a = $this->neighbor();
        $b = $this->neighbor();
        DB::table('map_event_attendances')->insert([
            ['map_event_id' => $id, 'user_id' => $a->id, 'joined_at' => now(), 'verified_at' => now()->addHours(2), 'distance_meters' => 15],
            ['map_event_id' => $id, 'user_id' => $b->id, 'joined_at' => now(), 'verified_at' => null, 'distance_meters' => null],
        ]);
        $this->travel(4)->hours();
        $this->artisan('events:award-points')->assertSuccessful();
        $this->artisan('events:award-points')->assertSuccessful();
        $this->assertDatabaseCount('empathy_rewards', 1);
        $this->assertDatabaseHas('empathy_rewards', ['user_id' => $a->id, 'points' => 2]);
    }

    public function test_filters_distance_bounds_and_invalid_event_details(): void
    {
        Sanctum::actingAs($this->neighbor());
        $id = $this->createEvent();
        $this->getJson('/api/v1/map-events?latitude=-12.0464&longitude=-77.0428')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/map-events?latitude=-13.5134&longitude=-71.9779')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/map-events?category=emergency')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/map-events', $this->payload(['latitude' => 200]))->assertUnprocessable();
        $this->postJson('/api/v1/map-events', $this->payload(['ends_at' => now()->addDays(5)]))->assertUnprocessable();
        $this->postJson('/api/v1/map-events', $this->payload(['frame' => 'neighbor']))->assertUnprocessable();
        $this->postJson("/api/v1/map-events/$id/join")->assertOk();
        $this->deleteJson("/api/v1/map-events/$id/join")->assertNoContent();
        $this->travel(4)->hours();
        $this->postJson("/api/v1/map-events/$id/check-in", $this->position())->assertUnprocessable();
    }

    public function test_owner_can_update_event_and_permissions_are_enforced(): void
    {
        $owner = $this->neighbor();
        Sanctum::actingAs($owner);
        $id = $this->createEvent();

        // Details show
        $this->getJson("/api/v1/map-events/$id")->assertOk()->assertJsonPath('data.is_owner', true);

        // Non-owner gets 403
        $stranger = $this->neighbor();
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/map-events/$id", [
            'title' => 'Intento extraño',
            'description' => 'Texto',
            'place' => 'Lugar',
            'has_cost' => false,
        ])->assertForbidden();

        // Owner can update
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/map-events/$id", [
            'title' => 'Título actualizado',
            'description' => 'Descripción nueva para el evento.',
            'place' => 'Nuevo parque',
            'has_cost' => false,
            'category' => 'community',
            'latitude' => -12.0465,
            'longitude' => -77.0429,
            'starts_at' => now()->addHours(2)->toIso8601String(),
            'ends_at' => now()->addHours(4)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.title', 'Título actualizado');

        $this->assertDatabaseHas('map_events', ['id' => $id, 'title' => 'Título actualizado', 'place' => 'Nuevo parque']);

        // Cancelled event cannot be updated
        $this->postJson("/api/v1/map-events/$id/cancel")->assertNoContent();
        $this->postJson("/api/v1/map-events/$id", [
            'title' => 'Otro cambio',
            'description' => 'Desc',
            'place' => 'Lugar',
            'has_cost' => false,
        ])->assertUnprocessable();
    }
}
