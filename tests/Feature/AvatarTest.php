<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_is_private_and_upload_does_not_replace_biometric_template(): void
    {
        Storage::fake('local');
        config(['face_recognition.token' => 'test-only']);
        $this->getJson('/api/v1/profile/avatar')->assertUnauthorized();
        $user = User::factory()->create();
        $user->assignRole('neighbor');
        Sanctum::actingAs($user);
        $image = UploadedFile::fake()->image('face.jpg');
        $bytes = file_get_contents($image->getRealPath());
        Storage::disk('local')->put('faces/test.jpg', $bytes);
        $user->faceProfile()->create(['photo_path' => 'faces/test.jpg', 'embedding' => array_fill(0, 128, 1 / sqrt(128)), 'model' => 'sface_yunet', 'model_fingerprint' => str_repeat('a', 64), 'quality_policy' => 'test', 'enrolled_at' => now()]);
        $this->get('/api/v1/profile/avatar')->assertOk();
        $accepted = ['accepted' => true, 'model' => 'sface_yunet', 'model_fingerprint' => str_repeat('a', 64), 'quality_policy' => 'test', 'embedding' => array_fill(0, 128, 1 / sqrt(128))];
        Http::fake(['*/extract' => Http::sequence()->push($accepted)
            ->push(array_replace($accepted, ['embedding' => array_fill(0, 128, -1 / sqrt(128))]))
            ->push(array_replace($accepted, ['model_fingerprint' => str_repeat('b', 64)]))]);
        $this->postJson('/api/v1/profile/avatar', ['foto' => 'data:image/jpeg;base64,'.base64_encode($bytes)])->assertOk();
        $this->assertNotNull($user->fresh()->getFirstMedia('avatar'));
        $this->assertSame('faces/test.jpg', $user->fresh()->faceProfile->photo_path);
        $this->assertCount(128, $user->fresh()->faceProfile->embedding);
        $this->get('/api/v1/profile/avatar')->assertOk();
        $previous = $user->fresh()->getFirstMedia('avatar')->id;
        $payload = ['foto' => 'data:image/jpeg;base64,'.base64_encode($bytes)];
        $this->postJson('/api/v1/profile/avatar', $payload)->assertUnprocessable()->assertJsonValidationErrors('foto');
        $this->assertSame($previous, $user->fresh()->getFirstMedia('avatar')->id);
        $this->postJson('/api/v1/profile/avatar', $payload)->assertStatus(503);
        $this->assertSame($previous, $user->fresh()->getFirstMedia('avatar')->id);
        $this->assertSame('faces/test.jpg', $user->fresh()->faceProfile->photo_path);
    }
}
