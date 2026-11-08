<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_presentation_can_be_updated_and_cleared_by_its_owner(): void
    {
        $owner = User::factory()->create(['ubigeo' => '150101']);
        $owner->assignRole('neighbor');
        $other = User::factory()->create(['ubigeo' => '150101']);
        $path = '/api/v1/profile/presentation';
        $profile = [
            'bio' => 'Me gusta compartir recetas.',
            'skills' => ['Cocina'],
            'interests' => ['Talleres', 'Ferias'],
            'availability' => 'Fines de semana',
            'can_help' => true,
        ];

        $this->patchJson($path, ['neighbor_profile' => $profile])->assertUnauthorized();
        Sanctum::actingAs($other);
        $this->patchJson($path, ['neighbor_profile' => $profile])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->patchJson($path, ['neighbor_profile' => $profile])->assertOk()->assertJsonPath('data', $profile);
        $changes = ['interests' => [], 'availability' => null, 'can_help' => false];
        $expected = array_replace($profile, $changes);
        $this->patchJson($path, ['neighbor_profile' => $changes])->assertOk()->assertJsonPath('data', $expected);
        $this->assertSame($expected, $owner->fresh()->neighbor_profile);
        $this->assertNull($other->fresh()->neighbor_profile);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/neighbors/'.$owner->id)->assertOk()->assertJsonPath('data.profile', $expected);
    }

    public function test_profile_presentation_rejects_invalid_choices_without_saving(): void
    {
        $owner = User::factory()->create(['neighbor_profile' => ['interests' => ['Ferias']]]);
        $owner->assignRole('neighbor');
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/profile/presentation', ['neighbor_profile' => [
            'skills' => ['Cocina', 'Cocina'],
            'interests' => ['Etiqueta inventada'],
            'availability' => 'Ahora mismo',
            'role' => 'admin',
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            'neighbor_profile', 'neighbor_profile.skills.1', 'neighbor_profile.interests.0', 'neighbor_profile.availability',
        ]);
        $this->assertSame(['interests' => ['Ferias']], $owner->fresh()->neighbor_profile);
    }
}
