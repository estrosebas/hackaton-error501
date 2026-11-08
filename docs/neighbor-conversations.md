# Conversaciones entre vecinos

El nombre o foto de un autor del feed abre **Ver perfil** y **Escribir mensaje**. La cabecera lleva a `/mensajes`. Se conservan los colores, tarjetas, avatares, modales y botones de Neff.

Las salas son propias de Laravel/PostgreSQL: conversación directa única por pareja y grupos privados de hasta 30 participantes, sin amistad previa. No integran un servidor Matrix, federación ni cifrado de extremo a extremo. La vista consulta actualizaciones cada tres segundos mientras está activa y la pestaña visible. El historial persiste en PostgreSQL.

Solo los miembros acceden a una sala y sus mensajes. El propietario del grupo añade vecinos del distrito, que entran directamente y pueden leer el historial compartido. Cualquier miembro puede salir; otro asume la propiedad si sale el propietario. La última salida elimina el grupo y sus mensajes. Las conversaciones directas conservan su historial.

Un bloqueo impide mensajes en ambos sentidos, nuevas conversaciones e invitaciones compartidas. En un grupo existente, las dos personas entre las que hay bloqueo no pueden enviar al grupo mientras ambas permanezcan; los demás continúan. El historial anterior sigue accesible a miembros actuales. Solo quien estableció el bloqueo puede retirarlo. Se gestiona desde perfiles, participantes y la lista de bloqueados. No cambia la visibilidad del feed.

## API

Rutas bajo `/api/v1`, con Sanctum Bearer. Los perfiles no exponen DNI, email, coordenadas, medios privados ni biometría. Solo los retratos demo locales tienen avatar compartido; las fotos reales mantienen acceso exclusivo del propietario.

| Método | Ruta | Contrato |
| --- | --- | --- |
| GET | `/neighbors?q=nombre` | Hasta 50 vecinos del distrito, búsqueda por nombre |
| GET | `/neighbors/{id}` | Perfil, `blocked_by_me`, `can_message` |
| GET | `/neighbors/blocked` | Bloqueados por el usuario, incluso si cambiaron de distrito |
| PUT | `/neighbors/{id}/block` | `{ "blocked": true }`, idempotente |
| GET | `/rooms?page=1` | `data`, `next_page`; miembros, último mensaje, no leídos, `can_send` |
| POST | `/rooms/direct/{neighborId}` | Abre o recupera la misma sala |
| POST | `/rooms` | `{ "name": "Talleres", "members": [2, 3] }`; creador incluido |
| GET | `/rooms/{id}` | Datos de la sala |
| POST | `/rooms/{id}/members` | `{ "user_id": 4 }`, propietario del grupo |
| DELETE | `/rooms/{id}/membership` | Salir de un grupo |
| GET | `/rooms/{id}/messages` | Últimos 30 cronológicos, `older_cursor` |
| GET | `/rooms/{id}/messages?before_id=100` | Página anterior |
| GET | `/rooms/{id}/messages?after_id=100` | Hasta 50 nuevos en orden ascendente |
| POST | `/rooms/{id}/messages` | `{ "body": "Hola" }`, máximo 2000 caracteres |
| PUT | `/rooms/{id}/read` | `{ "message_id": 100 }`; de esa sala, lectura monotónica |
| PUT | `/feed/{post}/comments/{comment}/reaction` | `{ "selected": true }`, retorna `liked`, `likes`; idempotente |

El feed incorpora `author_id`; los comentarios, `author_id`, `liked` y `likes`. Los likes validan distrito y relación publicación/comentario. Las salas ajenas retornan 404; los bloqueos, 403 al enviar. Crear, invitar, enviar y bloquear tienen límites de frecuencia. La UI conserva borradores tras errores.

El feed carga automáticamente por cursor al aproximarse al final. Evita peticiones simultáneas, ignora filtros anteriores y se detiene ante errores. Reintentar recupera la página fallida sin un botón para cargar más.

`php artisan test` usa exclusivamente `vecino_test` y comprueba privacidad, permisos, bloqueos, invitaciones, salidas, lectura, paginación y likes. La prueba de navegador usa usuarios temporales en esa misma base con un backend independiente.
