<script setup lang="ts">
import {onMounted,onBeforeUnmount,watch,useTemplateRef,ref} from 'vue'
import * as L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type {MapEvent,MapCategory,EventFrame} from '../../composables/useMapEvents'
import {apiImage} from '../../services/api'
import {NButton} from 'naive-ui'
import AppIcon from '../AppIcon.vue'
const props=withDefaults(defineProps<{center:{lat:number;lng:number};events?:MapEvent[];categories?:MapCategory[];frames?:EventFrame[];selected?:number;pick?:boolean;fill?:boolean;radius?:number}>(),{events:()=>[],categories:()=>[],frames:()=>[],radius:0})
const emit=defineEmits<{select:[id:number];pick:[point:{lat:number;lng:number}]}>()
const container=useTemplateRef<HTMLDivElement>('canvas'),tileError=ref(false)
let map:L.Map|undefined,layer:L.LayerGroup|undefined,pin:L.Marker|undefined,observer:ResizeObserver|undefined
let origin:L.CircleMarker|undefined,radiusCircle:L.Circle|undefined
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
    const badge=document.createElement('div');badge.className='event-map-badge'
    const label=document.createElement('span');label.className='neighbor-pin-name event-pin-title';label.textContent=event.title;badge.append(element,label)
    const marker=L.marker([event.latitude,event.longitude],{icon:L.divIcon({html:badge,className:'event-map-pin',iconSize:[62,74],iconAnchor:[31,26]}),title:`${event.title} · ${category?.label}`,keyboard:true}).addTo(layer)
    marker.on('click',()=>emit('select',event.id));marker.getElement()?.setAttribute('aria-label',`Ver evento: ${event.title}`)
    if(event.id===props.selected)marker.setZIndexOffset(1000)
  }
}
onMounted(()=>{
  if(!container.value)return
  map=L.map(container.value,{zoomControl:false,scrollWheelZoom:false,minZoom:3}).setView(props.center,16);map.attributionControl.setPrefix(false)
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).on('tileerror',()=>{tileError.value=true}).addTo(map)
  layer=L.layerGroup().addTo(map)
  if(!props.pick){origin=L.circleMarker(props.center,{radius:8,color:'#eee8ff',weight:3,fillColor:'#b1a0ed',fillOpacity:1}).bindTooltip('Tu zona',{permanent:true,direction:'bottom',className:'event-origin-label'}).addTo(map);radiusCircle=L.circle(props.center,{radius:props.radius,color:'#bd9bcf',weight:1,opacity:.45,fillColor:'#c3a7d5',fillOpacity:.045,dashArray:'5 8',interactive:false}).addTo(map)}
  if(props.pick){pin=L.marker(props.center,{draggable:true,icon:L.divIcon({html:'<span class="event-location-dot">📍</span>',className:'event-location-marker',iconSize:[40,40],iconAnchor:[20,36]})}).addTo(map);pin.on('dragend',()=>{if(pin)emit('pick',pin.getLatLng())});map.on('click',event=>{pin?.setLatLng(event.latlng);emit('pick',event.latlng)})}
  void render();observer=new ResizeObserver(()=>map?.invalidateSize());observer.observe(container.value)
})
watch(()=>[props.events,props.categories,props.frames],()=>{void render()})
watch(()=>props.center,center=>{if(typeof center.lat!=='number'||typeof center.lng!=='number'||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180)return;pin?.setLatLng(center);origin?.setLatLng(center);radiusCircle?.setLatLng(center);map?.setView(center,map.getZoom())},{deep:true})
watch(()=>props.radius,radius=>radiusCircle?.setRadius(radius))
watch(()=>props.selected,id=>{const event=props.events.find(item=>item.id===id);if(event)map?.panInside([event.latitude,event.longitude],{paddingTopLeft:[40,70],paddingBottomRight:[55,map.getSize().y<360?43:205]});void render()})
onBeforeUnmount(()=>{generation++;scope.abort();observer?.disconnect();map?.remove();urls.forEach(url=>URL.revokeObjectURL(url))})
</script>
<template><div class="event-map-wrap" :class="{'event-map-fill':fill}"><div ref="canvas" class="event-map-canvas" role="region" :aria-label="pick?'Selecciona la ubicación del evento en el mapa':'Mapa comunitario de eventos'"/><div class="event-map-controls" aria-label="Controles del mapa"><NButton circle aria-label="Ampliar mapa" @click="map?.zoomIn()"><AppIcon name="plus" :size="20"/></NButton><NButton circle aria-label="Reducir mapa" @click="map?.zoomOut()"><AppIcon name="minus" :size="20"/></NButton><NButton circle aria-label="Volver a mi zona" @click="map?.setView(center,16)"><AppIcon name="locate" :size="20"/></NButton></div><p v-if="pick" class="map-hint">Toca el mapa o arrastra el pin para cambiar el lugar.</p><p v-if="tileError" class="map-hint map-tile-error">No se cargaron algunas calles. Puedes usar la lista de eventos.</p></div></template>
<style>
.event-map-wrap{position:relative;isolation:isolate}.event-map-canvas{height:400px;min-height:280px;border:1px solid var(--line);border-radius:20px;background:#181d2e;z-index:0;font-family:var(--font)}.event-map-badge{display:flex;flex-direction:column;align-items:center;gap:3px}.event-pin-title{max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:1px 6px;border-radius:5px;color:#f4f0ff;background:#15182be8;font-size:10px;font-weight:900;line-height:1.5}.event-map-face{width:52px;height:52px;border:4px solid;border-radius:50%;background:#1d2137;display:grid;place-items:center;box-shadow:0 3px 14px #0008;overflow:hidden}.event-map-face span{font-size:25px}.event-map-face img{width:100%;height:100%;object-fit:cover}.event-map-pin:focus-visible .event-map-badge{display:flex;flex-direction:column;align-items:center;gap:3px}.event-pin-title{max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:1px 6px;border-radius:5px;color:#f4f0ff;background:#15182be8;font-size:10px;font-weight:900;line-height:1.5}.event-map-face{box-shadow:0 0 0 4px #fff}.event-location-dot{font-size:34px}.map-hint{font-size:.75rem;color:var(--muted);line-height:1.6}.event-map-canvas .leaflet-tile-pane{filter:invert(1) hue-rotate(178deg) saturate(.48) brightness(.78) contrast(.94)}.event-map-canvas .leaflet-control-attribution{padding:2px 6px;color:#d0cbe6;background:#15182be8;font-size:10px}.event-map-canvas .leaflet-control-attribution a{color:#dfd3fa}.event-map-controls{position:absolute;top:49px;right:11px;z-index:850;display:grid;gap:7px}.event-map-controls .n-button{width:44px;height:44px;color:var(--text);background:#1e233be8}.event-map-controls .n-button:last-child{margin-top:5px;color:var(--coral)}.event-origin-label{padding:2px 6px;border:0;border-radius:5px;color:#eee9ff;background:#24213ae8;font-size:10px;font-weight:900;box-shadow:none}.event-origin-label:before{display:none}.event-map-fill{position:absolute;inset:0}.event-map-fill .event-map-canvas{height:100%;min-height:0;border:0;border-radius:0}.map-tile-error{position:absolute;z-index:850;top:45px;left:12px;right:68px;width:fit-content;max-width:calc(100% - 80px);padding:8px 11px;background:#1f243be8;border-radius:9px;font-size:11px}@media(min-width:760px){.event-map-canvas{height:500px}}@media(max-height:760px){.event-map-fill{position:relative;height:clamp(275px,45dvh,345px)}}
</style>
