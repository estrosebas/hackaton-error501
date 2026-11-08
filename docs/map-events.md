# Mapa comunitario y puntos de empatía

El botón central abre `/mapa/crear`. «Publicar» en Inicio conserva `/publicar` y el compositor del feed. La pestaña Mapa (`/vecinos`) muestra eventos reales del distrito en lugar de personas con coordenadas de ejemplo. Cada evento puede tener una foto compartida, un círculo del color de su categoría y un marco adicional desbloqueado.

## Categorías y asistencia

| Categoría | Puntos por asistencia |
| --- | --- |
| Eventos comunitarios | 2 |
| Emergencias | 3 |
| Tendencias | 1 |
| Apoyo sin costo | 1 |
| Apoyo con costo | 0 |

Crear e inscribirse no otorgan puntos. El servidor fija el valor al crear; el cliente no puede elegirlo. Cada evento tiene horario de inicio/fin (hasta 48 horas), tipo, referencia y punto elegido con GPS, pin o coordenadas. El distrito se toma de la cuenta. Una foto es opcional y se recodifica como WebP sin EXIF; si falta, el marcador usa el símbolo de la categoría. La foto del evento se comparte explícitamente con el distrito y no reutiliza la biometría privada.

Para registrar asistencia es necesario estar inscrito, dentro del horario, sin cancelación, con una captura posterior a la inscripción y de los últimos dos minutos. Se exige precisión declarada de hasta 100 m y `ST_DWithin` sobre `geography(Point,4326)` a un máximo de 100 m del evento. No se suma la precisión al radio permitido. Las coordenadas de asistencia no se guardan ni se muestran; solo la distancia verificada y la hora. Las coordenadas del evento sí son públicas dentro del distrito.

El usuario puede pulsar «Validar mi asistencia». Al activar ubicación en el mapa, se intenta validar cada 45 segundos para eventos inscritos en curso, mientras el mapa esté abierto y la página visible. El navegador cerrado no puede proporcionar una posición nueva. GPS del cliente es adecuado para esta demo, pero no garantiza ausencia de suplantación de ubicación.

La acreditación se realiza al validar y se recupera cada minuto con `events:award-points`. El ledger tiene una restricción única por evento y usuario; la asistencia se bloquea en una transacción, por lo que reintentos y scheduler no duplican puntos. Se puede cancelar una inscripción antes de verificarla. Cancelar un evento bloquea nuevas asistencias, conservando las recompensas ya acreditadas. No hay edición de horario/ubicación que invalide pruebas anteriores.

Los cinco marcos se desbloquean a 10, 20, 30, 40 y 50 puntos, sin gastarlos. El servidor comprueba el saldo al crear un evento con marco. El borde de categoría permanece visible incluso con un marco decorativo adicional.

## API autenticada `/api/v1`

| Método | Ruta | Uso |
| --- | --- | --- |
| GET | `/map-events/catalog` | Categorías, tipos, saldo propio y cinco marcos con `unlocked` |
| GET | `/map-events` | Eventos vigentes del distrito; filtro `category`; `latitude` y `longitude` opcionales limitan a 30 km. Máximo 200 y señal `has_more` |
| POST | `/map-events` | `title`, `description`, `category`, `type` opcional validado por categoría, `place`, `starts_at`, `ends_at`, `latitude`, `longitude`, `has_cost`, `frame` opcional y `photo` opcional multipart (JPEG/PNG/WebP, 2 MiB, 2400 px) |
| POST | `/map-events/{event}/join` | Inscripción idempotente |
| DELETE | `/map-events/{event}/join` | Retirar inscripción sin asistencia |
| POST | `/map-events/{event}/check-in` | `latitude`, `longitude`, `accuracy`, `captured_at`; comprueba distancia y horario |
| POST | `/map-events/{event}/cancel` | Cancelar, solo el creador |
| GET | `/map-events/{event}/photo` | Foto compartida, con Bearer, mismo distrito y sin bloqueos |

La lista respeta bloqueos en ambos sentidos. No devuelve DNI, correo, embeddings ni posiciones de asistentes. Las respuestas privadas no se cachean. El saldo no es editable en el perfil.

## Entorno local

`LocalMapEventsSeeder` crea 12 eventos de demostración (tres por categoría) con vecinos sintéticos y fotos ilustrativas de espacios/actividades, a 65–475 m de una ubicación guardada. Se ejecuta después de `LocalCommunitySeeder`, exclusivamente con `APP_ENV=local` y la demo habilitada. Usa `DEMO_MAP_ANCHOR_USER_ID` si se indica; de lo contrario toma la cuenta real con la captura de ubicación más reciente (y la más nueva si no hay fecha). Sin cuenta con distrito y coordenadas no inventa una ubicación. Así cada desarrollador registra su propia cuenta y los eventos aparecen cerca de ella al ejecutar el seeder o volver a arrancar.

Las fotos están en `database/data/demo/events`, con origen, autor y SHA256 en `manifest.json`; no hay descargas durante el seed. Se validan y recodifican a WebP en almacenamiento privado, sin usar fotos de perfil ni biométricas. Son ilustrativas: no representan los sitios reales de las coordenadas. Ver [créditos](../database/data/demo/events/README.md). `scripts/download-demo-event-photos.ps1` permite recuperar los archivos originales comprobando sus hashes.

Las emergencias son simulacros explícitos. Repetir el seed no duplica eventos ni acredita puntos. Los eventos con inscripciones conservan sus ubicaciones, horarios y detalles; solo se actualiza su imagen demo anterior si era un retrato. El almacenamiento reutiliza las ilustraciones por hash. Para cargarlo manualmente: `php artisan db:seed --class=LocalMapEventsSeeder`.

`scripts/setup-postgis.ps1` instala PostGIS 3.6.2 para PostgreSQL 17 desde OSGeo, verifica SHA256 y habilita la extensión en vecino y vecino_test usando el administrador local. No cambia APP_KEY ni contraseñas. Conserva las DLL existentes de PostgreSQL. `setup-local.ps1` y `start-local.ps1` lo incluyen antes de migrar. El usuario de la app no necesita privilegios de superusuario.

Las conexiones PostgreSQL usan UTC; el navegador convierte horarios locales a ISO 8601 con zona. En otro entorno, un administrador debe instalar PostGIS y ejecutar `CREATE EXTENSION IF NOT EXISTS postgis` antes de las migraciones.

`start-local.ps1` levanta además `php artisan schedule:work` y `stop-local.ps1` detiene ese proceso mediante su PID del proyecto. En producción se necesita el cron normal de Laravel para ejecutar `schedule:run` cada minuto.

Pruebas: `php artisan test --filter=MapEventsTest` usa vecino_test y PostGIS real. No consulta Scrall ni ubicación externa.

Referencias: [PostGIS Windows](https://postgis.net/documentation/getting_started/install_windows/released_versions/) y [ST_DWithin con distancias en metros](https://postgis.net/docs/ST_DWithin.html).
