# masi · Frontend y contrato de API

Aplicación vecinal hecha con Vue 3, TypeScript y Naive UI. El Mercadito permite
explorar anuncios de segunda mano por zona, guardar productos y publicar los propios.

## Levantar el servidor

Ejecuta estos comandos desde la raíz del repositorio con Node.js 22.12 o posterior.

```sh
cd frontend
npm ci
npm run dev -- --host 127.0.0.1 --port 5173 --strictPort
```

Abre [el Mercadito](http://127.0.0.1:5173/mercadito). El acceso de demo acepta un
correo con formato válido y una contraseña de ejemplo de al menos ocho caracteres.
Para verificar el compilado de producción, ejecuta `npm run build` en `frontend`.

## Datos que maneja el frontend hoy

El frontend funciona con datos de demo. Los formularios emiten eventos a componentes
Vue y la app todavía no envía solicitudes a una API de masi.

| Flujo | Datos que recoge o procesa el frontend | Comportamiento actual |
| --- | --- | --- |
| Inicio de sesión | `email`, `password` | Valida en el navegador. El evento `submit` no incluye credenciales y activa una sesión en memoria. |
| Registro | `name`, `email`, `password`, `confirmation` | Valida la coincidencia de contraseñas y abre la sesión de demo. |
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

Las rutas de esta sección son una propuesta de integración bajo `/api/v1`. Las
rutas de navegación como `/mercadito/guardados` pertenecen a Vue Router.

### Acceso y barrios

| Operación propuesta | Envío del frontend | Respuesta esperada |
| --- | --- | --- |
| `POST /auth/login` | `{ email, password }` | `200` y perfil de la sesión. |
| `POST /auth/register` | `{ name, email, password }` | `201` y perfil de la sesión. `confirmation` se usa en el formulario. |
| `GET /auth/session` | Sesión actual | `200` con `{ id, name, avatar, neighborhood }`, o `401` si expiró o falta. |
| `POST /auth/logout` | Sesión actual | `204` tras invalidar la sesión. |
| `GET /neighborhoods` | Sesión actual | Barrios disponibles como `{ id, name }`. El frontend muestra el nombre y envía el ID. |

Los ejemplos asumen una cookie de sesión `HttpOnly`. El perfil incluye
`neighborhood: { id, name }`. El backend valida la sesión y la pertenencia al
barrio en cada operación protegida.

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
