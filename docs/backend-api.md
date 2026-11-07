# Servicios de registro y perfil

Perfiles públicos, likes de comentarios, scroll y salas privadas: [contrato de conversaciones](neighbor-conversations.md).

Base local: `http://127.0.0.1:8000/api/v1`. Enviar `Accept: application/json`. El frontend separado en `frontend/` conecta su registro a estos endpoints mediante el proxy Vite de `/api`. El login usa DNI y contraseña registrados, valida la sesión con el servidor y protege las vistas. El muro y las actividades se conectan a la API. El directorio de vecinos usa perfiles de ejemplo.

## Registro

1. `POST /identity/dni` con `{ "dni": "12345678" }`. Laravel consulta Scrall usando la credencial privada de `.env`. Devuelve `data` con `dni`, `name`, `given_names`, `paternal_surname`, `maternal_surname`, `birth_date` (YYYY-MM-DD), `verification_digit`; también `identity_token` cifrado y `expires_in: 600`.
2. Consultar catálogos y escoger distrito. Pedir autorización para obtener la posición actual desde el navegador o `@capacitor/geolocation` cuando se conecte el cliente.
3. Capturar la foto; opcionalmente `POST /face/check` con `foto` como data URL permite prevalidarla. El servidor vuelve a procesarla al registrar.
4. `POST /auth/register` con este contrato:

```json
{
  "dni": "12345678",
  "identity_token": "valor recibido al consultar el DNI",
  "email": "vecino@example.com",
  "phone": "999999999",
  "password": "minimo-8-caracteres",
  "password_confirmation": "minimo-8-caracteres",
  "ubigeo": "150101",
  "neighborhood": "Santa Rosa",
  "latitude": -12.0464,
  "longitude": -77.0428,
  "location_accuracy": 15,
  "location_captured_at": "fecha ISO 8601 de captura",
  "biometric_consent": true,
  "foto": "data:image/jpeg;base64,..."
}
```

La ubicación es obligatoria; `location_accuracy` y `phone` son opcionales. La captura debe ser de los últimos 30 minutos, no una fecha futura. La aplicación almacena la posición declarada por el dispositivo, sin rastreo en segundo plano. Los nombres, apellidos, nacimiento y dígito se toman del ticket de Scrall; región/provincia/distrito se derivan del código UBIGEO existente en PostgreSQL. No se aceptan roles, permisos ni nombres de ubicación elegidos libremente en el registro. Cada nuevo usuario recibe solo el rol `neighbor`.

Devuelve 201 con usuario, `token` de Sanctum y `token_type: Bearer`. El token dura 30 días; enviarlo como `Authorization: Bearer <token>` para el resto de endpoints privados. Nunca guardar la clave Basic de Scrall en el frontend. El ticket puede reutilizarse para corregir el formulario durante sus 10 minutos de vigencia; el DNI y correo únicos impiden registrar la misma cuenta dos veces.

El registro confirma cuenta y plantilla facial dentro de una transacción. Si el DNI no fue consultado, el ticket venció, falta ubicación o falla Python, no crea una cuenta parcial. Consultar datos de DNI no demuestra que el rostro sea de su titular; YuNet/SFace no evalúan prueba de vida. No se usa el nacimiento para imponer una edad mínima en esta etapa.

## Endpoints disponibles

Todas las rutas de esta tabla se agregan a `/api/v1`.

