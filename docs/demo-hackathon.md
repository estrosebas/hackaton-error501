# Demostración pública de la hackathon

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
