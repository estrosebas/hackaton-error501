import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { crearEstabilidad, evaluarCaptura } from '../../resources/js/utils/calidadCaptura.js';

const code = readFileSync(new URL('../../resources/js/composables/useCapturaRostro.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
function setup(getUserMedia) {
    const video = { play: async () => {}, srcObject: null };
    const context = vm.createContext({
        ref: value => ({ value }), onBeforeUnmount: () => {},
        crearEstabilidad, evaluarCaptura,
        crearDetectorAccesorios: async () => ({ analizar: async () => ({ estado: 'listo' }), reiniciar() {}, cerrar() {} }),
        navigator: { mediaDevices: { getUserMedia, enumerateDevices: async () => [
            { kind: 'videoinput', deviceId: 'front', label: 'Frontal' },
            { kind: 'videoinput', deviceId: 'back', label: 'Trasera' },
        ] } }, console: { warn: () => {} },
        requestAnimationFrame: () => 1, cancelAnimationFrame: () => {},
    });
    vm.runInContext(`${code}\nglobalThis.camera = useCapturaRostro();`, context);
    context.camera.video.value = video;
    return context.camera;
}
function stream(id) {
    const track = { stopped: false, stop() { this.stopped = true; }, getSettings: () => ({ deviceId: id }) };
    return { track, getTracks: () => [track], getVideoTracks: () => [track] };
}
test('prefiere frontal y cambiar libera stream anterior y selecciona por id exacto', async () => {
    const calls = [];
    const streams = [stream('front'), stream('back')];
    const camera = setup(async constraints => { calls.push(constraints); return streams[calls.length - 1]; });
    assert.equal(await camera.encender(), true);
    assert.equal(calls[0].video.facingMode.ideal, 'user');
    assert.equal(camera.camaras.value.length, 2);
    assert.equal(await camera.cambiarCamara('back'), true);
    assert.equal(calls[1].video.deviceId.exact, 'back');
    assert.equal(streams[0].track.stopped, true);
    assert.equal(camera.camaraSeleccionada.value, 'back');
    camera.detener();
    assert.equal(streams[1].track.stopped, true);
});
test('ignora y libera permiso de camara resuelto despues de salir del paso', async () => {
    let resolve;
    const captured = stream('front');
    const camera = setup(() => new Promise(done => { resolve = done; }));
    const pending = camera.encender();
    camera.detener();
    resolve(captured);
    assert.equal(await pending, false);
    assert.equal(captured.track.stopped, true);
    assert.equal(camera.encendida.value, false);
});
test('permiso denegado permite reintentar sin dejar estado de carga', async () => {
    const camera = setup(async () => { throw { name: 'NotAllowedError' }; });
    assert.equal(await camera.encender(), false);
    assert.match(camera.error.value, /permiso/);
    assert.equal(camera.iniciando.value, false);
    assert.equal(camera.encendida.value, false);
});
