import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { crearEstabilidad } from '../../resources/js/utils/calidadCaptura.js';

const code = readFileSync(new URL('../../resources/js/composables/useCapturaRostro.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace('export function', 'function')
    .replace('await import("@mediapipe/tasks-vision")', 'await cargarVision()');

async function iniciar(calidades, accesorios = async () => ({ estado: 'listo' }), opciones = {}) {
    let ahora = 0, siguiente = 0;
    const pendientes = new Map(), recortes = [], analizados = [];
    const video = { play: async () => {}, srcObject: null, readyState: 2,
        videoWidth: 640, videoHeight: 480, currentTime: 0, frame: 0 };
    const track = { stopped: false, stop() { this.stopped = true; }, getSettings: () => ({ deviceId: 'front' }) };
    const detector = { close() {}, detectForVideo(canvas) { analizados.push(canvas.frame); return { detections: [] }; } };
    const delegates = [];
    const contexto = vm.createContext({
        ref: value => ({ value }), onBeforeUnmount() {}, crearEstabilidad,
        crearDetectorAccesorios: async () => ({ analizar: async canvas => {
            const lectura = await accesorios(canvas.frame);
            ahora += opciones.demoraAnalisis || 0;
            return lectura;
        }, reiniciar() {}, cerrar() {} }),
        evaluarCaptura(detecciones, imagen) {
            const puntuacion = calidades(imagen.frame);
            if (typeof puntuacion === 'string') return { estado: puntuacion, mensaje: 'Vuelve al óvalo' };
            return puntuacion === null ? { estado: 'calidad', mensaje: 'Mejora la toma' } : {
                estado: 'listo', puntuacion, posicion: { x: .5, y: .39, escala: .25, inclinacion: 0, giro: 0 },
            };
        },
        performance: { now: () => ahora },
        console: { warn() {} },
        cargarVision: async () => ({ FilesetResolver: { forVisionTasks: async () => ({}) },
            FaceDetector: { createFromOptions: async (archivos, config) => {
                delegates.push(config.baseOptions.delegate);
                if (opciones.sinGpu && config.baseOptions.delegate === 'GPU') throw Error('WebGL no disponible');
                if (opciones.sinCpu && config.baseOptions.delegate === 'CPU') throw Error('CPU no disponible');
                return detector;
            } } }),
        navigator: { mediaDevices: {
            getUserMedia: async () => ({ getTracks: () => [track], getVideoTracks: () => [track] }),
            enumerateDevices: async () => [],
        } },
        requestAnimationFrame(callback) { pendientes.set(++siguiente, callback); return siguiente; },
        cancelAnimationFrame(id) { pendientes.delete(id); },
        document: { createElement() {
            const canvas = { width: 0, height: 0, frame: -1,
                toDataURL: () => `jpeg-${canvas.frame}`,
                getContext: () => ({
                    drawImage(source, ...args) { canvas.frame = source.frame; if (source === video) recortes.push(args); },
                    getImageData: () => ({ frame: canvas.frame }),
                }),
            };
            return canvas;
        } },
    });
    vm.runInContext(`${code}\nglobalThis.camera = useCapturaRostro();`, contexto);
    const camera = contexto.camera;
    camera.video.value = video;
    await camera.encender();
    for (let n = 0; n < 20; n++) await Promise.resolve();
    assert.equal(camera.detectando.value, !opciones.esperaError);
    return { camera, track, recortes, analizados, delegates,
        async avanzar(t, frame, tiempoVideo = t / 1000) {
            ahora = t; video.frame = frame; video.currentTime = tiempoVideo;
            const [id, callback] = pendientes.entries().next().value;
            pendientes.delete(id);
            await callback();
        },
    };
}

test('si Android no permite GPU inicia el detector facial en CPU', async () => {
    const f = await iniciar(() => 80, undefined, { sinGpu: true });
    assert.deepEqual(f.delegates, ['GPU', 'CPU']);
    assert.equal(f.camera.errorDetector.value, false); f.camera.detener();
});

test('captura con inferencias de 700 o 1800 ms sin quedarse en la cuenta 2', async () => {
    for (const demoraAnalisis of [700, 1800]) {
        const f = await iniciar(() => 80, undefined, { demoraAnalisis });
        for (let frame = 1; frame <= 12 && !f.camera.foto.value; frame++) {
            await f.avanzar(frame * (demoraAnalisis + 120), frame);
        }
        assert.ok(f.camera.foto.value);
        assert.equal(f.track.stopped, true);
    }
});

test('distingue un fallo facial de un fallo en accesorios y expone el detalle', async () => {
    const f = await iniciar(() => 80, undefined, { sinGpu: true, sinCpu: true, esperaError: true });
    assert.equal(f.camera.errorDetector.value, true);
    assert.match(f.camera.guia.value.mensaje, /Referencia: ROSTRO/);
    assert.match(f.camera.detalleErrorDetector.value, /CPU no disponible/);
    f.camera.detener();
});

test('guarda el mejor cuadro admitido, conservando el recorte evaluado y sin ampliar', async () => {
    const f = await iniciar(frame => frame === 3 ? 98 : 70);
    for (let frame = 1; frame <= 17; frame++) {
        await f.avanzar(frame * 120, frame);
        if (frame < 17) assert.equal(f.camera.foto.value, null);
    }
    assert.equal(f.camera.foto.value, 'jpeg-3');
    assert.deepEqual([...f.recortes[0]], [80, 0, 480, 480, 0, 0, 480, 480]);
    assert.equal(f.track.stopped, true);
});

test('una pérdida de calidad descarta el candidato anterior aunque tenga mayor puntuación', async () => {
    const f = await iniciar(frame => frame === 4 ? null : frame === 3 ? 99 : frame === 10 ? 95 : 70);
    for (let frame = 1; frame <= 22; frame++) {
        await f.avanzar(frame * 120, frame);
        if (frame < 22) assert.equal(f.camera.foto.value, null);
    }
    assert.equal(f.camera.foto.value, 'jpeg-10');
});

test('conserva el avance durante una salida breve pero guarda una toma nueva al volver', async () => {
    const f = await iniciar(frame => frame >= 6 && frame <= 13 ? 'descentrado' : frame === 3 ? 99 : frame === 18 ? 95 : 70);
    let progreso;
    for (let frame = 1; frame <= 26; frame++) {
        await f.avanzar(frame * 120, frame);
        if (frame === 5) progreso = f.camera.progreso.value;
        if (frame >= 6 && frame <= 14) assert.equal(f.camera.progreso.value, progreso);
        if (frame < 26) assert.equal(f.camera.foto.value, null);
    }
    assert.equal(f.camera.foto.value, 'jpeg-18');
});

test('un vídeo congelado no avanza ni conserva la cuenta al reanudarse', async () => {
    const f = await iniciar(() => 80);
    for (let frame = 1; frame <= 20; frame++) await f.avanzar(frame * 120, frame, 0);
    assert.equal(f.camera.foto.value, null);
    assert.equal(f.camera.progreso.value, 0);
    assert.equal(f.camera.cuenta.value, 0);
    assert.deepEqual(f.analizados, [0]);
    await f.avanzar(2520, 21);
    assert.equal(f.camera.progreso.value, 0);
    assert.equal(f.camera.foto.value, null);
    f.camera.detener();
});

test('lentes o mascarilla descartan el candidato e impiden capturar hasta retirarlos', async () => {
    const f = await iniciar(() => 80, async frame => frame <= 5
        ? { estado: 'accesorios', mensaje: 'Retira tus lentes' } : { estado: 'listo' });
    for (let frame = 1; frame <= 5; frame++) {
        await f.avanzar(frame * 120, frame);
        assert.equal(f.camera.foto.value, null);
        assert.equal(f.camera.progreso.value, 0);
        assert.match(f.camera.guia.value.mensaje, /lentes/);
    }
    for (let frame = 6; frame <= 30 && !f.camera.foto.value; frame++) await f.avanzar(frame * 120, frame);
    assert.ok(f.camera.foto.value);
});

test('salir durante inferencia descarta su resultado y no reactiva la camara', async () => {
    let terminar;
    const f = await iniciar(() => 80, async frame => frame === 1
        ? new Promise(resolve => { terminar = resolve; }) : { estado: 'listo' });
    const pendiente = f.avanzar(120, 1);
    f.camera.detener();
    terminar({ estado: 'listo' });
    await pendiente;
    assert.equal(f.camera.foto.value, null);
    assert.equal(f.camera.encendida.value, false);
});

test('un fallo en accesorios no permite autocapturar como si la revision fuera correcta', async () => {
    const f = await iniciar(() => 80, async frame => {
        if (frame === 1) throw new Error('WASM fallo');
        return { estado: 'listo' };
    });
    await f.avanzar(120, 1);
    assert.equal(f.camera.foto.value, null);
    assert.equal(f.camera.errorDetector.value, true);
    assert.equal(f.camera.detectando.value, false);
});
