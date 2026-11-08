# Servicios de registro y perfil

Recuperación por correo y configuración del relay SMTP: [recuperación de contraseña](password-recovery.md).

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
| PATCH | /profile/presentation | Bearer y profile.update. Actualiza la presentación de la cuenta autenticada. |
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

`PATCH /profile/presentation` recibe los campos que cambian dentro de `neighbor_profile` y devuelve el perfil actualizado en `data`. Para borrar una selección envía `[]` en `skills` o `interests`, `null` en `bio` o `availability`, o `false` en `can_help`. Los campos omitidos conservan su valor.

## Feed comunitario

### Visto bueno vecinal y búsqueda

El visto bueno respalda al vecino, independientemente de sus publicaciones/comentarios y de los «Me gusta». `GET /neighbors/{neighbor}/endorsement` devuelve `{data:{neighbor_id,count,selected}}`. `PUT` en la misma ruta recibe `{selected:boolean}`: establecer `true` repetidamente mantiene un único respaldo por pareja emisor/receptor; `false` lo retira. No hay autorrespaldos, cruces de distrito ni respaldos entre cuentas bloqueadas. No concede puntos de empatía. Los contadores son agregados, sin publicar listas ni datos privados de quienes respaldaron. La BD impone la unicidad y los cambios usan el mismo orden de bloqueo de usuarios que las relaciones vecinales.

El cliente comparte el estado del botón por vecino: publicaciones, comentarios y perfiles muestran el mismo respaldo. Mi perfil muestra los vistos buenos recibidos. Este contador no certifica una contratación, un oficio ni la calidad de un servicio.

`GET /feed` acepta `q` (hasta 100 caracteres; todas las palabras deben aparecer en el texto, sin distinguir mayúsculas/acentos) y `category` (nombre de categoría, hashtag o identificador/etiqueta de tema). Los filtros se combinan con `kind` y `saved`. Al filtrar categoría, ordena por vistos buenos recibidos por el autor y después por publicación más reciente; los respaldos son generales al vecino y no específicos de esa categoría. Sin categoría, la búsqueda textual es cronológica; el feed sin filtros conserva su personalización.

La búsqueda por categoría fija el orden de resultados al abrirla para que los cambios de respaldos no dupliquen publicaciones durante el scroll. Su cursor cifrado está vinculado al usuario, distrito y filtros, y dura 15 minutos. Refrescar actualiza el ranking. Por búsqueda admite hasta 5000 resultados; si se supera, pide afinar con palabras. Cada página vuelve a aplicar el ámbito y los permisos actuales. No cambia el ranking del mapa ni su asistencia.

El mapa tiene eventos y puntos de empatía propios, separados del feed. Ver rutas, categorías, inscripción y validación espacial en [map-events.md](map-events.md).

### Datos privados y gustos

`GET /profile/private` devuelve únicamente al titular su DNI, nombre validado, fecha de nacimiento, correo, teléfono, UBIGEO, región, provincia, distrito y barrio; usa `Cache-Control: no-store`. Estos datos no se añaden al perfil público de vecinos.

`PATCH /profile/private` recibe `email`, `phone` opcional, `ubigeo` y `neighborhood`. Cambiar el correo exige `current_password` e invalida los enlaces anteriores de recuperación. DNI, nombre y nacimiento permanecen bloqueados. Región, provincia y distrito se derivan del catálogo UBIGEO del servidor.

`GET /feed/topics` ofrece el catálogo público de temas (`id`, `label`, `emoji`). `PUT /profile/interests` guarda `interests`, una lista de hasta ocho identificadores distintos del catálogo. Una lista vacía permite omitir el paso. Ambos casos registran que la bienvenida fue completada; los gustos se pueden cambiar desde Mi perfil y no se muestran a otros vecinos.

Al publicar se puede enviar `topics` con hasta tres identificadores del catálogo, también en multipart mediante `topics[]`. El feed general prioriza emergencias recientes, después coincidencias de los últimos 14 días con los gustos (temas, categorías, hashtags o palabras del texto), y conserva el resto de publicaciones. Mantiene el ámbito del distrito registrado; no calcula distancia GPS. Los filtros de tipo y guardados conservan orden cronológico.

`recommended` identifica coincidencias y `trending` señala publicaciones recientes con al menos cinco reacciones y comentarios combinados. Esta tendencia es interna al feed. La paginación personalizada usa cursores cifrados asociados al usuario, distrito y gustos, y fija el conjunto de publicaciones al iniciar para evitar duplicados cuando llegan publicaciones nuevas. Cambiar gustos o distrito requiere recargar desde la primera página.

Todas las rutas requieren Sanctum y operan únicamente dentro del UBIGEO del usuario autenticado:

