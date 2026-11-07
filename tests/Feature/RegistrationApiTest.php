<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IdentityTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scrall.basic_token' => 'test-basic', 'face_recognition.token' => 'test-face']);
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    private function provider(array $override = []): array
    {
        return ['message' => 'found data', 'result' => array_replace([
            'D_NUDNI' => '00000003', 'D_PRENOMBRES' => 'ANA', 'D_APEPATERNO' => 'PRUEBA',
            'D_APEMATERNO' => 'TEST', 'D_FENACIMIENTO' => '16/05/2005', 'D_DIGITOVERIFICACION' => 3,
        ], $override)];
    }

    public function test_dni_provider_contract_and_ticket_are_normalized(): void
    {
        Http::fake(['api.scrall.net/*' => Http::response($this->provider())]);
        $result = $this->postJson('/api/v1/identity/dni', ['dni' => '00000003'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.birth_date', '2005-05-16')->assertJsonPath('data.name', 'ANA PRUEBA TEST');
        $identity = app(IdentityTicket::class)->read($result->json('identity_token'), '00000003');
        $this->assertSame('ANA', $identity['given_names']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.scrall.net/v1/reniec/basico'
            && $request->hasHeader('Authorization', 'Basic test-basic')
            && $request->hasHeader('Content-Type', 'application/json') && $request['dni'] === '00000003');
    }

    public function test_provider_errors_and_invalid_dates_fail_without_leaking_provider_body(): void
    {
        $sequence = Http::sequence()->push(['secret' => 'upstream-secret'], 401)
            ->push(['message' => 'quota'], 429)->push($this->provider(['D_NUDNI' => '99999999']))
            ->push($this->provider(['D_FENACIMIENTO' => '31/02/2005']))
            ->push(['result' => null]);
        Http::fake(['api.scrall.net/*' => $sequence]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/identity/dni', ['dni' => '00000003'])->assertStatus(503)
                ->assertDontSee('upstream-secret')->assertDontSee('test-basic');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_location_catalogs_preserve_hierarchy(): void
    {
        $this->getJson('/api/v1/locations/regions')->assertOk()->assertJsonCount(25, 'data');
        $this->getJson('/api/v1/locations/regions/15/provinces')->assertOk()->assertJsonFragment(['code' => '1501', 'region_code' => '15']);
        $this->getJson('/api/v1/locations/provinces/1501/districts')->assertOk()->assertJsonFragment(['code' => '150101', 'province_code' => '1501']);
        $this->getJson('/api/v1/locations/regions/99/provinces')->assertNotFound();
    }

    public function test_native_registration_uses_provider_identity_and_assigns_only_neighbor(): void
    {
        Http::fake(['api.scrall.net/*' => Http::response($this->provider()), '*/extract' => Http::response([
            'accepted' => true, 'embedding' => array_fill(0, 128, 1 / sqrt(128)), 'model' => 'sface_yunet',
            'model_fingerprint' => str_repeat('a', 64), 'quality_policy' => 'public_face_framing_v8',
        ])]);
        $ticket = $this->postJson('/api/v1/identity/dni', ['dni' => '00000003'])->assertOk()->json('identity_token');
        $image = UploadedFile::fake()->image('face.jpg', 100, 100);
        $payload = ['dni' => '00000003', 'identity_token' => $ticket, 'email' => 'native@example.test',
            'name' => 'Nombre manipulado', 'region' => 'Falso', 'role' => 'admin',
            'password' => 'test-password-123', 'password_confirmation' => 'test-password-123',
            'ubigeo' => '150101', 'neighborhood' => 'Barrio', 'latitude' => -12.04, 'longitude' => -77.03,
            'location_captured_at' => now()->subSecond()->toIso8601String(), 'biometric_consent' => true,
            'foto' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents($image->getRealPath()))];
        $this->postJson('/api/v1/auth/register', array_replace($payload, ['latitude' => null]))->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $response = $this->postJson('/api/v1/auth/register', $payload)->assertCreated()->assertJsonPath('user.name', 'ANA PRUEBA TEST')
            ->assertJsonPath('user.region', 'LIMA')->assertJsonPath('token_type', 'Bearer');
        $user = User::firstOrFail();
        $this->assertTrue($user->hasRole('neighbor'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertSame('LIMA', $user->district);
        $this->assertSame('2005-05-16', $user->birth_date->format('Y-m-d'));
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/profile')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_expired_tampered_or_mismatched_tickets_are_rejected(): void
    {
        $tickets = app(IdentityTicket::class);
        $ticket = $tickets->issue(['dni' => '00000003']);
        $this->travel(11)->minutes();
        try {
            $tickets->read($ticket, '00000003');
            $this->fail('Expired ticket accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('identity_token', $e->errors());
        }
        $this->travelBack();
        foreach ([[$ticket, '00000004'], ['tampered', '00000003']] as [$value, $dni]) {
            try {
                $tickets->read($value, $dni);
                $this->fail('Invalid ticket accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('identity_token', $e->errors());
            }
        }
    }

    public function test_media_is_private_and_taxonomy_writes_require_permission(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('neighbor');
        Sanctum::actingAs($owner);
        $response = $this->post('/api/v1/media', ['file' => UploadedFile::fake()->image('test.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('data.id');
        $this->get('/api/v1/media/'.$id)->assertOk();
        $this->postJson('/api/v1/taxonomy', ['type' => 'hashtag', 'name' => 'barrio'])->assertForbidden();
        $other = User::factory()->create();
        $other->assignRole('neighbor');
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/media/'.$id)->assertNotFound();
        $this->deleteJson('/api/v1/media/'.$id)->assertNotFound();
        $other->assignRole('admin');
        $this->postJson('/api/v1/taxonomy', ['type' => 'hashtag', 'name' => '#barrio'])->assertCreated();
        $this->getJson('/api/v1/taxonomy?type=hashtag&q=barrio')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/taxonomy?type=category')->assertOk()->assertJsonCount(6, 'data');
    }
}
