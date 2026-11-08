import { crearFiltroAccesorios, LADO_ACCESORIOS, normalizarAccesorios, probabilidadesAccesorios, recortesAccesorios } from './accesoriosRostro';
import manifest from '../data/face-accessories.json';

const BASE = '/face-accessories';

// Cambiar un peso cambia su URL. Una respuesta antigua con MIME incorrecto
// tampoco se reutiliza, porque antes estos recursos no estaban versionados.
function recurso(path) {
    const version = manifest.files[path] + (path.endsWith('.mjs') ? '-js1' : '');
    return new URL(`${BASE}/${path}?v=${version}`, window.location.origin).href;
}

export async function crearDetectorAccesorios(vision, archivos) {
    const ort = await import('onnxruntime-web/wasm');
    // Un hilo no requiere SharedArrayBuffer ni cabeceras COOP/COEP en Hestia.
    ort.env.wasm.numThreads = 1;
    ort.env.wasm.proxy = false;
    ort.env.wasm.wasmPaths = {
        mjs: recurso('ort-1.25.1/ort-wasm-simd-threaded.mjs'),
        wasm: recurso('ort-1.25.1/ort-wasm-simd-threaded.wasm'),
    };
    const sesiones = [];
    let landmarker;
    try {
        for (const nombre of ['glasses', 'mask']) sesiones.push(await ort.InferenceSession.create(recurso(`models/${nombre}.onnx`), {
            executionProviders: ['wasm'], graphOptimizationLevel: 'all',
        }));
        landmarker = await vision.FaceLandmarker.createFromOptions(archivos, {
            baseOptions: { modelAssetPath: recurso('models/face_landmarker.task'), delegate: 'CPU' },
            runningMode: 'IMAGE', numFaces: 1,
            minFaceDetectionConfidence: .5, minFacePresenceConfidence: .5,
        });
    } catch (error) {
        await Promise.allSettled(sesiones.map(s => s.release()));
        landmarker?.close();
        throw error;
    }

    const filtro = crearFiltroAccesorios();
    const recorte = document.createElement('canvas');
    recorte.width = recorte.height = LADO_ACCESORIOS;
    const ctx = recorte.getContext('2d', { willReadFrequently: true });
    let cerrado = false, enCurso = null;
    const liberar = () => {
        landmarker.close();
        return Promise.allSettled(sesiones.map(s => s.release()));
    };

    function tensor(canvas, roi) {
        ctx.drawImage(canvas, roi.x, roi.y, roi.w, roi.h, 0, 0, LADO_ACCESORIOS, LADO_ACCESORIOS);
        return new ort.Tensor('float32', normalizarAccesorios(ctx.getImageData(0, 0, LADO_ACCESORIOS, LADO_ACCESORIOS).data),
            [1, 3, LADO_ACCESORIOS, LADO_ACCESORIOS]);
    }

    return {
        reiniciar() { filtro.reiniciar(); },
        async analizar(canvas) {
            if (cerrado || enCurso) throw new Error('Detector de accesorios no disponible');
            enCurso = (async () => {
                const puntos = landmarker.detect(canvas).faceLandmarks?.[0];
                const regiones = recortesAccesorios(puntos, canvas.width, canvas.height);
                if (!regiones) {
                    filtro.reiniciar();
                    return { estado: 'accesorios', mensaje: 'Mira de frente y retira lentes o mascarilla si los usas' };
                }
                let entradaGafas, entradaMascarilla;
                let salidas = [];
                try {
                    entradaGafas = tensor(canvas, regiones.ojos);
                    entradaMascarilla = tensor(canvas, regiones.rostro);
                    // Misma imagen evaluada y guardada; no se pide otro cuadro a la camara.
                    salidas.push(await sesiones[0].run({ input: entradaGafas }));
                    salidas.push(await sesiones[1].run({ input: entradaMascarilla }));
                    const probabilidades = probabilidadesAccesorios(salidas[0].logit?.data, salidas[1].logits?.data);
                    return { ...filtro.actualizar(probabilidades), probabilidades };
                } finally {
                    entradaGafas?.dispose();
                    entradaMascarilla?.dispose();
                    for (const salida of salidas) for (const valor of Object.values(salida)) valor.dispose();
                }
            })();
            try { return await enCurso; }
            finally {
                enCurso = null;
                if (cerrado) await liberar();
            }
        },
        cerrar() {
            if (cerrado) return;
            cerrado = true;
            // Al cambiar de camara se espera la inferencia antes de liberar su memoria.
            if (!enCurso) void liberar();
        },
    };
}
