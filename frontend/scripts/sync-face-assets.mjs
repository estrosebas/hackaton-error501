import { cpSync, mkdirSync, readFileSync } from 'node:fs'
import { createHash } from 'node:crypto'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const assetsRoot = process.env.FACE_ASSETS_ROOT || fileURLToPath(new URL('../../public/', import.meta.url))
const manifest = JSON.parse(readFileSync(new URL('../src/data/face-accessories.json', import.meta.url)))
const runtime = JSON.parse(readFileSync(new URL('../node_modules/onnxruntime-web/package.json', import.meta.url)))
if (`onnxruntime-web@${runtime.version}` !== manifest.runtime) throw new Error('El runtime facial no coincide con los recursos locales.')
for (const [path, hash] of Object.entries(manifest.files)) {
  const data = readFileSync(resolve(assetsRoot, 'face-accessories', path))
  if (createHash('sha256').update(data).digest('hex') !== hash) throw new Error(`Recurso facial inválido: ${path}`)
}
for (const folder of ['mediapipe', 'face-accessories']) {
  const target = new URL(`../public/${folder}`, import.meta.url)
  mkdirSync(target, { recursive: true })
  cpSync(resolve(assetsRoot, folder), target, { recursive: true })
}
console.log('Recursos faciales locales copiados y verificados.')
