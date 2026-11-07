<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IdentityTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_python_models_enroll_and_replace_photo_in_postgres(): void
    {
        if (getenv('FACE_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Opcional: iniciar Python y ejecutar con FACE_LIVE_TEST=1.');
        }
        $fixture = base_path('.local/downloads/lena.jpg');
        $this->assertFileExists($fixture, 'Descarga la imagen de prueba indicada en README.');
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('vecino_test', DB::connection()->getDatabaseName());
        Storage::fake('local');
        $photo = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($fixture));
        $this->postJson('/api/face/check', ['foto' => $photo])->assertOk()->assertJsonPath('accepted', true);
        $this->postJson('/api/auth/register', [
            'dni' => '00000099', 'name' => 'Prueba técnica', 'email' => 'live-test@example.test',
            'password' => 'test-password-123', 'password_confirmation' => 'test-password-123',
            'ubigeo' => '150101', 'neighborhood' => 'Prueba', 'biometric_consent' => true, 'foto' => $photo,
            'latitude' => -12.04, 'longitude' => -77.03, 'location_captured_at' => now()->subSecond()->toIso8601String(),
            'identity_token' => app(IdentityTicket::class)->issue([
                'dni' => '00000099', 'name' => 'Prueba técnica', 'given_names' => 'Prueba',
                'paternal_surname' => 'Técnica', 'maternal_surname' => 'Test', 'birth_date' => '2000-01-01', 'verification_digit' => 1,
            ]),
        ])->assertCreated()->assertJsonMissingPath('embedding');
        $profile = User::firstOrFail()->faceProfile;
        $this->assertCount(128, $profile->embedding);
        $this->assertSame('sface_yunet', $profile->model);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $profile->model_fingerprint);
        Storage::disk('local')->assertExists($profile->photo_path);
        $this->postJson('/api/profile/photo', ['foto' => $photo, 'biometric_consent' => true])->assertOk();
        Storage::disk('local')->assertMissing($profile->photo_path);
        $this->assertDatabaseCount('face_profiles', 1);
    }
}
