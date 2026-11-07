# Contrato del servicio facial

El contrato vigente de registro con DNI, ubicación y fotografía está en [backend-api.md](backend-api.md). El cliente futuro usará `/api/v1` con tokens Bearer. Las rutas previas `/api/auth/*` conservan sesión y CSRF, pero el registro ahora exige igualmente un ticket de identidad y ubicación reciente; no se ha conectado ninguna pantalla al nuevo flujo.

## Imagen enviada por el cliente

`foto` es una data URL JPEG, PNG o WebP, de máximo 4 MiB decodificados y 16 millones de píxeles. Laravel inspecciona el archivo y Python verifica nuevamente peso, decodificación, rostro único, calidad y plantilla. La confirmación previa de `/face/check` es orientativa: el registro y la actualización ejecutan nuevamente el validador final.

La plantilla facial y la ruta privada de almacenamiento nunca se serializan al cliente. Las fotografías biométricas se conservan en `storage/app/private/faces`; la plantilla se cifra en PostgreSQL con APP_KEY. El propietario autenticado puede consultar o reemplazar su foto. Los adjuntos generales de Spatie tienen endpoints independientes y permanecen privados.

## Laravel a Python

Servicio en `127.0.0.1:8782`. Todas las rutas exigen `Authorization: Bearer <FACE_PHOTO_CHECK_TOKEN>`; esa credencial nunca se entrega al cliente. Un único proceso carga los pesos y permite una inferencia simultánea.

| Método | Ruta | Respuesta |
| --- | --- | --- |
| GET | /health | Estado, modelo, huella y política de calidad |
| POST | /check | accepted, reason, message, model, model_fingerprint, quality_policy, elapsed_ms, liveness |
| POST | /extract | Mismo contrato; si acepta agrega embedding normalizado de 128 números |

Enviar bytes con Content-Type image/jpeg, image/png, image/webp o application/octet-stream. `/check` nunca devuelve la plantilla. `/extract` es exclusivamente para Laravel; Python no mantiene galería ni guarda imágenes.

HTTP 200 con accepted=false indica rechazo de calidad. 401 indica token incorrecto, 413 exceso de tamaño, 415 tipo no admitido, 422 cuerpo vacío y 429 inferencia ocupada con Retry-After. Laravel transforma indisponibilidad, saturación o contrato inválido en 503 y no omite silenciosamente la validación.

La política conserva la extracción YuNet/SFace original. `liveness` permanece `not_evaluated`. No existe comparación del rostro con RENIEC ni deduplicación facial 1:N en esta integración. Scrall proporciona datos básicos de DNI, lo que no demuestra que el rostro enviado sea del titular.
