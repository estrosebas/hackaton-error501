import assert from 'node:assert/strict';
import test from 'node:test';
import { crearEstabilidad, evaluarCaptura } from '../../resources/js/utils/calidadCaptura.js';

test('acepta un rostro sin puntos de ojos, descentrado y de distintos tamaños', () => {
    for (const caja of [
        { originX: 30, originY: 30, width: 180, height: 240 },
        { originX: 120, originY: 70, width: 300, height: 360 },
    ]) {
        const lectura = evaluarCaptura([{ boundingBox: caja }], { width: 480, height: 480 });
        assert.equal(lectura.estado, 'listo');
        assert.ok(Number.isFinite(lectura.puntuacion));
    }
});

test('pide corregir la distancia o el recorte antes de capturar', () => {
    const evaluar = caja => evaluarCaptura([{ boundingBox: caja }], { width: 480, height: 480 }).estado;
    assert.equal(evaluar({ originX: 50, originY: 30, width: 380, height: 420 }), 'cerca');
    assert.equal(evaluar({ originX: 150, originY: 150, width: 80, height: 100 }), 'lejos');
    for (const caja of [
        { originX: 0, originY: 60, width: 220, height: 280 },
        { originX: 270, originY: 60, width: 220, height: 280 },
        { originX: 100, originY: 0, width: 220, height: 280 },
        { originX: 100, originY: 210, width: 220, height: 280 },
    ]) assert.equal(evaluar(caja), 'descentrado');
});

test('exige un único rostro y una caja válida', () => {
    const imagen = { width: 480, height: 480 };
    const cara = { boundingBox: { originX: 5, originY: 10, width: 80, height: 100 } };
    assert.equal(evaluarCaptura([], imagen).estado, 'buscando');
    assert.equal(evaluarCaptura([cara, cara], imagen).estado, 'varias');
    for (const boundingBox of [null, {}, { ...cara.boundingBox, width: NaN }, { ...cara.boundingBox, height: 0 }]) {
        assert.equal(evaluarCaptura([{ boundingBox }], imagen).estado, 'buscando');
    }
});

const lista = (puntuacion = 70, cambios = {}) => ({ estado: 'listo', puntuacion,
    posicion: { x: 0.5, y: 0.39, escala: 0.25, inclinacion: 0, giro: 0, ...cambios } });

function imagenNitidez({ detalle = true, fondo = false } = {}) {
    const width = 480;
    const height = 480;
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const interior = x >= 144 && x <= 336 && y >= 144 && y <= 336;
            // Separa el detalle de la cara del fondo para probar que no se confundan.
            const tieneDetalle = interior ? detalle : fondo;
            const textura = tieneDetalle ? 65 * Math.sin(x / 6) * Math.cos(y / 6) : 0;
            data.set([120 + textura, 120 + textura, 120 + textura, 255], (y * width + x) * 4);
        }
    }
    return { width, height, data };
}

test('prioriza detalle facial sobre una confianza de deteccion ligeramente mayor', () => {
    const cara = score => [{
        boundingBox: { originX: 80, originY: 80, width: 320, height: 320 },
        categories: [{ score }],
    }];
    const clara = evaluarCaptura(cara(0.90), imagenNitidez());
    const borrosa = evaluarCaptura(cara(0.99), imagenNitidez({ detalle: false }));
    assert.equal(clara.estado, 'listo');
    assert.equal(borrosa.estado, 'listo', 'Nitidez ordena candidatos, no bloquea la captura');
    assert.ok(clara.puntuacion > borrosa.puntuacion);
    const seleccion = crearEstabilidad();
    assert.equal(seleccion.actualizar(borrosa, 0).mejora, true);
    assert.equal(seleccion.actualizar(clara, 120).mejora, true);
});

test('un fondo nitido no mejora la puntuacion de un rostro sin detalle', () => {
    const cara = [{
        boundingBox: { originX: 80, originY: 80, width: 320, height: 320 },
        categories: [{ score: 0.95 }],
    }];
    const liso = evaluarCaptura(cara, imagenNitidez({ detalle: false }));
    const fondo = evaluarCaptura(cara, imagenNitidez({ detalle: false, fondo: true }));
    assert.equal(fondo.puntuacion, liso.puntuacion);
});

