import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

const base = new URL('../public/face-accessories/', import.meta.url);
const manifest = JSON.parse(readFileSync(new URL('../resources/js/data/face-accessories.json', import.meta.url), 'utf8'));
const runtime = JSON.parse(readFileSync(new URL('../node_modules/onnxruntime-web/package.json', import.meta.url), 'utf8'));
if (`onnxruntime-web@${runtime.version}` !== manifest.runtime) throw new Error('Actualiza los binarios locales al cambiar ONNX Runtime.');
for (const [path, expected] of Object.entries(manifest.files)) {
    const bytes = readFileSync(new URL(path, base));
    if (createHash('sha256').update(bytes).digest('hex') !== expected) throw new Error(`Archivo de accesorios incompleto o distinto: ${path}`);
}
console.log('Modelos y runtime de accesorios locales: SHA-256 verificados.');
