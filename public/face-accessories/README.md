# Deteccion de accesorios en el navegador

Todos los archivos de esta carpeta deben viajar en Git como archivos reales,
no punteros LFS pendientes de descargar. No hay descarga de pesos durante el
despliegue o arranque de PHP/Python. El navegador si debe recibirlos del propio
sitio la primera vez (aproximadamente 28 MiB adicionales), mas los recursos
MediaPipe existentes. La cache depende de las cabeceras del servidor.

## Procedencia

- glasses.onnx y mask.onnx: https://github.com/moraxh/FrameFind
  commit 3c69676cbcd44a47ab083b541b56c050dbc0b1b5, modelos v1 (MIT).
  El adaptador sigue sus recortes de ojos/rostro, RGB/CHW, normalizacion
  ImageNet y orden with_mask / without_mask / incorrect_mask. Se conserva
  el aviso de licencia en licenses/FrameFind-MIT.txt.
- face_landmarker.task: Google MediaPipe, float16, version 1:
  https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task
  Licencia Apache 2.0 en licenses/MediaPipe-Apache-2.0.txt.
- ort-1.25.1: binarios originales de onnxruntime-web 1.25.1 (MIT),
  copiados de su paquete npm; avisos y licencias en licenses/.

resources/js/data/face-accessories.json conserva SHA-256 de pesos y runtime. npm run build los verifica
y comprueba que la version npm coincide con los binarios incluidos. No
reemplazar pesos o WASM sin actualizar manifiesto y verificar su procedencia.

## Alcance

Esto orienta y bloquea la autocaptura ante lentes/mascarilla o duda. No es
prueba de vida, no autentica identidades y no garantiza detectar todo tipo
de accesorio. No detecta por ejemplo gorras. Los umbrales iniciales requieren
validacion con camaras y telefonos reales del club antes del uso definitivo.
Se exigen varias lecturas sin accesorios para permitir la captura; no se usa
un recorte centrado como reemplazo si faltan puntos faciales.

Solo afecta /actualizar-foto. Python conserva su validacion final y YuNet +
SFace no cambian de pesos ni de plantilla. No hace falta regenerar la galeria.
Gafas, reflejos y especialmente mascarillas pueden reducir la similitud al
identificarse en la puerta: detectar un rostro no garantiza reconocerlo.

Si faltan recursos o falla la inferencia se ofrece reintentar y no se captura
silenciosamente. No se envian cuadros a FrameFind, Google ni otro CDN.

## Despliegue en Nginx / Hestia

El runtime .mjs debe servirse como application/javascript, no como
application/octet-stream: el navegador rechaza los modulos con MIME binario.
En /etc/nginx/mime.types la entrada correspondiente debe incluir js y mjs:

```nginx
application/javascript js mjs;
application/wasm wasm;
```

Respaldar la configuracion, comprobar nginx -t y recargar Nginx. Verificar
Content-Type con curl -I sobre
/face-accessories/ort-1.25.1/ort-wasm-simd-threaded.mjs. Si una actualizacion
del servidor reemplaza mime.types, comprobar nuevamente estas extensiones.
Corregir solo Apache no basta cuando Nginx entrega los archivos estaticos.

Las URLs de pesos, .mjs y .wasm incluyen su SHA-256 para no reutilizar
respuestas antiguas. Cada build tambien publica /build/version.json:
la aplicacion detecta otra compilacion y ofrece actualizar sin borrar
automaticamente un formulario abierto. El HTML se entrega sin cache.
En Android/Chrome, el detector facial prueba CPU si falla la GPU. Un error
de carga muestra su etapa y permite desplegar el detalle original, sin
necesidad de abrir la consola del telefono. No se omiten las validaciones.
