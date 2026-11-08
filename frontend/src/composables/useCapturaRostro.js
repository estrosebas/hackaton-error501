import { onBeforeUnmount, ref } from "vue";
import { crearEstabilidad, evaluarCaptura } from "../utils/calidadCaptura";
import { crearDetectorAccesorios } from "../utils/detectorAccesorios";

/**
 * Cámara frontal con autocaptura guiada por calidad y estabilidad. El servidor
 * vuelve a validar el JPEG. Si el detector no carga se ofrece reintentar.
 */

/** Lado del cuadrado que se guarda. Suficiente para enrolar un rostro. */
const LADO = 720;

export function useCapturaRostro() {
    const video = ref(null);
    const foto = ref(null);
    const encendida = ref(false);
    const error = ref("");
    const detectando = ref(false);
    const errorDetector = ref(false);
    const detalleErrorDetector = ref('');
    const guia = ref({ estado: "buscando", mensaje: "Acomoda tu rostro en el óvalo" });
    const cuenta = ref(0);
    const progreso = ref(0);
    const camaras = ref([]);
    const camaraSeleccionada = ref('');
    const iniciando = ref(false);

    let stream = null;
    let detector = null;
    let accesorios = null;
    let cuadro = null;
    const estabilidad = crearEstabilidad();
    let lienzo = null;
    let mejorCuadro = null;
    let ultimoAnalisis = -Infinity;
    let ultimoAnalisisFinalizado = -Infinity;
    let ultimoFrame = -1;
    let desmontado = false;
    let generacion = 0;

    /**
     * Pide la cámara frontal y empieza a mostrar.
     *
     * Se resuelve en cuanto hay imagen; la carga del detector queda suelta a
     * propósito para no demorar lo único que el usuario necesita para avanzar.
     */
    async function encender(deviceId = camaraSeleccionada.value) {
        detener();
        const actual = generacion;
        iniciando.value = true;
        error.value = "";
        errorDetector.value = false;
        detalleErrorDetector.value = '';
        foto.value = null;
        estabilidad.reiniciar();
        ultimoAnalisis = -Infinity;
        ultimoAnalisisFinalizado = -Infinity;
        ultimoFrame = -1;
        cuenta.value = 0;
        guia.value = { estado: 'buscando', mensaje: 'Acomoda tu rostro en el óvalo' };

        if (!navigator.mediaDevices?.getUserMedia) {
            error.value = "Este navegador no permite usar la cámara. Prueba con Chrome o Safari.";
            iniciando.value = false;
            return false;
        }

        try {
            const nuevo = await navigator.mediaDevices.getUserMedia({
                video: {
                    ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: "user" } }),
                    width: { ideal: 1280 },
                    height: { ideal: 1280 },
                },
                audio: false,
            });
            if (desmontado || actual !== generacion) {
                nuevo.getTracks().forEach(track => track.stop());
                return false;
            }
            stream = nuevo;
            camaraSeleccionada.value = stream.getVideoTracks()[0]?.getSettings().deviceId || deviceId;
            await listarCamaras();
        } catch (e) {
            if (actual !== generacion || desmontado) return false;
            error.value = mensajeDeCamara(e);
            iniciando.value = false;
            await listarCamaras();
            return false;
        }

        if (desmontado || actual !== generacion) return false;

        encendida.value = true;
        await conectarVideo();
        if (actual !== generacion) return false;
        iniciando.value = false;
        cargarDetector(actual);
        return true;
    }

    async function listarCamaras() {
        try {
            camaras.value = (await navigator.mediaDevices.enumerateDevices())
                .filter(device => device.kind === 'videoinput')
                .map((device, index) => ({ id: device.deviceId, nombre: device.label || `Cámara ${index + 1}` }));
        } catch { /* La captura puede seguir aunque no se puedan enumerar dispositivos. */ }
    }

    async function cambiarCamara(id) {
        camaraSeleccionada.value = id;
        return encender(id);
    }

    /**
     * El permiso puede fallar por motivos muy distintos y cada uno se arregla
     * de otra forma: decir solo "no se pudo" deja a la persona sin salida.
     */
    function mensajeDeCamara(e) {
        const nombre = e?.name || "";

        if (nombre === "NotAllowedError" || nombre === "SecurityError") {
            return "No nos diste permiso para usar la cámara. Habilítalo desde el candado de la barra de direcciones y vuelve a intentar.";
        }
        if (nombre === "NotFoundError" || nombre === "DevicesNotFoundError") {
            return "No encontramos ninguna cámara en este dispositivo.";
        }
        if (nombre === "NotReadableError" || nombre === "TrackStartError") {
            return "Otra aplicación está usando la cámara. Ciérrala y vuelve a intentar.";
        }

        return "No pudimos abrir la cámara. Revisa los permisos y vuelve a intentar.";
    }

    async function conectarVideo() {
        // La vista monta el <video> cuando `encendida` pasa a true, así que
        // puede no existir todavía en este tick.
        for (let intento = 0; intento < 40 && !video.value; intento += 1) {
            await new Promise((r) => requestAnimationFrame(r));
        }

        if (!video.value || !stream) return;

        video.value.srcObject = stream;

        try {
            await video.value.play();
        } catch {
            // Safari puede rechazar play() si el gesto se perdió; el atributo
            // autoplay del elemento lo resuelve igual.
        }
    }

    /**
     * Carga MediaPipe desde los archivos del propio servidor.
     *
     * Auto-hospedado y no desde un CDN para que el formulario funcione también
     * cuando el club sale a internet por un enlace malo o filtrado.
     */
    async function cargarDetector(actual) {
        let creado, accesoriosCreados;
        let etapa = 'ROSTRO';
        try {
            const vision = await import("@mediapipe/tasks-vision");
            const { FilesetResolver, FaceDetector } = vision;
            const archivos = await FilesetResolver.forVisionTasks("/mediapipe/wasm");

            const opciones = delegate => ({
                baseOptions: { modelAssetPath: "/mediapipe/blaze_face_short_range.tflite", delegate },
                runningMode: "VIDEO", minDetectionConfidence: 0.6,
            });
            try {
                creado = await FaceDetector.createFromOptions(archivos, opciones('GPU'));
            } catch (gpuError) {
                // Algunos celulares o navegadores no permiten inicializar WebGL.
                if (desmontado || actual !== generacion) return;
                console.warn('[solicitudes] GPU no disponible; probando CPU', gpuError);
                creado = await FaceDetector.createFromOptions(archivos, opciones('CPU'));
            }
            if (desmontado || actual !== generacion) { creado.close(); return; }
            etapa = 'ACCESORIOS';
            accesoriosCreados = await crearDetectorAccesorios(vision, archivos);

            if (desmontado || !encendida.value || actual !== generacion) {
                creado.close();
                accesoriosCreados.cerrar();
                return;
            }

            detector = creado;
            accesorios = accesoriosCreados;
            detectando.value = true;
            mirar();
        } catch (e) {
            creado?.close();
            accesoriosCreados?.cerrar();
            if (actual !== generacion) return;
            // La vista ofrece reintento si la captura automatica no puede iniciar.
            detectando.value = false;
            errorDetector.value = true;
            detalleErrorDetector.value = `${etapa}: ${e?.message || String(e)}`;
            guia.value = { estado: 'accesorios', mensaje: etapa === 'ROSTRO'
                ? 'No pudimos iniciar el detector facial. Reintenta o prueba otro navegador. Referencia: ROSTRO'
                : 'No pudimos cargar la revisión de lentes y mascarilla. Reintenta la cámara. Referencia: ACCESORIOS' };
            console.warn("[solicitudes] autocaptura no disponible", { etapa, mensaje: e?.message, error: e });
        }
    }

    /**
     * Mira el cuadro actual y decide si ya se puede disparar.
     *
     * Acumula 2 segundos de cuadros aptos y permite correcciones breves del
     * encuadre. Tras una pausa solo selecciona candidatos nuevos.
     */
    async function mirar() {
        if (desmontado || !detector || !encendida.value || foto.value) return;

        const elemento = video.value;
        const actual = generacion;

        if (!elemento || elemento.readyState < 2 || !elemento.videoWidth) {
            estabilidad.reiniciar();
            mejorCuadro = null;
            progreso.value = 0;
            cuenta.value = 0;
            cuadro = requestAnimationFrame(mirar);
            return;
        }

        const ahora = performance.now();
        if (ahora - ultimoAnalisis < 120 || elemento.currentTime === ultimoFrame) {
            if (ahora - ultimoAnalisisFinalizado > 450 && mejorCuadro) {
                estabilidad.reiniciar();
                mejorCuadro = null;
                progreso.value = 0;
                cuenta.value = 0;
                guia.value = { estado: 'buscando', mensaje: 'Esperando una imagen nueva de la cámara…' };
            }
            cuadro = requestAnimationFrame(mirar);
            return;
        }
        ultimoAnalisis = ahora;
        ultimoFrame = elemento.currentTime;

        let lectura;

        try {
            // Detect, assess and save the same square, including portrait video.
            dibujarCuadro(elemento);
            const salida = detector.detectForVideo(lienzo, ahora);
            lectura = evaluarCaptura(salida?.detections ?? [], lienzo.getContext('2d').getImageData(0, 0, lienzo.width, lienzo.height));
            if (lectura.estado === 'listo') {
                const revision = await accesorios.analizar(lienzo);
                if (actual !== generacion || !encendida.value || foto.value) return;
                if (revision.estado !== 'listo') lectura = revision;
            } else accesorios.reiniciar();
        } catch (e) {
            if (actual !== generacion) return;
            estabilidad.reiniciar();
            mejorCuadro = null;
            progreso.value = 0;
            cuenta.value = 0;
            guia.value = { estado: 'accesorios', mensaje: 'No pudimos comprobar los accesorios. Reintenta la cámara' };
            detectando.value = false;
            errorDetector.value = true;
            detalleErrorDetector.value = `ANALISIS: ${e?.message || String(e)}`;
            console.warn('[solicitudes] analisis de captura no disponible', e);
            return;
        }

        guia.value = lectura;
        ultimoAnalisisFinalizado = performance.now();
        const estable = estabilidad.actualizar(lectura, ahora, ultimoAnalisisFinalizado);
        cuenta.value = estable.cuenta;
        progreso.value = estable.progreso;
        if (estable.pausada) guia.value = { ...lectura, mensaje: `${lectura.mensaje}. Vuelve al óvalo para continuar; conservamos tu avance` };
        if (estable.descartarCandidato) mejorCuadro = null;
        try {
            if (estable.mejora) {
                mejorCuadro ??= document.createElement('canvas');
                mejorCuadro.width = lienzo.width;
                mejorCuadro.height = lienzo.height;
                mejorCuadro.getContext('2d').drawImage(lienzo, 0, 0);
            }
            if (estable.lista) {
                capturar();
                return;
            }
        } catch {
            detener();
            error.value = 'No pudimos preparar la fotografía. Vuelve a iniciar la cámara';
            return;
        }

        cuadro = requestAnimationFrame(mirar);
    }

    function dibujarCuadro(elemento) {
        const lado = Math.min(elemento.videoWidth, elemento.videoHeight);
        lienzo ??= document.createElement('canvas');
        // Never upscale a low-resolution camera and pretend it has more detail.
        const salida = Math.min(LADO, lado);
        if (lienzo.width !== salida || lienzo.height !== salida) { lienzo.width = salida; lienzo.height = salida; }
        lienzo.getContext('2d', { willReadFrequently: true }).drawImage(elemento,
            (elemento.videoWidth - lado) / 2, (elemento.videoHeight - lado) / 2,
            lado, lado, 0, 0, salida, salida);
    }

    /**
     * Guarda el mejor cuadro del intervalo estable en un JPEG cuadrado.
     *
     * Se recorta al centro porque la cámara entrega 16:9 y lo que importa es la
     * cara: un cuadrado centrado es además lo que espera el terminal.
     */
    function capturar() {
        // No interpolation, enhancement or extra video frame after assessment.
        if (!mejorCuadro || guia.value.estado !== 'listo') return null;
        foto.value = mejorCuadro.toDataURL("image/jpeg", 0.95);
        cuenta.value = 0;
        detener();

        return foto.value;
    }

    /** Vuelve a la cámara descartando la foto tomada. */
    async function reintentar() {
        foto.value = null;
        estabilidad.reiniciar();
        await encender();
    }

    /**
     * Suelta la cámara y el detector.
     *
     * Soltar el stream importa de verdad: si no, el led de la cámara queda
     * prendido y el navegador sigue mostrando que el sitio la está usando.
     */
    function detener() {
        generacion += 1;
        estabilidad.reiniciar();
        cuenta.value = 0;
        progreso.value = 0;
        lienzo = null;
        mejorCuadro = null;
        iniciando.value = false;
        if (cuadro) {
            cancelAnimationFrame(cuadro);
            cuadro = null;
        }

        if (detector) {
            detector.close();
            detector = null;
        }
        if (accesorios) {
            accesorios.cerrar();
            accesorios = null;
        }

        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }

        if (video.value) {
            video.value.srcObject = null;
        }

        encendida.value = false;
        detectando.value = false;
    }

    onBeforeUnmount(() => {
        desmontado = true;
        detener();
    });

    return {
        video,
        foto,
        encendida,
        detectando,
        errorDetector,
        detalleErrorDetector,
        error,
        guia,
        cuenta,
        progreso,
        camaras,
        camaraSeleccionada,
        iniciando,
        cambiarCamara,
        encender,
        reintentar,
        detener,
    };
}
