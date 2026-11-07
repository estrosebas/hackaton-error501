# Frontend masi

Aplicación Vue 3 separada de Laravel. Desde la raíz, scripts/start-local.ps1 inicia PostgreSQL, Python, Laravel y Vite. Abrir http://127.0.0.1:5173/registro.

El registro consulta DNI, bloquea sus datos, carga región/provincia/distrito en cascada, pide ubicación actual y pasa a la captura biométrica. Crea la cuenta mediante /api/v1 y conserva el token durante la sesión de esta pestaña. Login y comunidad mantienen su comportamiento de demostración.

npm run dev y npm run build preparan los recursos MediaPipe/ONNX desde ../public, verifican hashes y los incluyen en el build. Las copias generadas no se versionan. La cámara reutiliza los componentes derivados de Club Mejía; Laravel y Python vuelven a validar cada imagen.

Vite usa proxy para /api hacia http://127.0.0.1:8000. En un despliegue separado, configurar VITE_API_BASE_URL y CORS con los dominios reales. Cámara y ubicación necesitan HTTPS o localhost. Capacitor usa @capacitor/geolocation; no se han generado Android/iOS.
