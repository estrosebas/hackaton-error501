# Levantar masi en otra computadora

Guía para el equipo y sus agentes. El procedimiento automático es para **Windows x64 con PowerShell**. No hace falta instalar PostgreSQL, PostGIS, Docker ni servicios de Windows: los scripts preparan una instalación portable dentro del proyecto.

## 1. Requisitos

Instalar y dejar disponibles en `PATH`:

- Git y acceso al repositorio del equipo.
- PHP 8.3 x64 y Composer. Puede usarse PHP de Laragon; no hace falta arrancar MySQL ni Apache.
- Node.js 22.12 o posterior compatible con Vite y npm. Node 24 sirve.
- `uv`, el gestor de Python. El script puede descargar Python 3.13 al crear el entorno.
- Conexión a Internet para la primera instalación.

Comprobar desde la terminal que se usará para el proyecto:

```powershell
php -v
php --ini
composer --version
node --version
npm --version
uv --version
git --version
```

PHP necesita las extensiones habituales de Laravel y `gd` para las imágenes. El instalador habilita `pdo_pgsql` y `pgsql` en el `php.ini` activo, guardando una copia previa en `.local/php.ini.before-pgsql`. Los DLL de esas extensiones deben estar disponibles en la distribución de PHP.

## 2. Primera instalación

Clonar o actualizar la rama acordada con el equipo, que debe incluir esta guía y `scripts/setup-postgis.ps1`. No mezclar ramas automáticamente ni descartar cambios locales.

```powershell
git clone git@github.com:estrosebas/hackaton-error501.git
cd hackaton-error501
```

Si el repositorio ya está clonado, entrar a su carpeta en vez de clonarlo otra vez. Ejecutar **desde la raíz**, donde está `artisan`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-local.ps1
```

**En una instalación nueva, no copiar `.env.example` a `.env` antes de este comando.** El script crea `.env` y genera las contraseñas locales y el token de Python. Un `.env` creado a mano con claves vacías impide preparar correctamente el rol de PostgreSQL.

El instalador realiza estas tareas:

1. Descarga PostgreSQL 17.11 portable en `.local/postgres` y crea el clúster en `.local/pgdata`.
2. Genera credenciales locales, crea el rol `vecino` y las bases `vecino` y `vecino_test`.
3. Instala PostGIS 3.6.2 para PostgreSQL 17, verifica el SHA256 del paquete y activa la extensión en ambas bases.
4. Instala dependencias PHP y genera `APP_KEY` únicamente si está vacía.
5. Prepara `.env.testing` y `modeloBiometrico/.env` con el token del servicio facial.
6. Ejecuta migraciones y seeders de catálogos, permisos y datos de demostración habilitados en local.
7. Crea el entorno Python 3.13 e instala sus dependencias.
8. Instala y compila los recursos del backend y la app independiente en `frontend/`.

La primera ejecución puede tardar por las descargas. Si falla, corregir el error y repetir el comando; no borrar las bases ni regenerar claves para intentar arreglarlo.

## 3. Levantar todo

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

Esto inicia PostgreSQL, Laravel, el servicio facial, el frontend y el scheduler. Se ejecutan en segundo plano y sin instalar servicios de Windows.

Para ver terminales separadas, abrir `iniciar-masi.bat` o ejecutar:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1 -Terminals
```

| Componente | Dirección o función |
| --- | --- |
| App Vue | http://127.0.0.1:5173/iniciar-sesion |
| Registro | http://127.0.0.1:5173/registro |
| Mapa comunitario | http://127.0.0.1:5173/vecinos |
| Laravel / API | http://127.0.0.1:8000 — API bajo `/api/v1` |
| Servicio biométrico privado | 127.0.0.1:8782 |
| PostgreSQL + PostGIS | 127.0.0.1:5433 |
| Base de desarrollo | `vecino` |
| Base de pruebas | `vecino_test` |
| Scheduler | `php artisan schedule:work`, sin puerto propio |

