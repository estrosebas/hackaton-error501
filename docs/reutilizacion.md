# Componentes reutilizados

## Feed comunitario

Se inspeccionaron `FEED-SOCIAL.md`, el controlador y los modelos del feed en `C:\laragonzo\www\clubmejia-mesas`. Se adaptaron sus patrones de paginación por cursor, comentarios, reacciones y guardados; la implementación usa el diseño Vue/Naive UI de masi y tablas propias en PostgreSQL. No se copiaron datos, fotos, credenciales ni permisos del club.

La primera versión admite texto con hasta cinco hashtags y seis categorías: pedidos de ayuda, ofrecimientos, colaboraciones, iniciativas, actividades y alertas. El distrito registrado delimita la comunidad. La autoría y el distrito se obtienen de la sesión. El propietario puede marcar una publicación como atendida, reabrirla o retirarla. Las fotos y el editor enriquecido del origen quedaron fuera de esa adaptación. La campana usa las publicaciones reales de actividad, pedido de ayuda y alerta del distrito, y muestra la presencia de quienes la comparten.

Origen local: `C:\laragonzo\www\clubmejia`, inspeccionado para esta adaptación. El proyecto de origen se mantuvo sin cambios.

## Navegador

- `useCapturaRostro.js`: ciclo de cámara, cambio de dispositivo, liberación de tracks, MediaPipe con fallback GPU/CPU y autocaptura estable.
- `calidadCaptura.js`, `accesoriosRostro.js` y `detectorAccesorios.js`: encuadre, selección de la mejor imagen y detección de accesorios.
- Recursos `public/mediapipe` y `public/face-accessories`, con sus avisos de licencia. `face-accessories.json` conserva las huellas SHA-256.
- La sección fotográfica de `/actualizar-foto` se extrajo como `FaceCapture.vue`, con HTML accesible y estilos propios, sin arrastrar pantallas de socios, permisos ni dependencias Vuetify del club.
- Se conservaron pruebas existentes del ciclo de cámara y sus reglas de captura.
- Catálogo UBIGEO: snapshot comunitario CC0 de RitchieRD/ubigeos-peru-data, revisión 23cd3666b19f32bccdf36d90243c2a4462d6202c, con procedencia dentro del JSON. No se presenta como padrón oficial actualizado.

## Python

- Pesos YuNet/SFace originales de `modeloJose/models/sface_yunet` y clases de detección/calidad.
- Solo se conserva la función `extract_enrollment_embedding` de enrollment; se retiran importaciones y operaciones de galería, padrón y equipos Hikvision.
- `photo_check_service.py` conserva límites, autenticación, concurrencia y comprobación de calidad. Se adapta la configuración y se añade `/extract` privado para guardar la plantilla en PostgreSQL mediante Laravel.
- La huella de modelo depende de ambos pesos y de la versión de OpenCV. Los hashes de los archivos copiados están en `modeloJose/models/manifest.json`.

## Laravel

`FacePhotoCheck` adapta la llamada HTTP del servicio original. El nuevo controlador registra vecinos y actualiza exclusivamente la foto del usuario autenticado. No usa identificaciones, pases de actualización, aprobación administrativa ni tablas del club. La cuenta y el perfil facial se crean dentro de una transacción.
