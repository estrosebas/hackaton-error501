<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_mailer_renders_a_working_recovery_link_without_external_delivery(): void
    {
        config(['mail.default' => 'array', 'app.frontend_url' => 'http://127.0.0.1:5173']);
        $user = User::factory()->create(['email' => 'mailer+demo@example.test']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $mail = $messages->first()->getOriginalMessage();
        $this->assertSame('Restablece tu contraseña de masi', $mail->getSubject());
        $this->assertSame($user->email, $mail->getTo()[0]->getAddress());
        $this->assertSame(1, preg_match('~href="([^"]*restablecer-contrasena[^"]*)"~', $mail->getHtmlBody(), $matches));
        parse_str(parse_url(html_entity_decode($matches[1]), PHP_URL_QUERY), $query);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $query['email'], 'token' => $query['token'],
            'password' => 'MailerDemo123!', 'password_confirmation' => 'MailerDemo123!',
        ])->assertOk();
        $this->assertTrue(Hash::check('MailerDemo123!', $user->fresh()->password));
    }

    public function test_request_is_private_throttled_and_token_is_only_in_the_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'vecina@example.test']);
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'VECINA@EXAMPLE.TEST'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->assertExactJson($known->json());
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson($known->json());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertTrue(Hash::check($notification->token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token')));
        $known->assertDontSee($notification->token)->assertJsonMissingPath('token')->assertJsonMissingPath('url');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertTooManyRequests();
    }

    public function test_successful_reset_is_single_use_changes_login_and_revokes_only_that_users_sessions(): void
    {
        $user = User::factory()->create(['dni' => '87650001', 'password' => 'Before123!', 'email' => 'reset@example.test']);
        $oldRemember = $user->getRememberToken();
        $device = $user->createToken('device', ['*'], now()->addDays(1));
        $user->createToken('another-device');
        $other = User::factory()->create();
        $other->createToken('keep-this-device');
        foreach ([$user, $other] as $item) {
            DB::table('sessions')->insert(['id' => 'recovery-session-'.$item->id, 'user_id' => $item->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        }
        $token = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'After123!', 'password_confirmation' => 'After123!'];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk()->assertJsonMissingPath('token');
        $this->assertTrue(Hash::check('After123!', $user->fresh()->password));
        $this->assertNotEquals($oldRemember, $user->fresh()->getRememberToken());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        $this->withToken($device->plainTextToken)->getJson('/api/v1/profile')->assertUnauthorized();
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['dni' => $user->dni, 'password' => 'Before123!'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['dni' => $user->dni, 'password' => 'After123!'])->assertOk();
    }

    public function test_wrong_email_invalid_expired_and_unconfirmed_tokens_do_not_change_password(): void
    {
        $user = User::factory()->create(['password' => 'Before123!']);
        $other = User::factory()->create();
        $token = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'After123!', 'password_confirmation' => 'After123!'];
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['email' => $other->email]))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['token' => 'wrong-token']))->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['password_confirmation' => 'SomethingElse']))->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $this->assertTrue(Hash::check('Before123!', $user->fresh()->password));
    }

    public function test_notification_uses_the_configured_frontend_instead_of_the_request_host(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://masi.example.test']);
        $user = User::factory()->create(['email' => 'vecina+test@example.test']);
        $this->withHeader('Host', 'untrusted.example.test')->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $mail = $notification->toMail($user);
        $this->assertSame('Restablece tu contraseña de masi', $mail->subject);
        $this->assertSame('https://masi.example.test', 'https://'.parse_url($mail->actionUrl, PHP_URL_HOST));
        parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertSame($user->email, $query['email']);
        $this->assertSame($notification->token, $query['token']);
        $this->assertStringNotContainsString('untrusted.example.test', $mail->actionUrl);
    }

    public function test_mail_failure_does_not_leak_accounts_or_secrets_and_allows_a_new_link(): void
    {
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('smtp-password-must-not-leak'));
        Log::spy();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->assertDontSee('smtp-password-must-not-leak');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Log::shouldHaveReceived('warning')->once()->with('No se pudo enviar un correo de recuperación.', ['mailer' => config('mail.default')]);
    }
}