Abrir la **app del puerto 5173**. El proxy de Vite reenvía la API a Laravel. Los mapas usan calles de OpenStreetMap y necesitan Internet para cargar las teselas.

El frontend solicita permisos de cámara y ubicación. Probar desde `127.0.0.1` o `localhost`; para un teléfono por red se necesita HTTPS y configurar ese entorno. No basta cambiar la URL a la IP del PC.

## 4. Configuraciones privadas

El arranque técnico no exige compartir el `.env` de otra persona.

| Variable | Cuándo se necesita |
| --- | --- |
| `SCRALL_BASIC_TOKEN` | Consulta real de DNI y registro completo. Colocar solamente el valor posterior a `Basic`, sin ese prefijo |
| `MAIL_*` | Envío real de recuperación de contraseña mediante el relay del equipo |
| `FACE_PHOTO_CHECK_TOKEN` | Se genera localmente y debe coincidir con `modeloBiometrico/.env`; no enviarlo al navegador |
| `APP_KEY` | Se genera en la primera instalación y debe conservarse mientras se conserve esa base y sus datos cifrados |

Por defecto `MAIL_MAILER=log`: no envía correos externos. Para el relay, completar localmente `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp`, `MAIL_REQUIRE_TLS=true`, host, puerto, usuario, contraseña y remitente. Consultar [recuperación de contraseña](docs/password-recovery.md).

No pegar credenciales reales en esta guía, en archivos de ejemplo ni en commits. No copiar `.local`, `.env`, fotos privadas, plantillas biométricas ni entornos virtuales desde otra computadora.

`APP_ENV=local` habilita los datos sintéticos cuando está activa su configuración. No cambiar a `production` para desarrollar. Los seeders no sustituyen la configuración de Scrall necesaria para registrar personas nuevas.

### Eventos de prueba cerca del desarrollador

Registrar una cuenta con distrito y permiso de ubicación. Después, reiniciar el entorno o ejecutar:

```powershell
php artisan db:seed --class=LocalMapEventsSeeder
```

Se crean tres eventos de cada categoría (12 en total), a 65–475 metros de la ubicación guardada de la cuenta con captura GPS más reciente. Las fotos ilustran lugares y actividades y ya vienen en el repositorio; no usan retratos de los vecinos ni necesitan descargarse al arrancar. Las emergencias se identifican como simulacros.

Si hay varias cuentas locales, se puede elegir cuál centra los eventos dejando `DEMO_MAP_ANCHOR_USER_ID=ID_DE_LA_CUENTA` en `.env`, ejecutar `php artisan config:clear` y repetir el seeder. Los eventos con inscripciones no cambian de lugar ni de horario. No se reparten puntos automáticamente.

Después de cambiar configuración y si Laravel tenía caché:

```powershell
php artisan config:clear
```

Reiniciar los procesos si cambiaron puertos o el token/configuración de Python.

## 5. Actualizar un entorno ya instalado

Tras integrar los cambios de la rama del equipo, conservando el trabajo local:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-local.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-local.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-local.ps1
```

Esto vuelve a instalar dependencias y aplica migraciones pendientes, conservando credenciales y una `APP_KEY` existente. En un entorno ya completo, `start-local.ps1` prepara las migraciones locales y arranca los servicios; no es necesario reinstalar todo a diario.

Para añadir solamente PostGIS a una instalación portable anterior:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup-postgis.ps1
php artisan migrate
```

No usar `migrate:fresh`, `db:wipe` ni eliminar `.local/pgdata` sobre desarrollo. Se perderían las cuentas y los datos guardados.

## 6. Detener todo