| Método | Ruta bajo `/api/v1` | Función |
| --- | --- | --- |
| GET | `/feed?kind=need_help&saved=1&cursor=...` | Lista paginada, diez publicaciones por página; filtros opcionales |
| POST | `/feed` | Crear con `body` (máximo 2000 caracteres) y `kind`. Multipart opcional con hasta cuatro archivos `photos[]` (JPEG, PNG o WebP, 1.5 MiB cada uno, hasta 2400 × 2400). El campo singular `photo` conserva su límite de 2 MiB |
| GET | `/feed/{post}` | Abre una publicación del mismo distrito. Devuelve 404 si existe un bloqueo con el autor |
| GET | `/feed/{post}/photo/{index?}` | Foto WebP de la publicación por índice desde cero. Por defecto devuelve la portada, disponible para cuentas autenticadas del mismo distrito |
| GET | `/feed/{post}/comments?cursor=...` | Comentarios paginados, veinte por página |
| POST | `/feed/{post}/comments` | Comentar con `body` (máximo 500 caracteres) |
| PUT | `/feed/{post}/reaction` | Establecer reacción con `selected: boolean` |
| PUT | `/feed/{post}/bookmark` | Establecer guardado con `selected: boolean` |
| PATCH | `/feed/{post}` | Propietario establece `status: open` o `resolved` |
| DELETE | `/feed/{post}` | Propietario o moderador retira la publicación |

### Avisos del distrito y presencia

| Método | Ruta | Comportamiento |
| --- | --- | --- |
| POST | `/notifications/sync` | Devuelve `data` (avisos), `active` (vecinos activos), `sharing_presence`, `my_avatar` y `has_district`. Renueva la presencia de la cuenta si activó la opción |
| GET | `/notifications/emergencies` | Devuelve en `data` las emergencias abiertas publicadas por otros vecinos en el UBIGEO registrado de la cuenta |
| PUT | `/notifications/{post}/read` | Marca un aviso como leído para la cuenta autenticada. Operación idempotente |
| PUT | `/notifications/presence` | Guarda la preferencia `sharing: boolean`, desactivada por defecto |
| POST | `/profile/community-avatar` | Comparte una miniatura WebP de hasta 160 px sin metadatos. Multipart con `photo` en JPEG, PNG o WebP, hasta 2 MiB |
| DELETE | `/profile/community-avatar` | Retira la miniatura comunitaria |
| GET | `/neighbors/{neighbor}/community-avatar` | Sirve la miniatura comunitaria con Bearer a vecinos del mismo distrito sin bloqueos entre ambos |

La bandeja coloca las emergencias abiertas antes de los avisos ordinarios. Los avisos ordinarios conservan el límite de 30 publicaciones de los últimos siete días. Cada aviso incluye resumen, fecha, estado de lectura y autor. El avatar se representa con una versión opaca y queda vacío si el vecino no compartió una imagen.

La vista se actualiza cada 20 segundos mientras la aplicación está visible. El listado de presencia muestra hasta 20 vecinos del distrito que optaron por compartir su estado y sincronizaron en los últimos 90 segundos. Una alerta vecinal nueva también muestra un aviso en pantalla mientras la aplicación está visible. La miniatura comunitaria se sube por separado del avatar privado y de la foto biométrica.

La app visible consulta emergencias cada cinco segundos y al volver a primer plano. La señal aparece en pantalla y sigue disponible en la bandeja hasta que la publicación se marque como atendida o se retire. La recepción funciona aunque la cuenta tenga la presencia oculta. Cuando hay un bloqueo entre el autor y el destinatario, el aviso contiene un texto genérico y `author: null`, y se restringe el acceso al contenido de la publicación.

Categorías `moment`, `need_help`, `offer_help`, `collaboration`, `initiative`, `activity`, `alert` y `emergency`. Un `moment` puede contener solo fotos. Para programar una actividad, enviar juntos `activity_title` (hasta 100 caracteres), `activity_starts_at` (fecha futura ISO 8601 con zona) y `activity_location` (hasta 160 caracteres). La respuesta expone estos campos como `activity: { title, starts_at, location }` e indica cuántas fotos contiene con `photo_count`. Conserva `has_photo` para clientes previos.

Las listas devuelven `data` y `next_cursor`. Las modificaciones devuelven `data`, salvo comentarios (mensaje) y eliminación (204). Las fotos publicadas se recodifican con PHP GD y WebP para eliminar metadatos y se guardan en el disco privado. Las fotos biométricas y los adjuntos personales mantienen el acceso exclusivo del propietario. El servidor extrae hasta cinco hashtags mediante Spatie Tags.

### Mercadito

Las rutas requieren Bearer. Los anuncios disponibles se comparten en el distrito de registro entre vecinos sin bloqueos. El servidor toma el distrito y el barrio del vendedor de su perfil. Los anuncios vendidos quedan en Mis anuncios.

| Método | Ruta | Comportamiento |
| --- | --- | --- |
| GET | `/listings` | Devuelve `items`, `nextCursor` y `total`. Filtros `view=all\|mine\|saved`, `scope=nearby\|neighborhood`, `q` y `cursor`. `nearby` abarca el distrito y `neighborhood` filtra por barrio |
| POST | `/listings` | Multipart con `requestId` (UUID estable para reintentos), `title`, `price` (PEN, hasta dos decimales), `category`, `condition`, `description` y `photo` (JPEG, PNG o WebP, hasta 2 MiB y 2400 × 2400 px) |
| GET | `/listings/{listing}` | Devuelve el anuncio visible con vendedor, ubicación, estado y favorito de la cuenta |
| GET | `/listings/{listing}/photo` | Sirve la foto WebP sin metadatos desde almacenamiento privado, con la misma visibilidad que el detalle |
| PUT | `/listings/{listing}/favorite` | Establece `saved: boolean` para la cuenta |
| PATCH | `/listings/{listing}` | El propietario marca el anuncio como vendido con `sold: true` |
| DELETE | `/listings/{listing}` | El propietario elimina el anuncio, la foto y sus guardados |

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