test('acumula 2 segundos con movimientos normales y reinicia por mala calidad o vídeo detenido', () => {
    const lectura = lista();
    const estable = crearEstabilidad();
    for (let t = 0; t < 2000; t += 120) {
        const lecturaMovida = lista(70, { x: 0.5 + 0.025 * Math.sin(t / 300), inclinacion: 3 * Math.sin(t / 300) });
        assert.equal(estable.actualizar(lecturaMovida, t).lista, false);
    }
    assert.equal(estable.actualizar(lectura, 2040).lista, true);
    assert.equal(estable.actualizar({ estado: 'calidad' }, 2160).cuenta, 0);
    assert.equal(estable.actualizar(lectura, 2280).lista, false);
    assert.equal(estable.actualizar(lectura, 6000).lista, false);
});

test('selecciona el candidato de mayor calidad y descarta los de ventanas interrumpidas', () => {
    const estable = crearEstabilidad();
    assert.equal(estable.actualizar(lista(70), 0).mejora, true);
    assert.equal(estable.actualizar(lista(90), 120).mejora, true);
    assert.equal(estable.actualizar(lista(80), 240).mejora, false);
    const pausa = estable.actualizar({ estado: 'descentrado' }, 360);
    assert.equal(pausa.pausada, true);
    assert.ok(pausa.progreso > 0);
    assert.equal(pausa.descartarCandidato, true);
    const nueva = estable.actualizar(lista(60), 480);
    assert.equal(nueva.reiniciada, false);
    assert.equal(nueva.descartarCandidato, true);
    assert.equal(nueva.mejora, true);
    assert.equal(nueva.progreso, pausa.progreso);
    assert.equal(estable.actualizar(lista(70, { inclinacion: 3 }), 600).reiniciada, false);
    assert.equal(estable.actualizar(lista(70, { giro: 0.06 }), 720).reiniciada, false);
});

test('una secuencia escasa o timestamps repetidos no bastan para la captura', () => {
    const estable = crearEstabilidad();
    for (let t = 0; t <= 2000; t += 400) assert.equal(estable.actualizar(lista(), t).lista, false);
    assert.equal(estable.actualizar(lista(), 2000).reiniciada, true);
    assert.equal(estable.actualizar(lista(), 1900).reiniciada, true);
    assert.equal(estable.actualizar(lista(NaN), 2120).mejora, false);
});

test('un segundo fuera del óvalo pausa sin sumar tiempo y exige cuadros nuevos al volver', () => {
    const estable = crearEstabilidad();
    for (let t = 0; t <= 1800; t += 120) estable.actualizar(lista(), t);
    const previo = estable.actualizar({ estado: 'descentrado' }, 1920);
    for (let t = 2040; t <= 2760; t += 120) {
        const pausa = estable.actualizar({ estado: 'descentrado' }, t);
        assert.equal(pausa.lista, false);
        assert.equal(pausa.progreso, previo.progreso);
    }
    const vuelta = estable.actualizar(lista(), 2880);
    assert.equal(vuelta.reiniciada, false);
    assert.equal(vuelta.progreso, previo.progreso);
    assert.equal(vuelta.lista, false);
    assert.equal(estable.actualizar(lista(), 3000).lista, false);
    assert.equal(estable.actualizar(lista(), 3120).lista, false);
    assert.equal(estable.actualizar(lista(), 3240).lista, true);
});

test('salidas largas o múltiples personas borran el avance', () => {
    for (const motivo of ['descentrado', 'varias', 'buscando', 'calidad']) {
        const estable = crearEstabilidad();
        for (let t = 0; t <= 600; t += 120) estable.actualizar(lista(), t);
        const lectura = estable.actualizar({ estado: motivo }, motivo === 'descentrado' ? 2160 : 720);
        assert.equal(lectura.progreso, 0);
        assert.equal(lectura.descartarCandidato, true);
    }
});

test('una inferencia lenta no se confunde con una camara congelada', () => {
    for (const demora of [700, 1800]) {
        const estable = crearEstabilidad();
        let salida;
        for (let i = 0; i < 10; i++) {
            const inicio = i * (demora + 120);
            salida = estable.actualizar(lista(), inicio, inicio + demora);
            if (i > 0) assert.equal(salida.reiniciada, false);
        }
        assert.equal(salida.lista, true);
    }
});

test('sigue rechazando pausas reales y no basta una sola inferencia muy larga', () => {
    const estable = crearEstabilidad();
    assert.equal(estable.actualizar(lista(), 0, 3000).lista, false);
    assert.equal(estable.actualizar(lista(), 3600, 6600).reiniciada, true);
    assert.equal(estable.actualizar(lista(), 6720, 9720).lista, false);
    assert.equal(estable.actualizar({ estado: 'accesorios' }, 9840, 12840).progreso, 0);
});
