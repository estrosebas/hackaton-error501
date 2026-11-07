# masi · Frontend y contrato de API

Aplicación Vue 3 separada de Laravel. Desde la raíz, scripts/start-local.ps1 inicia PostgreSQL, Python, Laravel y Vite. Abrir http://127.0.0.1:5173/registro.

El registro consulta DNI, bloquea sus datos, carga región/provincia/distrito en cascada, pide ubicación actual y pasa a la captura biométrica. Crea la cuenta mediante /api/v1 y conserva el token durante la sesión de esta pestaña. El login valida DNI y contraseña contra Laravel. La comunidad mantiene datos de demostración, pero sus vistas requieren una sesión real y vigente.

npm run dev y npm run build preparan los recursos MediaPipe/ONNX desde ../public, verifican hashes y los incluyen en el build. Las copias generadas no se versionan. La cámara reutiliza los componentes derivados de Club Mejía; Laravel y Python vuelven a validar cada imagen.

Vite usa proxy para /api hacia http://127.0.0.1:8000. En un despliegue separado, configurar VITE_API_BASE_URL y CORS con los dominios reales. Cámara y ubicación necesitan HTTPS o localhost. Capacitor usa @capacitor/geolocation; no se han generado Android/iOS.


Aplicación vecinal hecha con Vue 3, TypeScript y Naive UI. El Mercadito permite
explorar anuncios de segunda mano por zona, guardar productos y publicar los propios.

## Levantar el servidor

Ejecuta estos comandos desde la raíz del repositorio con Node.js 22.12 o posterior.

```sh
cd frontend
npm ci
npm run dev -- --host 127.0.0.1 --port 5173 --strictPort
```

