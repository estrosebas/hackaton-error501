import { ref, onBeforeUnmount } from 'vue'
import { Capacitor } from '@capacitor/core'
import { Geolocation } from '@capacitor/geolocation'
import { api } from '../services/api'

export interface MapCategory { id:string; label:string; emoji:string; color:string; points:number; examples:string;types:string[] }
export interface EventFrame { id:string; label:string; points:number; color:string; style:string; unlocked:boolean }
export interface MapEvent { id:number;title:string;description:string;category:string;type:string|null;place:string;starts_at:string;ends_at:string;latitude:number;longitude:number;has_cost:boolean;points:number;frame:string|null;has_photo:boolean;author:{id:number;name:string};is_owner:boolean;joined:boolean;attended:boolean;participants:number;cancelled:boolean }
export interface MapCatalog { points:number;categories:MapCategory[];frames:EventFrame[] }
export async function currentPosition(){
  if(Capacitor.isNativePlatform())await Geolocation.requestPermissions()
  const position=await Geolocation.getCurrentPosition({enableHighAccuracy:true,maximumAge:0,timeout:15000})
  return {latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy:position.coords.accuracy,captured_at:new Date(Math.min(position.timestamp,Date.now())).toISOString()}
}
export function useMapEvents(){
  const events=ref<MapEvent[]>([]),catalog=ref<MapCatalog>({points:0,categories:[],frames:[]}),error=ref(''),loading=ref(false),hasMore=ref(false)
  const controller=new AbortController()
  const options={signal:controller.signal,cache:'no-store' as RequestCache}
  async function loadCatalog(){catalog.value=await api<MapCatalog>('/map-events/catalog',options)}
  async function load(){loading.value=true;error.value='';try{const [result]=await Promise.all([api<{data:MapEvent[];has_more:boolean}>('/map-events',options),loadCatalog()]);events.value=result.data;hasMore.value=result.has_more}catch(e){if(!controller.signal.aborted)error.value=e instanceof Error?e.message:'No pudimos cargar el mapa.'}finally{loading.value=false}}
  async function action(event:MapEvent,kind:'join'|'check-in'|'cancel',body?:unknown,method='POST'){
    const result=await api<{data:MapEvent}>(`/map-events/${event.id}/${kind}`,{...options,method,...(body?{body:JSON.stringify(body)}:{})})
    if(kind==='cancel')events.value=events.value.filter(item=>item.id!==event.id)
    else if(method==='DELETE')events.value=events.value.map(item=>item.id===event.id?{...item,joined:false,participants:Math.max(0,item.participants-1)}:item)
    else events.value=events.value.map(item=>item.id===event.id?result.data:item)
    if(kind==='check-in')await loadCatalog()
  }
  onBeforeUnmount(()=>controller.abort())
  return {events,catalog,error,loading,hasMore,load,loadCatalog,action}
}
