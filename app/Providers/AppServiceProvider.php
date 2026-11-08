<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $resetUrl = fn ($user, string $token) => rtrim(config('app.frontend_url'), '/').'/restablecer-contrasena?'.http_build_query([
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);
        ResetPassword::createUrlUsing($resetUrl);
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject('Restablece tu contraseña de masi')
            ->greeting('Hola, '.$user->name)
            ->line('Recibimos una solicitud para cambiar la contraseña de tu cuenta en masi.')
            ->action('Crear contraseña nueva', $resetUrl($user, $token))
            ->line('El enlace vence en '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos y solo puede utilizarse una vez.')
            ->line('Si no solicitaste este cambio, puedes ignorar este correo.')
            ->salutation('El equipo de masi'));
    }
}