| Método | Ruta | Acceso y finalidad |
| --- | --- | --- |
| POST | /identity/dni | Público, 6/min/IP; consulta y ticket de identidad |
| GET | /locations/regions | Público; regiones con code/name |
| GET | /locations/regions/{code2}/provinces | Público; provincias de una región |
| GET | /locations/provinces/{code4}/districts | Público; distritos de una provincia |
| POST | /face/check | Público, 12/min/IP; acepta foto, nunca devuelve embedding |
| POST | /auth/register | Público, 6/min/IP; registro completo |
| POST | /auth/login | Público, 6/min/IP; dni/password, devuelve token |
| POST | /auth/logout | Bearer; revoca el token actual |
| GET | /profile | Bearer; perfil, roles, permisos y estado de foto propios |
| PATCH | /profile/location | Bearer y profile.update; latitude, longitude, location_captured_at y accuracy opcional |
| GET | /profile/photo | Bearer; foto biométrica propia privada |
| POST | /profile/photo | Bearer y profile.update, 6/min; foto + biometric_consent |
| GET | /media | Bearer; adjuntos propios |
| POST | /media | Bearer y media.manage-own; multipart file JPEG/PNG/WebP, máximo 4 MiB y 4000x4000 |
| GET | /media/{id} | Bearer; contenido privado, solo propietario |
| DELETE | /media/{id} | Bearer y media.manage-own; solo propietario |
| GET | /taxonomy?type=category | Público; categorías, q opcional |
| GET | /taxonomy?type=hashtag | Público; hashtags, q opcional |
| POST | /taxonomy | Bearer y taxonomy.manage; type category/hashtag y name |

Los medios de Spatie se guardan en disco `local` privado. Las fotografías y plantillas biométricas conservan su servicio privado específico; no se convierten en adjuntos públicos. No devolver `getUrl()` de Spatie para archivos privados. Las categorías y hashtags usan tipos separados de `spatie/laravel-tags`, listos para vincularse a publicaciones futuras.

422 indica datos incorrectos o calidad facial rechazada; 401 falta de token; 403 permiso insuficiente; 404 ubicación/medio inexistente o medio ajeno; 429 límite de solicitudes. 503 indica Scrall/Python inaccesible, credencial inválida, cuota del proveedor o respuesta inconsistente. No se propagan respuestas internas del proveedor ni se reintentan consultas cobrables automáticamente.

Las rutas previas `/api/auth/*` conservan sesiones/CSRF y comparten la validación estricta de registro. La pantalla de prueba anterior en Laravel no se adaptó al nuevo ticket; el registro vigente está en la aplicación separada. Las rutas `/api/v1` usan Bearer. En desarrollo Vite usa proxy; un despliegue con orígenes separados requiere configurar CORS/HTTPS con sus dominios reales.

## Catálogos y paquetes

- `spatie/laravel-permission`: roles neighbor, moderator y admin; ningún administrador se crea automáticamente.
- `spatie/laravel-medialibrary`: archivos privados del usuario, con autorización por propietario.
- `spatie/laravel-tags`: tipos category/hashtag; seis categorías iniciales.
- `laravel/sanctum`: tokens móviles revocables.
- `@capacitor/core`, `@capacitor/cli`, `@capacitor/geolocation`: instalados en frontend; configuración webDir dist. No se generaron proyectos Android/iOS ni se conectaron pantallas.

