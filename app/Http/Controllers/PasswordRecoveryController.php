<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordRecoveryController extends Controller
{
    private function account(string $email): ?User
    {
        return User::where('email', $email)->first()
            ?? User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    public function requestLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = $this->account($data['email']);
        try {
            Password::sendResetLink(['email' => $user?->email ?? $data['email']]);
        } catch (TransportExceptionInterface $error) {
            // Do not expose account existence, SMTP credentials or reset links.
            if ($user) {
                Password::deleteToken($user);
            }
            Log::warning('No se pudo enviar un correo de recuperación.', ['mailer' => config('mail.default')]);
        }

        return response()->json(['message' => 'Si ese correo está registrado, recibirás un enlace para crear una contraseña nueva. Revisa también tu carpeta de spam.'])
            ->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:256'],
            'password' => ['required', 'confirmed', 'max:255', PasswordRule::min(8)],
        ]);
        $data['email'] = $this->account($data['email'])?->email ?? $data['email'];
        $status = DB::transaction(function () use ($data) {
            // Consume the database token once, including concurrent submissions.
            $broker = config('auth.defaults.passwords');
            DB::table(config("auth.passwords.$broker.table"))->where('email', $data['email'])->lockForUpdate()->first();

            return Password::reset($data, function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                event(new PasswordReset($user));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Este enlace no es válido o ya venció. Solicita uno nuevo.']);
        }

        return response()->json(['message' => 'Tu contraseña fue actualizada. Inicia sesión con tu DNI y la contraseña nueva.'])
            ->header('Cache-Control', 'no-store');
    }
}