Abrir `detener-masi.bat` o ejecutar:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-local.ps1
```

El script usa los PID del proyecto y detiene también su scheduler y su PostgreSQL portable. No terminar indiscriminadamente todos los procesos PHP, Node o PostgreSQL del equipo.

## 7. Verificar y resolver problemas

```powershell
php artisan migrate:status
php artisan schedule:list
php artisan test
npm --prefix frontend run build
```

Las pruebas PHP usan **vecino_test**. No ejecutar suites PHP simultáneas sobre esa misma base. Las consultas de Scrall se simulan durante las pruebas y no gastan consultas reales.

Para comprobar PostGIS desde Laravel:

```powershell
php artisan tinker --execute="dump(DB::selectOne('SELECT PostGIS_Version() AS version'));"
```

| Problema | Qué revisar |
| --- | --- |
| PowerShell bloquea scripts | Usar los comandos con `-ExecutionPolicy Bypass` de esta guía; no hace falta cambiar la política global |
| `php`, `composer`, `node` o `uv` no se reconoce | Instalar la herramienta y abrir otra terminal con el `PATH` actualizado |
| `could not find driver` | Ejecutar `php --ini` y `php -m`; habilitar `pdo_pgsql` en el PHP que realmente ejecuta el proyecto |
| PHP no puede modificar `php.ini` | Usar una instalación de PHP/Laragon editable por el usuario o habilitar las extensiones manualmente en ese archivo |
| PostgreSQL rechaza usuario/contraseña | No copiar un `.env` ajeno ni dejar `DB_PASSWORD` vacía. La instalación nueva genera sus propias credenciales; si ya hay datos, conservar sus credenciales existentes |
| Falta PostGIS o no existe `ST_DWithin` | Ejecutar `setup-postgis.ps1` antes de migrar; debe activarse en desarrollo y pruebas |
| Un puerto ya está ocupado | Revisar qué proceso lo usa. El proyecto usa 5433 y no necesita reemplazar el PostgreSQL de Laragon o de otro proyecto |
| No hay correos reales | Revisar `MAIL_MAILER`; con `log` la notificación queda en `storage/logs/laravel.log` |
| No consulta el DNI | Completar `SCRALL_BASIC_TOKEN` localmente y revisar conexión/disponibilidad del proveedor |
| No se valida la foto | Revisar el servicio 8782, su log y la coincidencia de tokens; la carpeta actual es `modeloBiometrico`, no `modeloJose` |
| No suma puntos por inscripción | Es lo esperado: requiere asistencia durante el evento, ubicación reciente y distancia de hasta 100 m |
| No se actualiza ubicación con la web cerrada | El navegador no envía posiciones cerrado; abrir el mapa y activar ubicación o pulsar «Validar mi asistencia» |

Logs de los servicios: `.local/logs/`. Logs de Laravel: `storage/logs/laravel.log`. Los PID se guardan en `.local/*.pid`. Si se usaron terminales separadas, revisar también su salida.

## Instrucción para copiar al agente

```text
Lee AGENTS.md y LEVANTAR-PROYECTO.md de este repositorio. Prepara y levanta
el proyecto en Windows usando scripts/setup-local.ps1 y scripts/start-local.ps1.
Comprueba primero PHP/Composer, Node/npm y uv en PATH. No necesito instalar
PostgreSQL ni PostGIS globalmente: usa los portables de estos scripts.
En una instalación nueva deja que setup-local cree .env y las credenciales;
no copies .env.example manualmente antes. Conserva APP_KEY y credenciales si
ya existen. No borres datos, no uses migrate:fresh en desarrollo y no descartes
cambios Git. Las pruebas usan vecino_test. Comprueba migraciones, PostGIS,
scheduler y que frontend/API respondan. Si faltan credenciales de Scrall o SMTP,
deja esas integraciones pendientes e indica qué variable necesita completar
el propietario; no inventes claves ni uses credenciales de otro equipo.
```

En Linux/macOS estos `.ps1` y binarios portables de Windows no son el procedimiento de instalación. Un agente debe preparar servicios equivalentes con PostgreSQL 17 + PostGIS y las mismas variables; no ejecutar los binarios `.exe` allí. Contratos adicionales: [API](docs/backend-api.md), [mapa y empatía](docs/map-events.md) y [servicio facial](docs/facial-api.md).
