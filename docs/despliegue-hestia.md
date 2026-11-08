# Despliegue web en Hestia

Al compilar, establecer `MASI_BUILD_ID` al SHA de la versión desplegada. Vite publica `build/version.json`; el perfil muestra el identificador y la app ofrece actualizar cuando detecta otra versión, sin recargar automáticamente formularios abiertos. Servir ese JSON y `index.html` sin caché. El frontend de la VPS es una copia desplegada y no necesita `.git` propio.

Frontend: `https://masi.nooxtools.me`. API: `https://api.masi.nooxtools.me/api/v1`.

## Directorios

- Repositorio Laravel: `/home/noox/web/api.masi.nooxtools.me/public_html`.
- Raíz pública del backend: directorio `public/` del repositorio.
- Frontend separado: `/home/noox/web/masi.nooxtools.me/public_html/frontend`.
- Raíz pública del frontend: `frontend/dist/`.

En Hestia, configurar la raíz personalizada de cada dominio. Para Laravel, permitir también a PHP acceder al directorio del proyecto, no solo a `public/`. La CLI de Hestia lo permite con:

```bash
v-change-web-domain-docroot noox api.masi.nooxtools.me api.masi.nooxtools.me /public/ yes
```

El frontend incluye `.htaccess` para Apache: todas las rutas de Vue que no sean archivos deben devolver `index.html`. En instalaciones de Nginx sin Apache se necesita una plantilla con `try_files $uri $uri/ /index.html`.

## Dependencias y entorno

PHP 8.3 CLI y FPM con `pdo_pgsql`, Composer, PostgreSQL con PostGIS, Python 3.12 con venv y Node compatible con Vite 8. La VPS usa PostgreSQL 18 en `127.0.0.1:5432`; la base y el usuario de esta aplicación son `noox_masi`. Las credenciales se guardan exclusivamente en el `.env` remoto.

En el backend: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.masi.nooxtools.me`, `FRONTEND_URL=https://masi.nooxtools.me` y `CORS_ALLOWED_ORIGINS=https://masi.nooxtools.me`. Conservar `APP_KEY` para poder descifrar los datos existentes. Scrall, SMTP y el token biométrico permanecen privados.

Para instalar/actualizar como usuario `noox`, desde el repositorio del backend:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

Activar PostGIS con una cuenta administradora antes de las migraciones. En una instalación nueva ejecutar `UbigeoSeeder` y `AccessSeeder` con `--force`; no se cargan datos de demostración en producción.

## Compilar el frontend separado

Crear `frontend/.env.production.local` con:

```dotenv
VITE_API_BASE_URL=https://api.masi.nooxtools.me/api/v1
```

Desde la carpeta del frontend:

```bash
npm ci --no-audit --no-fund
FACE_ASSETS_ROOT=/home/noox/web/api.masi.nooxtools.me/public_html/public npm run build
```

`FACE_ASSETS_ROOT` indica dónde están los recursos faciales originales; se siguen verificando sus hashes antes de copiarlos. No colocar credenciales privadas en variables Vite.

## Procesos

- `masi-biometrico.service`: ejecuta `modeloBiometrico/.venv/bin/python -m photo_check_service` desde `modeloBiometrico/src`, como `noox`, con el `.env` privado del modelo. Escucha únicamente en `127.0.0.1:8782` y requiere Bearer.
- `/etc/cron.d/masi-scheduler`: ejecuta `php8.3 artisan schedule:run` cada minuto como `noox`.

Verificar con `systemctl status masi-biometrico`, `journalctl -u masi-biometrico`, `php artisan schedule:list` y `https://api.masi.nooxtools.me/up`. No usar `artisan serve` ni Vite dev en producción.

Ambos dominios necesitan HTTPS. Si el proxy de Cloudflare rechaza TLS para `api.masi.nooxtools.me`, usar Solo DNS hasta disponer de un certificado de borde que cubra ese nombre; el certificado del origen en Hestia es independiente.

El respaldo inicial de esta separación está fuera de la raíz pública, en `/root/masi-deploy-backup/`. No se deben publicar sus archivos ni los `.env`.
