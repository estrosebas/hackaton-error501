import test from 'node:test';
import assert from 'node:assert/strict';
import { crearFiltroAccesorios, normalizarAccesorios, probabilidadesAccesorios, recortesAccesorios } from '../../resources/js/utils/accesoriosRostro.js';

const libre = { gafas: .1, mascarilla: .1, sinMascarilla: .9 };
test('exige tres lecturas libres y se reinicia ante accesorios o duda', () => {
    const filtro = crearFiltroAccesorios();
    assert.notEqual(filtro.actualizar(libre).estado, 'listo');
    filtro.actualizar(libre);
    assert.equal(filtro.actualizar(libre).estado, 'listo');
    assert.notEqual(filtro.actualizar({ ...libre, gafas: .9 }).estado, 'listo');
    assert.match(filtro.actualizar({ ...libre, gafas: .9 }).mensaje, /lentes/);
    assert.notEqual(filtro.actualizar(libre).estado, 'listo');
    filtro.reiniciar();
    assert.notEqual(filtro.actualizar(libre).estado, 'listo');
});
test('avisa mascarilla, lentes o ambos sin confundir incorrect_mask con rostro libre', () => {
    for (const [p, mensaje] of [
        [{ gafas: .1, mascarilla: .9, sinMascarilla: .1 }, /mascarilla/],
        [{ gafas: .9, mascarilla: .9, sinMascarilla: .1 }, /lentes y la mascarilla/],
    ]) {
        const filtro = crearFiltroAccesorios();
        filtro.actualizar(p);
        assert.match(filtro.actualizar(p).mensaje, mensaje);
    }
    assert.ok(probabilidadesAccesorios([0], [-9, -9, 9]).mascarilla > .99);
    assert.throws(() => probabilidadesAccesorios([NaN], [1, 2, 3]));
    assert.throws(() => probabilidadesAccesorios([0], [1, 2]));
});
test('respeta RGB CHW e ImageNet, sin cambiar orden de canales', () => {
    const rgba = new Uint8ClampedArray(112 * 112 * 4).fill(255);
    rgba[0] = 0;
    const t = normalizarAccesorios(rgba);
    assert.equal(t.length, 3 * 112 * 112);
    assert.ok(Math.abs(t[0] - (0 - .485) / .229) < 1e-5);
    assert.ok(Math.abs(t[112 * 112] - (1 - .456) / .224) < 1e-5);
    assert.throws(() => normalizarAccesorios(new Uint8ClampedArray(4)));
});
test('no inventa un recorte cuando faltan puntos; limita los validos al cuadro', () => {
    assert.equal(recortesAccesorios(undefined, 720, 720), null);
    assert.equal(recortesAccesorios([], 720, 720), null);
    const puntos = Array.from({ length: 478 }, (_, i) => ({ x: .2 + (i % 10) * .05, y: .15 + (i % 12) * .04 }));
    for (const region of Object.values(recortesAccesorios(puntos, 720, 720))) {
        assert.ok(region.x >= 0 && region.y >= 0 && region.w > 0 && region.h > 0);
        assert.ok(region.x + region.w <= 720 && region.y + region.h <= 720);
    }
    puntos[33].x = NaN;
    assert.equal(recortesAccesorios(puntos, 720, 720), null);
});
