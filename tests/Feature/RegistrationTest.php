<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IdentityTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['face_recognition.token' => 'test-only']);
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function photo(): string
    {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'dni' => '00000001', 'name' => 'Vecina de prueba', 'email' => 'vecina@example.test',
            'password' => 'test-password-123', 'password_confirmation' => 'test-password-123',
            'ubigeo' => '150101', 'neighborhood' => 'Barrio de prueba',
            'biometric_consent' => true, 'foto' => $this->photo(),
            'latitude' => -12.04, 'longitude' => -77.03, 'location_captured_at' => now()->subSecond()->toIso8601String(),
            'identity_token' => app(IdentityTicket::class)->issue([
                'dni' => '00000001', 'name' => 'Vecina de prueba', 'given_names' => 'Vecina',
                'paternal_surname' => 'de', 'maternal_surname' => 'prueba', 'birth_date' => '2000-01-01', 'verification_digit' => 1,
            ]),
        ], $overrides);
    }

    private function accepted(array $overrides = []): array
    {
        return array_replace([
            'accepted' => true, 'reason' => 'ok', 'model' => 'sface_yunet',
            'model_fingerprint' => str_repeat('a', 64), 'quality_policy' => 'public_face_framing_v8',
            'embedding' => array_fill(0, 128, 1 / sqrt(128)), 'liveness' => 'not_evaluated',
        ], $overrides);
    }

    public function test_registration_persists_private_enrollment_and_starts_session(): void
    {
        Http::fake(['*/extract' => Http::response($this->accepted())]);
        $this->postJson('/api/auth/register', $this->payload())
            ->assertCreated()->assertJsonPath('user.name', 'Vecina de prueba')
            ->assertJsonMissingPath('user.dni')->assertJsonMissingPath('embedding');
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(password_verify('test-password-123', $user->password));
        $profile = $user->faceProfile;
        $this->assertCount(128, $profile->embedding);
        $this->assertNotEquals(json_encode($profile->embedding), DB::table('face_profiles')->value('embedding'));
        Storage::disk('local')->assertExists($profile->photo_path);
        $this->getJson('/api/auth/user')->assertOk()->assertJsonPath('face_enrolled', true)
            ->assertJsonMissingPath('user.face_profile.embedding');
        $this->get('/api/profile/photo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_rejected_or_unavailable_service_never_creates_account_or_photo(): void
    {
        $cases = [
            [200, ['accepted' => false, 'reason' => 'multiple_faces'], 422],
            [429, ['detail' => 'busy'], 503],
            [500, ['detail' => 'error'], 503],
            [200, ['accepted' => true], 503],
            [200, $this->accepted(['embedding' => array_fill(0, 128, 0)]), 503],
        ];
        $sequence = Http::sequence();
        foreach ($cases as [$status, $body]) {
            $sequence->push($body, $status);
        }
        Http::fake(['*/extract' => $sequence]);
        foreach ($cases as [$status, $body, $expected]) {
            $this->postJson('/api/auth/register', $this->payload())->assertStatus($expected);
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('face_profiles', 0);
            $this->assertEmpty(Storage::disk('local')->allFiles());
        }
    }

    public function test_missing_consent_bad_dni_and_invalid_photo_are_rejected_before_inference(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['biometric_consent' => false, 'dni' => '123']))
            ->assertUnprocessable()->assertJsonValidationErrors(['biometric_consent', 'dni']);
        $this->postJson('/api/auth/register', $this->payload(['foto' => 'data:image/jpeg;base64,YmFk']))
            ->assertUnprocessable()->assertJsonValidationErrors('foto');
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_and_logout_use_dni_and_password(): void
    {
        $user = User::factory()->create(['dni' => '00000002', 'password' => 'test-password-123']);
        $this->postJson('/api/auth/login', ['dni' => '00000002', 'password' => 'wrong'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/login', ['dni' => '00000002', 'password' => 'test-password-123'])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_photo_requires_session_and_replacement_keeps_only_new_photo(): void
    {
        $this->postJson('/api/profile/photo', $this->payload())->assertUnauthorized();
        $this->getJson('/api/profile/photo')->assertUnauthorized();
        Http::fake(['*/extract' => Http::response($this->accepted())]);
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();
        $user = User::firstOrFail();
        $oldPath = $user->faceProfile->photo_path;
        $this->postJson('/api/profile/photo', ['foto' => $this->photo(), 'biometric_consent' => true])->assertOk();
        $newPath = $user->fresh()->faceProfile->photo_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertDatabaseCount('face_profiles', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_duplicate_dni_and_email_are_rejected_without_calling_python(): void
    {
        User::factory()->create(['dni' => '00000001', 'email' => 'vecina@example.test']);
        $this->postJson('/api/auth/register', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors(['dni', 'email']);
        Http::assertNothingSent();
    }

    public function test_check_never_returns_template_to_browser(): void
    {
        Http::fake(['*/check' => Http::response($this->accepted())]);
        $this->postJson('/api/face/check', ['foto' => $this->photo()])
            ->assertOk()->assertJsonPath('accepted', true)->assertJsonMissingPath('embedding');
        $this->assertDatabaseCount('face_profiles', 0);
    }
}