UBIGEO procede de [RitchieRD/ubigeos-peru-data](https://github.com/RitchieRD/ubigeos-peru-data), revisión fija `23cd3666b19f32bccdf36d90243c2a4462d6202c`, CC0. Los archivos originales, licencia y versión están en `database/data/ubigeo`; `catalog.json` normaliza las claves. Es un catálogo comunitario, no un padrón oficial certificado.

## Personalización del perfil

`GET /profile/options` devuelve sin sesión el catálogo de `skills`, `interests` y `availability`. El tercer paso del registro puede enviar `neighbor_profile` a `POST /auth/register` con los siguientes campos opcionales:

- `bio`: texto de hasta 280 caracteres.
- `skills` e `interests`: hasta cinco opciones distintas por grupo tomadas del catálogo.
- `availability`: una opción del catálogo o `null`.
- `can_help`: booleano que indica si la persona quiere mostrar que puede ayudar.

El servidor conserva esta selección y la devuelve al titular en `GET /profile`. Las cuentas anteriores y los registros que omiten el paso pueden tener `neighbor_profile` nulo.

## Feed comunitario

Todas las rutas requieren Sanctum y operan únicamente dentro del UBIGEO del usuario autenticado:

| Método | Ruta bajo `/api/v1` | Función |
| --- | --- | --- |
| GET | `/feed?kind=need_help&saved=1&cursor=...` | Lista paginada, diez publicaciones por página; filtros opcionales |
| POST | `/feed` | Crear con `body` (máximo 2000 caracteres) y `kind`. Multipart opcional con hasta cuatro archivos `photos[]` (JPEG, PNG o WebP, 1.5 MiB cada uno, hasta 2400 × 2400). El campo singular `photo` conserva su límite de 2 MiB |
| GET | `/feed/{post}/photo/{index?}` | Foto WebP de la publicación por índice desde cero. Por defecto devuelve la portada, disponible para cuentas autenticadas del mismo distrito |
| GET | `/feed/{post}/comments?cursor=...` | Comentarios paginados, veinte por página |
| POST | `/feed/{post}/comments` | Comentar con `body` (máximo 500 caracteres) |
| PUT | `/feed/{post}/reaction` | Establecer reacción con `selected: boolean` |
| PUT | `/feed/{post}/bookmark` | Establecer guardado con `selected: boolean` |
| PATCH | `/feed/{post}` | Propietario establece `status: open` o `resolved` |
| DELETE | `/feed/{post}` | Propietario o moderador retira la publicación |

Categorías `moment`, `need_help`, `offer_help`, `collaboration`, `initiative`, `activity` y `alert`. Un `moment` puede contener solo fotos. Para programar una actividad, enviar juntos `activity_title` (hasta 100 caracteres), `activity_starts_at` (fecha futura ISO 8601 con zona) y `activity_location` (hasta 160 caracteres). La respuesta expone estos campos como `activity: { title, starts_at, location }` e indica cuántas fotos contiene con `photo_count`. Conserva `has_photo` para clientes previos.

Las listas devuelven `data` y `next_cursor`. Las modificaciones devuelven `data`, salvo comentarios (mensaje) y eliminación (204). Las fotos publicadas se recodifican con PHP GD y WebP para eliminar metadatos y se guardan en el disco privado. Las fotos biométricas y los adjuntos personales mantienen el acceso exclusivo del propietario. El servidor extrae hasta cinco hashtags mediante Spatie Tags.

## Instalación y verificación

```powershell
composer install
php artisan migrate
php artisan db:seed
npm --prefix frontend ci
npm --prefix frontend run build
```

Variables privadas: `SCRALL_BASE_URL=https://api.scrall.net` y `SCRALL_BASIC_TOKEN` con el valor base64 que sigue a Basic. La clave queda únicamente en `.env`, ignorado por Git. No copiarla a `.env.example` ni `.env.testing`.

Pruebas: `php artisan test`. `FACE_LIVE_TEST=1` habilita la integración con el microservicio facial real usando un DNI sintético y ticket generado en la prueba; no gasta consultas Scrall. Las pruebas de Scrall usan su contrato HTTP simulado; la consulta real se valida por separado sin almacenar sus datos personales.

El perfil devuelve `expires_at` del token actual. El cliente comprueba la sesión en cada navegación protegida, al recuperar foco, periódicamente y al vencer. Un 401 limpia el almacenamiento local. El logout revoca únicamente el token actual; otros dispositivos conservan su sesión.

## Comparación facial al cambiar fotos

El avatar nuevo requiere extracción SFace y comparación coseno con la plantilla cifrada del registro. El modelo y su huella deben coincidir; una referencia ausente, inválida o incompatible bloquea la actualización. FACE_MATCH_THRESHOLD configura el mínimo de similitud (0.5 por defecto, sujeto a calibración con capturas reales). Un rostro rechazado retorna 422 sin sustituir el avatar. Este control también se aplica al endpoint de actualización biométrica. La comparación es probabilística y no constituye prueba de vida.
