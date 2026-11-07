# Vecino

Laravel 12 / PHP 8.3, Vue 3, PostgreSQL portable 17.11 y un servicio CPU YuNet/SFace en `modeloJose`.

## Entorno

- `scripts/start-local.ps1`: PostgreSQL 127.0.0.1:5433, Python 8782 y Laravel 8000, ocultos y sin servicios de Windows.
- `scripts/stop-local.ps1`: detiene solo los procesos del proyecto.
- `scripts/setup-local.ps1`: instalación repetible; conserva APP_KEY y credenciales existentes.
- `php artisan migrate` funciona con el PHP de Laragon, que ya tiene pdo_pgsql habilitado.
- PostgreSQL de desarrollo: vecino. Pruebas: vecino_test. No ejecutar migrate:fresh en desarrollo sin indicación del usuario.

## Verificación

- `php artisan test`: pruebas PostgreSQL aisladas; prueba facial real optativa con FACE_LIVE_TEST=1.
- `npm run test:face`: reglas de captura y ciclo de cámara.
- `modeloJose/.venv/Scripts/python.exe -m pytest modeloJose/tests -q`.
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
