# Contrato de registro y fotografía

## Navegador a Laravel

Mismo origen, cookies de sesión y cabecera `Accept: application/json`. Las operaciones de escritura usan `X-XSRF-TOKEN`, obtenido de la cookie Laravel `XSRF-TOKEN`; Axios lo envía automáticamente. No se desactiva CSRF. El token de Python nunca se entrega al navegador.

| Método | Ruta | Acceso | Respuesta |
| --- | --- | --- | --- |
| POST | /api/auth/register | Público, 6/min/IP | 201, usuario y sesión iniciada |
| POST | /api/auth/login | Público, 6/min/IP | 200, usuario y sesión iniciada |
| POST | /api/auth/logout | Sesión | 204 |
| GET | /api/auth/user | Sesión | 200, usuario propio y face_enrolled |
| POST | /api/face/check | Público, 12/min/IP | 200, accepted/model/liveness sin plantilla |
| POST | /api/profile/photo | Sesión, 6/min | 200, confirmación y fecha |
| GET | /api/profile/photo | Sesión | Imagen privada propia, no-store |

Registro: `dni` de 8 dígitos y `email` únicos, `name` (120 caracteres), `password` de mínimo 8 caracteres y `password_confirmation`, `ubigeo` de 6 dígitos, `neighborhood` (120), `biometric_consent: true` y `foto`. `phone` y el par latitude/longitude son opcionales. UBIGEO valida formato, no residencia.

`foto` es una data URL base64 JPEG, PNG o WebP. Máximo 4 MiB decodificados y 16 millones de píxeles. PHP inspecciona imagen/MIME y Python verifica nuevamente peso, decodificación, rostro único, calidad y plantilla. La confirmación previa de `/face/check` es orientativa: el registro y la actualización vuelven a ejecutar la validación en el servidor.

- 401: se necesita sesión.
- 419: sesión/CSRF vencido; recargar antes de reintentar.
- 422: campos inválidos o foto rechazada; `errors.foto` contiene la corrección sugerida.
- 429: límite de solicitudes de Laravel.
- 503: servicio facial inaccesible, ocupado o contrato de respuesta inválido. No se crea la cuenta ni se reemplaza la foto.

La plantilla y la ruta de almacenamiento no se serializan en las respuestas públicas. Se guarda el consentimiento con fecha y se limita el acceso a la fotografía al propietario.

## Laravel a Python

Servicio local en `127.0.0.1:8782`, `Authorization: Bearer <FACE_PHOTO_CHECK_TOKEN>`. Todas las rutas requieren el token. Un único proceso carga los pesos; solo se permite una inferencia a la vez.

| Método | Ruta | Contrato |
| --- | --- | --- |
| GET | /health | Estado, modelo, huella y política de calidad |
| POST | /check | Bytes de imagen; accepted, reason, message, model, model_fingerprint, quality_policy, elapsed_ms, liveness |
| POST | /extract | Mismo contrato; si acepta, agrega embedding normalizado de 128 números |

Content-Type admitidos: image/jpeg, image/png, image/webp y application/octet-stream. `/check` nunca devuelve embedding. `/extract` es exclusivamente para Laravel: la plantilla se cifra en la base y Python no mantiene galería ni almacena imágenes.

HTTP 200 con `accepted: false` indica rechazo de calidad. 401 indica token incorrecto; 413 exceso de tamaño; 415 tipo no admitido; 422 cuerpo vacío; 429 inferencia ocupada, con Retry-After. Laravel transforma indisponibilidad o saturación en 503 y no omite la validación.

La política conserva la extracción original YuNet/SFace. `liveness` permanece `not_evaluated`. No existe comparación con RENIEC ni deduplicación 1:N en esta primera integración.
