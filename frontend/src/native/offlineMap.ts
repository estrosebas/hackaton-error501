import { Preferences } from '@capacitor/preferences'
import { Filesystem, Directory } from '@capacitor/filesystem'
import { Capacitor } from '@capacitor/core'

const prefix = 'masi.map.v1.'
function key() {
  const user = JSON.parse(sessionStorage.getItem('masi.api.user') || 'null')
  return user?.id && user?.ubigeo ? `${prefix}${user.id}.${user.ubigeo}` : undefined
}
export async function saveMapSnapshot(data: unknown) {
  const scope = key()
  if (scope) await Preferences.set({ key: scope, value: JSON.stringify({ at: Date.now(), data }) })
}
export async function readMapSnapshot<T>(): Promise<{at:number;data:T}|undefined> {
  const scope = key(); if (!scope) return
  const { value } = await Preferences.get({ key: scope })
  if (!value) return
  try { const snapshot = JSON.parse(value); if (Date.now()-snapshot.at < 86400000) return snapshot } catch { /* Invalid cache is ignored. */ }
}
export async function clearMapSnapshots() {
  const { keys } = await Preferences.keys()
  await Promise.all(keys.filter(k=>k.startsWith(prefix)).map(key=>Preferences.remove({key})))
}
// Only opt in for a raster provider whose terms explicitly permit offline storage.
export const tileUrl = import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
export const offlineTilesAllowed = Capacitor.isNativePlatform() && import.meta.env.VITE_MAP_OFFLINE_TILES === 'true' && !/tile\.openstreetmap\.org/i.test(tileUrl)
export async function readTile(id:string):Promise<string|undefined> {
  if (!offlineTilesAllowed) return
  try { const result=await Filesystem.readFile({path:`map-tiles/${id}.png`,directory:Directory.Cache});return `data:image/png;base64,${result.data}` } catch { return }
}
export async function saveTile(id:string,blob:Blob) {
  if (!offlineTilesAllowed || blob.size>500000) return
  // Bounded cache: at most 256 viewed tiles, not a bulk-download area pack.
  try {
    const {files}=await Filesystem.readdir({path:'map-tiles',directory:Directory.Cache}).catch(()=>({files:[]}))
    if(files.length>=256)await Filesystem.deleteFile({path:`map-tiles/${files[0]!.name}`,directory:Directory.Cache})
    const data=await new Promise<string>((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(String(reader.result).split(',')[1]!);reader.onerror=reject;reader.readAsDataURL(blob)})
    await Filesystem.writeFile({path:`map-tiles/${id}.png`,data,directory:Directory.Cache,recursive:true})
  } catch { /* Cache failures never prevent the online map rendering. */ }
}
