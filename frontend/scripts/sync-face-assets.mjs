import { cpSync, mkdirSync, readFileSync } from 'node:fs'
import { createHash } from 'node:crypto'
const manifest = JSON.parse(readFileSync(new URL('../src/data/face-accessories.json', import.meta.url)))
const runtime = JSON.parse(readFileSync(new URL('../node_modules/onnxruntime-web/package.json', import.meta.url)))
if (`onnxruntime-web@${runtime.version}` !== manifest.runtime) throw new Error('El runtime facial no coincide con los recursos locales.')
for (const [path, hash] of Object.entries(manifest.files)) {
  const data = readFileSync(new URL(`../../public/face-accessories/${path}`, import.meta.url))
  if (createHash('sha256').update(data).digest('hex') !== hash) throw new Error(`Recurso facial inválido: ${path}`)
}
for (const folder of ['mediapipe', 'face-accessories']) {
  const target = new URL(`../public/${folder}`, import.meta.url)
  mkdirSync(target, { recursive: true })
  cpSync(new URL(`../../public/${folder}`, import.meta.url), target, { recursive: true })
}
console.log('Recursos faciales locales copiados y verificados.')
