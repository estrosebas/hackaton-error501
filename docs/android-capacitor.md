# Android: Capacitor, ubicación y mapa local

## Base instalada

`frontend/android` es el proyecto Android nativo (app ID `com.error501.vecino`). Capacitor 8 y plugins de Android, App, Network, Geolocation, Background Geolocation (community), Filesystem, Preferences, Local Notifications, Keyboard, Splash Screen y Status Bar están instalados. El mapa conserva Leaflet y el diseño oscuro.

No se usan servicios de ubicación del navegador para prometer funcionamiento en segundo plano: Android utiliza `@capacitor-community/background-geolocation`, una notificación persistente y HTTP nativo. La pantalla ofrece un botón para detener el seguimiento. No se inicia automáticamente al abrir la app; comienza tras activar ubicación. Logout/401 lo detiene y limpia las copias privadas del mapa. Cambiar de pantalla no lo detiene.

Cada captura válida se comprueba contra los eventos inscritos en horario, como máximo una vez cada 30 segundos. Se rechazan posiciones simuladas, antiguas o con precisión mayor a 100 m. La estimación de distancia del cliente solo filtra candidatos; Laravel/PostGIS sigue decidiendo la asistencia. No se acreditan puntos offline ni se reenvían capturas antiguas como si fueran nuevas.

El servicio está preparado para background con `android.useLegacyBridge` y CapacitorHttp. Android puede detener la app por cierre forzado, restricciones de batería o permisos; todavía hace falta verificar el comportamiento en un dispositivo físico. No se implementó reinicio automático tras muerte del proceso ni arranque del teléfono.

## Compilar e instalar

Requisitos: Android Studio, Android SDK 36, JDK compatible con Gradle/Capacitor (usar el JBR de Android Studio, preferentemente Java 21), Node/npm.

1. Copiar `frontend/.env.android.example` a `frontend/.env.android.local`.
2. Colocar `VITE_API_BASE_URL=https://TU_BACKEND/api/v1` con el backend real. `localhost` del teléfono no es el PC. No incluir credenciales privadas en variables VITE.
3. Desde `frontend`:

```powershell
npm ci
npm run android:sync
npm run android:open
```

Para ejecutar en un emulador o dispositivo configurado: `npm run android:run`. Para compilar el APK desde CLI tras sincronizar: `android/gradlew.bat -p android assembleDebug`. El resultado queda en `android/app/build/outputs/apk/debug/app-debug.apk`.

`build:android` valida que la API sea HTTPS y no sea el dominio de ejemplo antes de compilar. `npm run build` continúa siendo el build web normal con proxy Vite. Los assets web se incluyen en el APK; no se configura `server.url` para depender de Vite en Android. Los permisos de cámara, ubicación aproximada/precisa, background y notificaciones están declarados. Los plugins piden sus permisos en ejecución.

## Copia de eventos y teselas

- `src/native/offlineMap.ts`: copia de eventos y catálogo por cuenta/distrito, caducidad 24 horas. No guarda biometría ni JWT en Preferences. Se descartan eventos terminados al recuperar la copia. Logout purga las copias.
- `useMapEvents`: recupera esa copia si no alcanza al servidor, muestra aviso de datos antiguos, bloquea inscripciones/check-in offline y vuelve a consultar al reconectar.
- `src/native/tileLayer.ts`: conector de caché nativa para teselas raster de un proveedor autorizado. Guarda solo teselas visitadas en Filesystem Cache, con un máximo de 256, separadas por proveedor; permite representar la zona ya vista offline. No es una descarga completa de ciudad ni garantiza zonas/zooms que no se visitaron. Android puede limpiar su directorio Cache.
- `src/native/runtime.ts`: eventos de red y retorno a primer plano.

Para habilitar teselas locales se necesita configurar `VITE_MAP_TILE_URL`, `VITE_MAP_ATTRIBUTION` y `VITE_MAP_OFFLINE_TILES=true` con un proveedor que permita ese uso. **El proveedor actual `tile.openstreetmap.org` no se descarga para uso offline**, ni siquiera si se activa la bandera: su [política](https://operations.osmfoundation.org/policies/tiles/) lo prohíbe. La caché de eventos funciona independientemente; sin un proveedor offline configurado puede haber marcadores sobre un fondo sin calles cuando se corta Internet. Las fotos de eventos no tienen aún caché offline propia.

La autenticación exige una validación inicial contra Laravel. Si se pierde la red con una sesión ya validada en la misma ejecución, permite consultar la copia hasta la expiración que informó el servidor; no permite escrituras ni verificar revocaciones nuevas mientras no haya Internet. Al vencer se limpia la sesión y se detiene el servicio. **No se implementó inicio de sesión offline ni arranque en frío sin validación**. Esto debe diseñarse por separado si se quiere abrir la app desde cero sin red.

## Investigación de Vela

[Vela Maps](https://velamaps.org/) sí tiene mapas offline. Su [documentación](https://pimpinpumpkin.github.io/Vela/docs/book/offline/) describe MapLibre, archivos PMTiles para mapa base, paquetes de fuentes y descargas por zona. Es una aplicación Android nativa; no es un servidor de teselas raster intercambiable ni un plugin de Capacitor. Su código está bajo [GPLv3](https://github.com/PimpinPumpkin/Vela/blob/main/LICENSE).

No se ha copiado código ni se han descargado paquetes regionales de Vela. La ruta para mapas de zonas completas es un conector MapLibre/PMTiles con proveedor o paquetes licenciados, y descarga nativa controlada de región, estilo, fuentes e iconos. Ese renderer vectorial y la importación/descarga de PMTiles **quedan pendientes**; no basta poner una URL PMTiles en Leaflet raster. La caché raster actual sirve para empezar y no se presenta como esa implementación completa.

## Validación en dispositivo pendiente

Probar permisos denegados y otorgados (incluido “permitir siempre”), Android 13+ notificaciones, pantalla bloqueada durante más de cinco minutos, detener seguimiento, logout, pérdida de red, reconexión, cambio de cuenta/distrito y reinicio del proceso. Confirmar que nunca se duplican puntos ni se reenvían pruebas vencidas. Revisar también consumo de batería antes de ampliar la frecuencia de capturas.

Referencia del plugin: [community/background-geolocation](https://github.com/capacitor-community/background-geolocation).
