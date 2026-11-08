# Guía de masi

Driver.js se carga bajo demanda para un recorrido de tres pasos: Inicio y recomendaciones, Mapa/creación y puntos, y Más/datos privados. Usa la paleta oscura de masi y respeta la preferencia de movimiento reducido.

El primer acceso de cada cuenta en cada dispositivo ofrece la guía después de cerrar la selección de gustos. Terminarla o cerrarla guarda únicamente el indicador `masi.tour.v1.{id}` en localStorage; no guarda credenciales ni biometría. En Más, «Cómo funciona masi» permite repetirla. Salir de la cuenta detiene un recorrido abierto.

La versión se muestra al pie de Más. `build/version.json` permite detectar una compilación posterior; la notificación de actualización requiere que el usuario pulse Actualizar, para conservar formularios que estén abiertos.
