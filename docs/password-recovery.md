# Recuperación de contraseña

Desde el login: **¿Olvidaste tu contraseña?** → correo de registro → enlace → contraseña nueva y confirmación. El usuario vuelve a iniciar sesión con su DNI. El registro ya exige email; no se recuperan cuentas consultando RENIEC ni biometría.

Se usa el broker y la notificación de Laravel, con `password_reset_tokens` existente: token guardado con hash, caducidad de 60 minutos y un solo uso. Una recuperación correcta revoca los tokens Sanctum y las sesiones de base de datos del titular. No altera sesiones de otros vecinos. El correo tiene asunto e instrucciones en español y el enlace apunta a `FRONTEND_URL`, nunca al Host de la petición.

## Relay SMTP del VPS

Las variables están en `.env` y `.env.example`. El ejemplo conserva `MAIL_MAILER=log` para instalaciones sin credenciales; la instancia local utiliza el relay SMTP configurado en su `.env` privado. Con el transporte `log`, los mensajes quedan en el log local de Laravel sin enviar emails reales. El enlace nunca se devuelve en la API. No publicar ni compartir ese log: contiene enlaces de recuperación.

Completar localmente cuando estén disponibles los datos del relay:

```dotenv
FRONTEND_URL=http://127.0.0.1:5173
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_REQUIRE_TLS=true
MAIL_HOST=host-del-relay-del-vps
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=remitente-verificado@tu-dominio.com
MAIL_FROM_NAME=masi
MAIL_TIMEOUT=10
```

Usar el host, puerto y esquema reales del relay. `smtp` admite STARTTLS cuando el servidor lo anuncia; para TLS desde la conexión, usar `smtps` y el puerto que corresponda. `MAIL_FROM_ADDRESS` debe ser el remitente admitido por el relay/Brevo. En despliegue, `FRONTEND_URL` debe ser la URL pública HTTPS de la app, accesible para quien abra el correo.

Después de modificar `.env`, ejecutar `php artisan config:clear`. El envío es síncrono para simplificar la demo; no requiere un worker de colas. La conexión y autenticación SMTP pueden comprobarse sin enviar correos; verificar la entrega requiere un mensaje al destinatario de prueba elegido.

## API

- `POST /api/v1/auth/forgot-password`: `{ "email": "vecina@example.com" }`. Respuesta genérica para correos existentes, inexistentes y solicitudes repetidas; no revela si hay cuenta. Hasta 3 solicitudes/minuto por IP y una notificación/minuto por correo. Un fallo SMTP se registra sin secretos ni email, se elimina ese token y se mantiene la respuesta genérica.
- `POST /api/v1/auth/reset-password`: `email`, `token`, `password` y `password_confirmation`. Mínimo ocho caracteres. Token incorrecto, vencido o reutilizado: 422. Hasta 6 intentos/minuto por IP. No inicia sesión automáticamente.

Las pantallas públicas son `/recuperar-contrasena` y `/restablecer-contrasena?token=...&email=...`. La segunda también funciona si el navegador conservaba una sesión activa; al completar el cambio, limpia esa sesión local. Las pruebas usan `vecino_test` y notificaciones simuladas, sin contactar con tu VPS ni Brevo.
