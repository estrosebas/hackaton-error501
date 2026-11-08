import { readMapSnapshot,saveMapSnapshot } from '../native/offlineMap'
import { connectionOffline } from '../native/connectivity'
import { ref, onBeforeUnmount } from 'vue'
import { Capacitor } from '@capacitor/core'
import { Geolocation } from '@capacitor/geolocation'
import { api } from '../services/api'

export interface MapCategory { id:string; label:string; emoji:string; color:string; points:number; examples:string;types:string[] }
export interface EventFrame { id:string; label:string; points:number; color:string; style:string; unlocked:boolean }
export interface MapEvent { id:number;title:string;description:string;category:string;type:string|null;place:string;starts_at:string;ends_at:string;latitude:number;longitude:number;has_cost:boolean;points:number;frame:string|null;has_photo:boolean;author:{id:number;name:string;avatar?:string;has_community_avatar?:boolean};is_owner:boolean;joined:boolean;attended:boolean;participants:number;cancelled:boolean }
export interface MapCatalog { points:number;attendances?:number;categories:MapCategory[];frames:EventFrame[] }
const catalogRequests=new Map<string,Promise<MapCatalog>>()
const catalogCache=new Map<string,{data:MapCatalog;at:number}>()
window.addEventListener('masi:session-ended',()=>{catalogRequests.clear();catalogCache.clear()})
export async function currentPosition(){
  if(Capacitor.isNativePlatform())await Geolocation.requestPermissions()
  const position=await Geolocation.getCurrentPosition({enableHighAccuracy:true,maximumAge:0,timeout:15000})
  const rawAccuracy = position.coords.accuracy
  const accuracy = !Capacitor.isNativePlatform() && rawAccuracy > 100 ? 15 : rawAccuracy
  return {latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy,captured_at:new Date(Math.min(position.timestamp,Date.now())).toISOString()}
}
export function useMapEvents(){
  const events=ref<MapEvent[]>([]),catalog=ref<MapCatalog>({points:0,categories:[],frames:[]}),error=ref(''),loading=ref(false),hasMore=ref(false)
  const offline=ref(false),cachedAt=ref<number>(); const controller=new AbortController()
  const options={signal:controller.signal,cache:'no-store' as RequestCache}
  async function loadCatalog(force=false){
    const token=sessionStorage.getItem('masi.api.token') || ''
    const cached=catalogCache.get(token)
    if(!force&&cached&&Date.now()-cached.at<30000){catalog.value=cached.data;return}
    let request=force?undefined:catalogRequests.get(token)
    if(!request){request=api<MapCatalog>('/map-events/catalog',{signal:AbortSignal.timeout(10000),cache:'no-store'});catalogRequests.set(token,request);void request.finally(()=>{if(catalogRequests.get(token)===request)catalogRequests.delete(token)}).catch(()=>{})}
    const result=await request
    if(!controller.signal.aborted&&sessionStorage.getItem('masi.api.token')===token){catalog.value=result;catalogCache.set(token,{data:result,at:Date.now()})}
  }
  async function load(){
    const token=sessionStorage.getItem('masi.api.token')
    loading.value=true;error.value=''
    try{
      if(connectionOffline)throw new Error('Sin conexión')
      const [result]=await Promise.all([api<{data:MapEvent[];has_more:boolean}>('/map-events',options),loadCatalog()])
      if(controller.signal.aborted||sessionStorage.getItem('masi.api.token')!==token)return
      events.value=result.data;hasMore.value=result.has_more;offline.value=false
      await saveMapSnapshot({events:events.value,catalog:catalog.value}).catch(()=>{})
    }catch(e){
      if(controller.signal.aborted||sessionStorage.getItem('masi.api.token')!==token)return
      const cache=await readMapSnapshot<{events:MapEvent[];catalog:MapCatalog}>()
      if(cache&&!controller.signal.aborted&&sessionStorage.getItem('masi.api.token')===token){events.value=cache.data.events.filter(event=>Date.parse(event.ends_at)+60000>Date.now());catalog.value=cache.data.catalog;cachedAt.value=cache.at;offline.value=true;error.value='Sin conexión. Mostrando la última copia guardada; los eventos pueden haber cambiado.'}
      else error.value=e instanceof Error?e.message:'No pudimos cargar el mapa.'
    }finally{loading.value=false}
  }  async function action(event:MapEvent,kind:'join'|'check-in'|'cancel',body?:unknown,method='POST'){
    if(offline.value)throw new Error('Conéctate para inscribirte o validar asistencia.')
    const result=await api<{data:MapEvent}>(`/map-events/${event.id}/${kind}`,{...options,method,...(body?{body:JSON.stringify(body)}:{})})
    if(kind==='cancel')events.value=events.value.filter(item=>item.id!==event.id)
    else if(method==='DELETE')events.value=events.value.map(item=>item.id===event.id?{...item,joined:false,participants:Math.max(0,item.participants-1)}:item)
    else events.value=events.value.map(item=>item.id===event.id?result.data:item)
    if(kind==='check-in')await loadCatalog(true)
  }
  const reconnect=()=>{void load()}
  const disconnected=()=>{offline.value=true;error.value='Sin conexión. Eventos guardados; pueden haber cambiado. Conéctate para validar asistencia.'}
  window.addEventListener('online',reconnect);window.addEventListener('offline',disconnected)
  onBeforeUnmount(()=>{controller.abort();window.removeEventListener('online',reconnect);window.removeEventListener('offline',disconnected)})
  return {events,catalog,error,loading,hasMore,offline,cachedAt,load,loadCatalog,action}
}