Abre [el Mercadito](http://127.0.0.1:5173/mercadito). El acceso requiere el DNI y la contraseña usados en el registro.
Para verificar el compilado de producción, ejecuta `npm run build` en `frontend`.

## Datos que maneja el frontend hoy

El registro consulta la API real de Laravel. Inicio, Mercadito, vecinos y planes
conservan datos de demostración y su estado local mientras se conectan sus servicios.

| Flujo | Datos que recoge o procesa el frontend | Comportamiento actual |
| --- | --- | --- |
| Inicio de sesión | `dni`, `password` | Laravel valida las credenciales, emite un token Sanctum y el router verifica su vigencia con `/profile`. |
| Registro | DNI, ticket de identidad, contacto, contraseña, UBIGEO, barrio, ubicación actual y foto | Consulta Scrall, bloquea los datos obtenidos y crea cuenta real mediante Laravel/Python. |
| Publicar producto | `title`, `price`, `category`, `condition`, `description`, `image`, `neighborhood` | Emite `NewListing`. `image` es una URL de datos Base64 y `neighborhood` es el nombre visible del barrio. |
| Explorar productos | Texto de búsqueda, zona `nearby` o `neighborhood`, vista general, propia o guardada | Filtra los datos en memoria. La búsqueda compara el título, la descripción y la categoría. |
| Administrar productos | ID del anuncio al guardar, marcar como vendido o eliminar | Persiste los anuncios propios y los IDs guardados en `localStorage`. |
| Publicaciones y comentarios | Texto de publicación de hasta 2.000 caracteres, ID de publicación y texto de comentario de hasta 500 | El muro, los comentarios, las reacciones y los guardados se actualizan en memoria. |
| Vecinos y planes | Barrio seleccionado, búsqueda, radio en metros, filtro `all`, `help` o `activities`, ID del plan al marcar interés | El mapa usa perfiles y posiciones de ejemplo. Los planes y el interés se manejan en memoria. |

Implementación actual en [AuthForm](src/components/auth/AuthForm.vue),
[ProductForm](src/components/marketplace/ProductForm.vue),
[useMarketplace](src/composables/useMarketplace.ts),
[useCommunity](src/composables/useCommunity.ts) y
[useNearbyNeighbors](src/composables/useNearbyNeighbors.ts).

## Contrato propuesto para el backend

Las rutas de Mercadito, muro, vecinos y planes de esta sección son propuestas
pendientes de implementación bajo `/api/v1`. Las
rutas de navegación como `/mercadito/guardados` pertenecen a Vue Router.

### Acceso y registro implementados

El contrato vigente de DNI, registro biométrico, perfil, ubicación, medios privados,
roles y etiquetas está en [la documentación del backend](../docs/backend-api.md).
La API usa tokens Bearer de Sanctum; el login del frontend usa las credenciales reales del registro.
El registro conserva el token en sessionStorage durante la sesión de esta pestaña.
Cada navegación protegida valida `/profile`; también se revisa la sesión al volver a la pestaña, cada 30 segundos y en su fecha de vencimiento. Un 401 elimina el token local y vuelve al login. Cerrar sesión revoca el token actual en Laravel antes de eliminarlo de sessionStorage; si falla la conexión, se muestra un error y se permite reintentar.

### Mercadito

| Operación propuesta | Envío del frontend | Respuesta esperada |
| --- | --- | --- |
| `GET /listings` | `q`, `scope=nearby\|neighborhood`, `view=all\|mine\|saved`, `neighborhoodId` y `cursor` opcional | `200` con `{ items, nextCursor, total }`. `nextCursor` es `null` al terminar. La vista propia incluye vendidos. |
| `GET /listings/{id}` | ID | `200` con el anuncio o `404` si ya no está disponible. |
| `POST /listings` | `multipart/form-data` con los campos de abajo | `201` con el anuncio listo para mostrar. |
| `PATCH /listings/{id}` | `{ sold: true }` | `200` con el anuncio actualizado. Requiere ser propietario. |
| `DELETE /listings/{id}` | ID | `204` al eliminar. Requiere ser propietario. |
| `PUT /listings/{id}/favorite` | `{ saved: true }` o `{ saved: false }` | `200` con `{ saved }`. Reintentar la misma petición deja el mismo estado. |

El formulario de publicación usa estos valores:

| Campo | Contrato propuesto |
| --- | --- |
| `title` | Texto de 3 a 100 caracteres. |
| `price` | Importe en PEN entre 0 y 999999, con hasta dos decimales. En el formulario multipart se serializa como texto decimal, por ejemplo `65.00`. |
| `category` | `Bicicletas`, `Hogar`, `Moda`, `Tecnología` u `Otros`. |
| `condition` | `Como nuevo`, `Buen estado` o `Con detalles`. |
| `description` | Texto de 10 a 2.000 caracteres. |
| `image` | Un archivo JPG, PNG o WebP de hasta 2 MiB. El frontend adaptará la foto de la demo a un archivo multipart. |
| `neighborhoodId` | ID del barrio donde se publica. Sustituye al nombre de barrio que usa el formulario local. |

Ejemplo de anuncio en una respuesta:

```json
{
  "id": "listing_123",
  "title": "Piezas de cerámica",
  "description": "Se venden juntas y se recogen en el barrio.",
  "price": 65,
  "currency": "PEN",
  "category": "Hogar",
  "condition": "Como nuevo",
  "image": "https://media.example.com/listings/listing_123.webp",
  "neighborhood": { "id": "san-blas", "name": "San Blas" },
  "seller": { "id": "user_456", "name": "Lucía", "avatar": null },
  "createdAt": "2026-11-04T15:00:00Z",
  "owned": false,
  "sold": false,
  "saved": true
}
```

El backend asigna `id`, vendedor y fecha, y calcula `owned` y `saved` para la
sesión activa. El frontend transforma el barrio y el vendedor en los valores
que usa la tarjeta y formatea el precio para la vista.

### Muro, vecinos y planes

| Operación propuesta | Envío del frontend | Respuesta esperada |
| --- | --- | --- |
| `GET /posts` y `POST /posts` | Al listar, `neighborhoodId` y cursor opcional. Al publicar, `{ text, neighborhoodId }`. | Muro paginado o publicación creada. Cada publicación incluye autor, fecha, párrafos, estado de bienvenida, reacciones, guardado y comentarios visibles. |
| `POST /posts/{id}/comments` | `{ text }` | Comentario creado con ID, autor, fecha y texto. |
| `PUT /posts/{id}/reaction` y `PUT /posts/{id}/saved` | `{ welcomed: boolean }` o `{ saved: boolean }` | Estado actual y total de reacciones cuando corresponda. |
| `GET /neighbors` | `neighborhoodId`, `q`, `radiusMeters` y `filter` | Vecinos ordenados por cercanía con ID, nombre, avatar, biografía, habilidades, intereses, disponibilidad y banderas para ayuda y actividades. Para el mapa, coordenadas aproximadas `{ lat, lng }` y distancia en metros. |
| `GET /activities` y `PUT /activities/{id}/interest` | Barrio al listar y `{ interested: boolean }` al cambiar interés | Planes con ID, título, categoría, inicio con zona horaria, lugar, descripción, imagen opcional e interés de la sesión. |

Para errores, se propone un JSON con `code`, `message` y un objeto opcional
`fieldErrors` por campo. Usar `401` para sesión faltante o vencida, `403` para
permisos insuficientes, `404` para recursos ausentes y `422` para validación.
`409` puede indicar un correo ya registrado y `413` un archivo demasiado grande.
