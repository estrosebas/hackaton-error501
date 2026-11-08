<?php

namespace Tests\Feature;

use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceListingTest extends TestCase
{
    use RefreshDatabase;

    private function neighbor(array $attributes = []): User
    {
        $user = User::factory()->create(['ubigeo' => '150101', 'neighborhood' => 'San Blas', ...$attributes]);
        $user->assignRole('neighbor');

        return $user;
    }

    public function test_listings_and_saved_items_are_account_scoped_and_persist_across_requests(): void
    {
        Storage::fake('local');
        $owner = $this->neighbor();
        $buyer = $this->neighbor(['neighborhood' => 'San Cristóbal']);
        $outsider = $this->neighbor(['ubigeo' => '080101']);
        $this->getJson('/api/v1/listings')->assertUnauthorized();

        Sanctum::actingAs($owner);
        $payload = [
            'requestId' => (string) Str::uuid(),
            'title' => 'Bicicleta de paseo',
            'price' => '320.50',
            'category' => 'Bicicletas',
            'condition' => 'Buen estado',
            'description' => 'Bicicleta para pasear por el barrio.',
            'neighborhood' => 'Barrio inventado',
            'ubigeo' => $outsider->ubigeo,
        ];
        $this->postJson('/api/v1/listings', $payload)->assertUnprocessable()->assertJsonValidationErrors('photo');
        $upload = fn () => $this->post('/api/v1/listings', [...$payload, 'photo' => UploadedFile::fake()->image('bike.png')], ['Accept' => 'application/json']);
        $id = $upload()->assertCreated()->assertJsonPath('neighborhood', 'San Blas')
            ->assertJsonPath('seller.id', $owner->id)->assertJsonPath('owned', true)->assertJsonMissingPath('photo_path')->json('id');
        $upload()->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('marketplace_listings', 1);
        $path = MarketplaceListing::findOrFail($id)->photo_path;

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/listings')->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/v1/listings/$id")->assertNotFound();
        $this->getJson("/api/v1/listings/$id/photo")->assertNotFound();

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/listings?scope=neighborhood')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/listings?q=PASEO')->assertOk()->assertJsonPath('items.0.id', $id);
        $this->getJson('/api/v1/listings?q=%25')->assertOk()->assertJsonPath('total', 0);
        $this->get("/api/v1/listings/$id/photo")->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->putJson("/api/v1/listings/$id/favorite", ['saved' => true])->assertOk()->assertJsonPath('saved', true);
        $this->putJson("/api/v1/listings/$id/favorite", ['saved' => true])->assertOk();
        $this->assertDatabaseCount('marketplace_favorites', 1);
        $this->getJson('/api/v1/listings?view=saved')->assertOk()->assertJsonPath('items.0.saved', true);
        $this->patchJson("/api/v1/listings/$id", ['sold' => true])->assertForbidden();
        $this->deleteJson("/api/v1/listings/$id")->assertForbidden();
        $this->putJson("/api/v1/neighbors/{$owner->id}/block", ['blocked' => true])->assertOk();
        $this->getJson('/api/v1/listings?view=saved')->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/v1/listings/$id/photo")->assertNotFound();
        $this->putJson("/api/v1/neighbors/{$owner->id}/block", ['blocked' => false])->assertOk();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/listings?view=saved')->assertOk()->assertJsonPath('total', 0);
        $this->patchJson("/api/v1/listings/$id", ['sold' => true])->assertOk()->assertJsonPath('sold', true);
        $this->getJson('/api/v1/listings?view=mine')->assertOk()->assertJsonPath('items.0.sold', true);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/listings?view=saved')->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/v1/listings/$id")->assertNotFound();
        $this->getJson("/api/v1/listings/$id/photo")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/listings/$id")->assertNoContent();
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('marketplace_favorites', 0);
    }
}
