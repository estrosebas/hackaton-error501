# Vecino

Base del proyecto de comunidad vecinal: Laravel 12, Vue 3, PostgreSQL y un microservicio privado Python con YuNet/SFace.

## Estado actual

- Registro de vecinos con DNI, contraseña, contacto, distrito, barrio y fotografía.
- Login por DNI y contraseña mediante sesión y CSRF de Laravel.
- `/actualizar-foto` adapta la captura del proyecto `clubmejia`: MediaPipe, guía de encuadre, estabilidad, revisión de lentes/mascarilla y captura automática.
- Python valida nuevamente la imagen y genera una plantilla SFace de 128 valores.
- La plantilla se cifra con `APP_KEY` en PostgreSQL; la fotografía se guarda en `storage/app/private/faces`, fuera de `public`.
- Solo el propietario autenticado puede consultar o reemplazar su fotografía.
- No se ha copiado el padrón, la galería biométrica, credenciales, fotos de socios ni la integración con Hikvision del club.

La consulta externa de DNI todavía no está integrada. El nombre se ingresa manualmente. La validación actual no acredita titularidad del DNI, residencia, unicidad de persona ni prueba de vida/PAD. El servicio devuelve `liveness: not_evaluated`. La actualización de foto requiere sesión; no compara la nueva foto con la plantilla anterior.

El mapa, publicaciones, recomendaciones, intercambios, emergencias, modo offline y PostGIS quedan para las siguientes etapas. Los campos de coordenadas ya están preparados; PostgreSQL portable por ahora no incluye PostGIS.

## Uso diario en Windows

Desde esta carpeta, con PowerShell:

```powershell
.\scripts\start-local.ps1
```

Abrir [el registro](http://127.0.0.1:8000/registro) o [el login](http://127.0.0.1:8000/login).

```powershell
.\scripts\stop-local.ps1
```

Los procesos se ejecutan en segundo plano sin ventanas adicionales. PostgreSQL se inicia con `pg_ctl`, sin instalar un servicio de Windows. Los logs y PID están en `.local`.

| Componente | Dirección |
| --- | --- |
| Laravel | http://127.0.0.1:8000 |
| Python privado | http://127.0.0.1:8782 |
| PostgreSQL 17.11 portable | 127.0.0.1:5433 |
| Base de desarrollo | vecino |
| Base aislada de pruebas | vecino_test |

La cámara funciona en `localhost` o `127.0.0.1`. Para abrir desde un teléfono por red se necesita HTTPS y configurar la URL/cookies del entorno correspondiente.

## Instalación reproducible

Requisitos: PHP 8.2+ y Composer, Node.js/npm, uv y Python 3.13 (uv puede descargarlo).

```powershell
.\scripts\setup-local.ps1
```

El script descarga los binarios de PostgreSQL de [EDB](https://www.enterprisedb.com/download-postgresql-binaries), crea el clúster local con autenticación SCRAM y escucha solo en `127.0.0.1`. Crea un rol de aplicación sin privilegios de superusuario y dos bases, genera contraseñas/token locales, instala dependencias, ejecuta migraciones y compila el frontend.

La primera instalación habilita `pdo_pgsql` y `pgsql` en el PHP activo de Laragon. Conserva el archivo anterior en `.local/php.ini.before-pgsql`. No cambia la base ni el código de `clubmejia`. Los pesos y recursos del navegador se incluyen localmente; no se descargan al iniciar el servicio.

No regenerar `APP_KEY` después de guardar plantillas: esa clave permite descifrarlas. `setup-local.ps1` conserva una clave existente. `.env`, `.env.testing`, `modeloJose/.env`, el clúster y las contraseñas están excluidos de Git.

Las migraciones se usan normalmente:

```powershell
php artisan migrate
php artisan migrate:status
```

Para desarrollar la interfaz con recarga automática, mantener `npm run dev` en otra terminal. Al terminarlo, asegurarse de que no quede `public/hot` apuntando a un Vite detenido.

## Comprobaciones

```powershell
php artisan test
npm run test:face
.\modeloJose\.venv\Scripts\python.exe -m pytest modeloJose/tests -q
npm run build
```

PHPUnit fuerza `vecino_test`, usa almacenamiento de fotografías de prueba y revierte los registros. Las pruebas normales simulan la respuesta HTTP de Python para comprobar rechazos, indisponibilidad, duplicados, cifrado y autorización.

La prueba opcional de integración ejecuta YuNet/SFace reales y guarda la plantilla en la base de prueba. Necesita el servicio Python iniciado y una imagen pública de ejemplo de OpenCV, descargada solo a `.local`:

```powershell
curl.exe -fL https://raw.githubusercontent.com/opencv/opencv/master/samples/data/lena.jpg -o .local/downloads/lena.jpg
$env:FACE_LIVE_TEST = '1'
php artisan test --filter=LiveEnrollmentTest
Remove-Item Env:\FACE_LIVE_TEST
```

Esa imagen sirve para verificar el procesamiento técnico; no prueba cámara física, identidad ni liveness. El build verifica los hashes de los modelos de accesorios; `modeloJose/models/manifest.json` registra los hashes de YuNet/SFace.

Consultar [el contrato facial](docs/facial-api.md) y [la procedencia de los componentes](docs/reutilizacion.md).
