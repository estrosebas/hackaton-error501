<script setup lang="ts">
import {onMounted,onBeforeUnmount,watch,useTemplateRef,ref} from 'vue'
import * as L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type {MapEvent,MapCategory,EventFrame} from '../../composables/useMapEvents'
import {apiImage} from '../../services/api'
const props=withDefaults(defineProps<{center:{lat:number;lng:number};events?:MapEvent[];categories?:MapCategory[];frames?:EventFrame[];selected?:number;pick?:boolean}>(),{events:()=>[],categories:()=>[],frames:()=>[]})
const emit=defineEmits<{select:[id:number];pick:[point:{lat:number;lng:number}]}>()
const container=useTemplateRef<HTMLDivElement>('canvas'),tileError=ref(false)
let map:L.Map|undefined,layer:L.LayerGroup|undefined,pin:L.Marker|undefined,observer:ResizeObserver|undefined
const urls=new Map<number,string>(),scope=new AbortController();let generation=0
const pendingPhotos=new Map<number,Promise<string|undefined>>()
function photoUrl(id:number){
  if(urls.has(id))return Promise.resolve(urls.get(id))
  const existing=pendingPhotos.get(id);if(existing)return existing
  const request=apiImage(`/map-events/${id}/photo`,scope.signal).then(blob=>{if(scope.signal.aborted)return;const url=URL.createObjectURL(blob);urls.set(id,url);return url}).catch(()=>undefined).finally(()=>pendingPhotos.delete(id))
  pendingPhotos.set(id,request);return request
}
function render(){
  if(!map||!layer)return
  const version=++generation;layer.clearLayers()
  for(const event of props.events){
    const category=props.categories.find(item=>item.id===event.category),frame=props.frames.find(item=>item.id===event.frame)
    const element=document.createElement('div');element.className='event-map-face';element.style.borderColor=category?.color||'#a992ee'
    if(frame){element.style.outline=`4px ${frame.style} ${frame.color}`;element.style.outlineOffset='3px'}
    const glyph=document.createElement('span');glyph.textContent=category?.emoji||'📍';element.append(glyph)
    if(event.has_photo){
      void photoUrl(event.id).then(url=>{if(!url||version!==generation||scope.signal.aborted)return;const image=document.createElement('img');image.src=url;image.alt='';element.replaceChildren(image)})
    }
    const marker=L.marker([event.latitude,event.longitude],{icon:L.divIcon({html:element,className:'event-map-pin',iconSize:[52,52],iconAnchor:[26,26]}),title:`${event.title} · ${category?.label}`,keyboard:true}).addTo(layer)
    marker.on('click',()=>emit('select',event.id));marker.getElement()?.setAttribute('aria-label',`Ver evento: ${event.title}`)
    if(event.id===props.selected)marker.setZIndexOffset(1000)
  }
}
onMounted(()=>{
  if(!container.value)return
  map=L.map(container.value,{scrollWheelZoom:false,minZoom:3}).setView(props.center,15);map.attributionControl.setPrefix(false)
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).on('tileerror',()=>{tileError.value=true}).addTo(map)
  layer=L.layerGroup().addTo(map)
  if(props.pick){pin=L.marker(props.center,{draggable:true,icon:L.divIcon({html:'<span class="event-location-dot">📍</span>',className:'event-location-marker',iconSize:[40,40],iconAnchor:[20,36]})}).addTo(map);pin.on('dragend',()=>{if(pin)emit('pick',pin.getLatLng())});map.on('click',event=>{pin?.setLatLng(event.latlng);emit('pick',event.latlng)})}
  void render();observer=new ResizeObserver(()=>map?.invalidateSize());observer.observe(container.value)
})
watch(()=>[props.events,props.categories,props.frames],()=>{void render()})
watch(()=>props.center,center=>{if(typeof center.lat!=='number'||typeof center.lng!=='number'||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180)return;pin?.setLatLng(center);map?.setView(center,map.getZoom())},{deep:true})
watch(()=>props.selected,id=>{const event=props.events.find(item=>item.id===id);if(event)map?.panTo([event.latitude,event.longitude]);void render()})
onBeforeUnmount(()=>{generation++;scope.abort();observer?.disconnect();map?.remove();urls.forEach(url=>URL.revokeObjectURL(url))})
</script>
<template><div class="event-map-wrap"><div ref="canvas" class="event-map-canvas" role="region" :aria-label="pick?'Selecciona la ubicación del evento en el mapa':'Mapa comunitario de eventos'"/><p v-if="pick" class="map-hint">Toca el mapa o arrastra el pin para cambiar el lugar.</p><p v-if="tileError" class="map-hint">No se cargaron algunas calles. Puedes usar las coordenadas y la lista de eventos.</p></div></template>
<style>
.event-map-wrap{position:relative;isolation:isolate}.event-map-canvas{height:400px;min-height:280px;border:1px solid var(--line);border-radius:20px;background:var(--surface-soft);z-index:0}.event-map-face{width:52px;height:52px;border:4px solid;border-radius:50%;background:#1d2137;display:grid;place-items:center;box-shadow:0 3px 14px #0008;overflow:hidden}.event-map-face span{font-size:25px}.event-map-face img{width:100%;height:100%;object-fit:cover}.event-map-pin:focus-visible .event-map-face{box-shadow:0 0 0 4px #fff}.event-location-dot{font-size:34px}.map-hint{font-size:.75rem;color:var(--muted);line-height:1.6}.event-map-canvas .leaflet-tile-pane{filter:brightness(.7) saturate(.65)}.event-map-canvas .leaflet-control-attribution{font-size:9px}@media(min-width:760px){.event-map-canvas{height:500px}}
</style>
