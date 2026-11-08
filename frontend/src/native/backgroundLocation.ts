import { Capacitor, CapacitorHttp, registerPlugin } from '@capacitor/core'
import type { BackgroundGeolocationPlugin } from '@capacitor-community/background-geolocation'
import { LocalNotifications } from '@capacitor/local-notifications'
import { ref } from 'vue'
import { clearSession } from '../services/api'
import { clearMapSnapshots } from './offlineMap'

const plugin=registerPlugin<BackgroundGeolocationPlugin>('BackgroundGeolocation')
export const backgroundTracking=ref(false),backgroundError=ref('')
export const backgroundPosition=ref<{lat:number;lng:number}>()
let watcher:string|undefined,starting:Promise<void>|undefined,lastSent=0,busy=false,generation=0
export async function stopBackgroundLocation() {
  generation++;backgroundTracking.value=false;backgroundPosition.value=undefined
  const id=watcher;watcher=undefined
  if(id)await plugin.removeWatcher({id})
}
export function startBackgroundLocation():Promise<void> {
  if(!Capacitor.isNativePlatform())return Promise.resolve()
  if(starting)return starting
  if(watcher)return Promise.resolve()
  starting=(async()=>{
    const epoch=generation,token=sessionStorage.getItem('masi.api.token')
    const base=String(import.meta.env.VITE_API_BASE_URL||'').replace(/\/$/,'')
    if(!token||!/^https:\/\//.test(base))throw new Error('Android necesita VITE_API_BASE_URL con una API HTTPS accesible.')
    const permission=await LocalNotifications.requestPermissions()
    if(permission.display!=='granted')throw new Error('Permite las notificaciones para activar ubicación en segundo plano.')
    backgroundError.value=''
    const id=await plugin.addWatcher({backgroundTitle:'masi · Ubicación activa',backgroundMessage:'Validando asistencia a tus eventos. Puedes detenerlo desde el mapa.',requestPermissions:true,stale:false,distanceFilter:0},async(position,error)=>{
      if(error){backgroundError.value=error.message;return}
      if(!position||epoch!==generation||busy||position.simulated||!position.time||position.accuracy>100||Date.now()-position.time>120000||position.time>Date.now()+5000||Date.now()-lastSent<30000||sessionStorage.getItem('masi.api.token')!==token)return
      busy=true;lastSent=Date.now()
      backgroundPosition.value={lat:position.latitude,lng:position.longitude}
      const headers={Authorization:`Bearer ${token}`,Accept:'application/json','Content-Type':'application/json'}
      try{
        const result=await CapacitorHttp.get({url:`${base}/map-events`,headers,connectTimeout:15000,readTimeout:15000})
        if(result.status===401){clearSession();return}
        if(result.status!==200||epoch!==generation)return
        for(const event of result.data.data||[]){
          if(epoch!==generation||sessionStorage.getItem('masi.api.token')!==token)break
          if(!event.joined||event.attended||Date.parse(event.starts_at)>position.time||Date.parse(event.ends_at)<Date.now())continue
          const radians=Math.PI/180,dy=(event.latitude-position.latitude)*radians,dx=(event.longitude-position.longitude)*radians
          const a=Math.sin(dy/2)**2+Math.cos(position.latitude*radians)*Math.cos(event.latitude*radians)*Math.sin(dx/2)**2
          if(6371000*2*Math.atan2(Math.sqrt(a),Math.sqrt(Math.max(0,1-a)))>100)continue
          const check=await CapacitorHttp.post({url:`${base}/map-events/${event.id}/check-in`,headers,data:{latitude:position.latitude,longitude:position.longitude,accuracy:position.accuracy,captured_at:new Date(Math.min(position.time,Date.now())).toISOString()},connectTimeout:15000,readTimeout:15000})
          if(check.status===401){clearSession();break}
        }
      }catch{backgroundError.value='Sin conexión: la asistencia requiere validación online reciente.'}finally{busy=false}
    })
    if(epoch!==generation){await plugin.removeWatcher({id});return}
    watcher=id;backgroundTracking.value=true
  })().finally(()=>{starting=undefined})
  return starting
}
window.addEventListener('masi:session-ended',()=>{void stopBackgroundLocation().catch(()=>{});void clearMapSnapshots()})
