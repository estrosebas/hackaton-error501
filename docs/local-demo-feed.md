# Feed sintético de desarrollo

`iniciar-masi.bat` y `scripts/start-local.ps1` ejecutan `php artisan demo:seed` antes de iniciar las aplicaciones. Solo cuando Laravel tiene **APP_ENV=local**, se aplican las migraciones pendientes y se carga el seeder. No hay cambios de base de datos ni cargas automáticas al iniciar en producción, staging o testing.

La carga crea 20 vecinos ficticios, 12 publicaciones, 24 comentarios y 36 reacciones. Los nombres, historias y fotos son sintéticos. Los usuarios tienen contraseñas aleatorias no compartidas, DNI nulo y ninguna plantilla biométrica; no se simula una identidad validada. Inicia sesión con tu cuenta registrada para probar el feed.

El distrito se toma de `DEMO_FEED_UBIGEO`, del primer usuario real con distrito registrado o, si aún no existe, de Lima (`150101`). Las categorías y los hashtags son etiquetas Spatie de tipos distintos. Los identificadores `demo_key` permiten repetir el seeder sin duplicar conversaciones ni borrar comentarios, reacciones o publicaciones de usuarios reales. Una publicación retirada no se restaura al reiniciar.

Configuración opcional en `.env`:

```dotenv
APP_ENV=local
DEMO_FEED_ENABLED=true
# DEMO_FEED_UBIGEO=080101
```

Con `DEMO_FEED_ENABLED=false` no se insertan datos nuevos ni se sirven retratos demo. Cambiar esta opción no borra registros existentes. Después de cambiar configuración con caché, ejecutar `php artisan config:clear`.

Los veinte JPEG se descargaron del archivo `shard_10_of_86.zip` del espejo [pravsels/SFHQ_256](https://huggingface.co/datasets/pravsels/SFHQ_256), del proyecto [SFHQ de SelfishGene](https://github.com/SelfishGene/SFHQ-dataset). Son imágenes generadas fotorealistas. Se conservan sin editar en `database/data/demo/avatars`, con archivo de procedencia y hashes SHA-256. `python scripts/download-demo-avatars.py` permite reproducir la descarga con validación del archivo completo. La app y los tests no descargan imágenes ni consumen Scrall al sembrar.

`GET /api/v1/demo/avatars/{01..20}.jpg` sirve únicamente estos archivos en entorno local. Nunca accede a medios privados ni fotos de registro de cuentas reales.
