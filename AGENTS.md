# Vecino

Laravel 12 / PHP 8.3, Vue 3, PostgreSQL portable 17.11 y un servicio CPU YuNet/SFace en `modeloBiometrico`.

## Entorno

- `scripts/start-local.ps1`: PostgreSQL 127.0.0.1:5433, Python 8782 y Laravel 8000, ocultos y sin servicios de Windows.
- `scripts/stop-local.ps1`: detiene solo los procesos del proyecto.
- `scripts/setup-local.ps1`: instalación repetible; conserva APP_KEY y credenciales existentes.
- `php artisan migrate` funciona con el PHP de Laragon, que ya tiene pdo_pgsql habilitado.
- PostgreSQL de desarrollo: vecino. Pruebas: vecino_test. No ejecutar migrate:fresh en desarrollo sin indicación del usuario.

## Verificación

- `php artisan test`: pruebas PostgreSQL aisladas; prueba facial real optativa con FACE_LIVE_TEST=1.
- `npm run test:face`: reglas de captura y ciclo de cámara.
- `modeloBiometrico/.venv/Scripts/python.exe -m pytest modeloBiometrico/tests -q`.
- `npm run build`: verifica recursos faciales locales y compila Vite.
- `php vendor/bin/pint --dirty` después de cambios PHP.

## Contratos

- JSON del navegador usa sesión y CSRF, rutas en routes/web.php. No desactivar CSRF.
- Fotos en storage/app/private; solo su propietario tiene acceso. Nunca exponer embeddings ni token Python al navegador.
- Embeddings cifrados con APP_KEY. No regenerar una clave existente.
- Python es privado, requiere Bearer y escucha en loopback; no mantiene la galería del club.
- La validación comprueba calidad/enrolabilidad, no identidad RENIEC, residencia, unicidad ni liveness.
- Origen de componentes y limitaciones en docs/reutilizacion.md y docs/facial-api.md.
- No copiar credenciales, fotos, galerías ni datos personales desde clubmejia.
- API nueva en routes/api.php, prefijo /api/v1, Sanctum Bearer para Capacitor. Contrato en docs/backend-api.md.
- Registro exige identity_token cifrado, DNI, foto, ubicación reciente y UBIGEO existente. Identidad desde Scrall; nombres de región/provincia/distrito derivados del catálogo, nunca del cliente.
- SCRALL_BASIC_TOKEN solo en .env; pruebas simulan Scrall, nunca gastan consultas automáticamente.
- Spatie permissions/media/tags instalados. Roles públicos solo neighbor. Adjuntos en disco local privado, acceso exclusivo del propietario.
- frontend/ tiene registro y login DNI/contraseña conectados a /api/v1 mediante proxy Vite. Las vistas requieren validar el token con /profile; no existe acceso de demo. Logout revoca el token y limpia sessionStorage. RegistrationForm captura ubicación con Capacitor Geolocation y foto con MediaPipe/ONNX, validada nuevamente por Python.
- Recursos faciales del frontend se generan con scripts/sync-face-assets.mjs en predev/prebuild desde public/ del backend. No modificar los pesos sin actualizar sus hashes.
- Eventos del mapa: PostGIS requerido en vecino y vecino_test (`scripts/setup-postgis.ps1`); conexión UTC. Puntos solo tras asistencia inscrita, durante el evento, GPS reciente y ST_DWithin <=100 m; ledger único por evento/usuario. Scheduler `events:award-points` idempotente. Cinco marcos a 10/20/30/40/50 puntos; comprobar desbloqueo en servidor. Contrato en docs/map-events.md.
