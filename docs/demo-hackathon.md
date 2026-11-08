# Demostración pública de la hackathon

`php artisan db:seed --class=HackathonPolishSeeder --force` aplica el refinamiento visual: 73 posts incluyendo un simulacro concluido en rojo, likes y comentarios sintéticos de cantidades diferentes, horarios y distancias irregulares y dos actividades con horarios y fotos distintos. Conserva likes/comentarios reales y no cambia horario o ubicación de eventos que ya tienen inscritos. Los vistos buenos de ejemplo se generan exclusivamente entre los perfiles sintéticos, sin atribuir recomendaciones ni puntos a cuentas reales. La ejecución es repetible; no cambia `APP_ENV`.

Para mostrar cambios al ampliar el radio, ejecutar además `php artisan db:seed --class=HackathonMapRangeSeeder --force`. Conserva los 12 eventos base y añade otros 24, a 400, 650, 900, 1250, 1800 y 2600 metros. Respecto a la ubicación guardada de la cuenta ancla, habrá 12 eventos dentro de 300 m, 24 dentro de 1 km y 36 al elegir Todo mi distrito. Una ubicación GPS distinta puede producir otros recuentos. La ampliación también es idempotente y respeta eventos ya modificados.

`HackathonDemoSeeder` es una siembra manual independiente de los seeders locales. Solo funciona con `DEMO_SHOWCASE_ENABLED=true` y requiere `DEMO_MAP_ANCHOR_USER_ID` de una cuenta real con UBIGEO y coordenadas guardadas. No cambiar `APP_ENV` a local para usarla.

```bash
php artisan config:clear
php artisan db:seed --class=HackathonDemoSeeder --force
php artisan optimize
```

Crea 20 perfiles sintéticos, 72 publicaciones, 144 comentarios iniciales y 12 eventos (tres por categoría) en el distrito de esa cuenta. Los eventos se colocan a 80–245 metros de sus coordenadas guardadas. Los horarios se fijan al sembrar; una segunda ejecución conserva los eventos existentes, sus inscripciones, cancelaciones y comentarios reales. No reinicia los horarios ni duplica registros.

Las cuentas sintéticas no tienen DNI ni credenciales de acceso conocidas. No se crean embeddings, inscripciones, puntos ni vistos buenos ficticios. Cada registro lleva una `demo_key` con prefijo `showcase-{ubigeo}-`; el feed muestra una marca de demostración y las emergencias del mapa son simulacros explícitos.

Se verifican los hashes de los retratos sintéticos y las fotos antes de sembrar. Se usan los recursos de `database/data/demo/events/`, o los de `database/data/demo/showcase-photos/` cuando existe su manifiesto. Las fotografías compartidas se recodifican como WebP y se sirven por los endpoints del feed y del mapa, con las restricciones existentes. Los retratos sintéticos públicos usan una URL absoluta del backend para funcionar con dominios separados; esta exposición requiere la activación explícita de la demo.

Inicio y Planes del barrio consultan los eventos reales de `/api/v1/map-events`. Las tarjetas abren `/vecinos?event={id}` y seleccionan el evento en el mapa. No se duplican eventos como publicaciones ni se cambian sus reglas de asistencia.

En la VPS, la copia previa está en `/root/masi-deploy-backup/before-showcase.dump`. El catálogo UBIGEO, las cuentas reales y sus datos se conservan. La demostración pública actual está centrada en Socabaya; los usuarios de otros distritos mantienen su aislamiento habitual.
