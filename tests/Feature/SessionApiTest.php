<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_checks_registered_credentials_and_profile_reports_expiry(): void
    {
        $user = User::factory()->create(['dni' => '00000071', 'password' => 'test-password-123']);
        $this->postJson('/api/v1/auth/login', ['dni' => '00000071', 'password' => 'wrong-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('dni');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $response = $this->postJson('/api/v1/auth/login', ['dni' => '00000071', 'password' => 'test-password-123'])
            ->assertOk()->assertJsonPath('user.id', $user->id);
        $token = $response->json('token');
        $profile = $this->withToken($token)->getJson('/api/v1/profile')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertNotNull($profile->json('expires_at'));
        $this->assertSame(PersonalAccessToken::findToken($token)->expires_at->toIso8601String(), $profile->json('expires_at'));
    }

    public function test_missing_forged_expired_and_revoked_tokens_cannot_access_profile(): void
    {
        $user = User::factory()->create();
        $expired = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $revoked = $user->createToken('revoked')->plainTextToken;
        PersonalAccessToken::findToken($revoked)->delete();
        foreach ([null, 'forged-token', $expired, $revoked] as $token) {
            $this->app['auth']->forgetGuards();
            $headers = $token ? ['Authorization' => 'Bearer '.$token] : [];
            $this->getJson('/api/v1/profile', $headers)->assertUnauthorized();
            $this->postJson('/api/v1/auth/logout', [], $headers)->assertUnauthorized();
        }
    }

    public function test_logout_revokes_only_current_token_and_prevents_reuse(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current', ['*'], now()->addDay())->plainTextToken;
        $other = $user->createToken('other-device', ['*'], now()->addDay())->plainTextToken;
        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertNull(PersonalAccessToken::findToken($current));
        $this->assertNotNull(PersonalAccessToken::findToken($other));
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/v1/profile')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/profile')->assertOk();
    }
}
