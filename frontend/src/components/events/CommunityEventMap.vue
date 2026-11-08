<script setup lang="ts">
import {computed,onMounted,onBeforeUnmount,watch,useTemplateRef,ref,shallowRef} from 'vue'
import * as L from 'leaflet'
import {localTileLayer} from '../../native/tileLayer'
import 'leaflet/dist/leaflet.css'
import type {MapEvent,MapCategory,EventFrame} from '../../composables/useMapEvents'
import {apiImage} from '../../services/api'
import {NButton} from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'
const props=withDefaults(defineProps<{center:{lat:number;lng:number};events?:MapEvent[];categories?:MapCategory[];frames?:EventFrame[];selected?:number;pick?:boolean;fill?:boolean;radius?:number}>(),{events:()=>[],categories:()=>[],frames:()=>[],radius:0})
const emit=defineEmits<{select:[id:number];pick:[point:{lat:number;lng:number}]}>()
const container=useTemplateRef<HTMLDivElement>('canvas'),tileError=ref(false)
let map:L.Map|undefined,layer:L.LayerGroup|undefined,pin:L.Marker|undefined,observer:ResizeObserver|undefined
let origin:L.Marker|undefined,radiusCircle:L.Circle|undefined
const originContent = shallowRef<HTMLElement>()
const initialZoom = 16
const radarZoom = shallowRef(initialZoom)
// Keep the halo outside the avatar even at the widest zoom.
const radarScale = computed(() => 0.8 + 0.2 * 2 ** ((radarZoom.value - initialZoom) * 0.7))
const radarPlaying = shallowRef(false)
let radarObserver: IntersectionObserver | undefined
let originVisible = false
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches
const urls = new Map<string, string>()
const pendingPhotos = new Map<string, Promise<string | undefined>>()
const scope = new AbortController()
let generation = 0

function photoUrl(path: string) {
  const cached = urls.get(path)
  if (cached) return Promise.resolve(cached)
  const pending = pendingPhotos.get(path)
  if (pending) return pending

  const request = apiImage(path, scope.signal).then(async blob => {
    if (scope.signal.aborted) return
    const url = URL.createObjectURL(blob)
    try {
      const image = new Image()
      image.src = url
      await image.decode()
      if (scope.signal.aborted) return
      urls.set(path, url)
      return url
    } finally {
      if (!urls.has(path)) URL.revokeObjectURL(url)
    }
  }).catch(() => undefined).finally(() => pendingPhotos.delete(path))
  pendingPhotos.set(path, request)
  return request
}

async function setMarkerPhoto(event: MapEvent, element: HTMLElement, version: number) {
  const paths: string[] = []
  if (event.has_photo) paths.push(`/map-events/${event.id}/photo`)
  if (event.author.has_community_avatar) paths.push(`/neighbors/${event.author.id}/community-avatar`)
  if (event.author.avatar?.startsWith('/api/v1/demo/avatars/')) {
    paths.push(event.author.avatar.slice('/api/v1'.length))
  }

  for (const path of paths) {
    const url = await photoUrl(path)
    if (version !== generation || scope.signal.aborted) return
    if (!url) continue
    const image = document.createElement('img')
    image.alt = ''
    image.src = url
    element.replaceChildren(image)
    return
  }
}
function render(){
  if(!map||!layer)return
  const version=++generation;layer.clearLayers()
  for(const event of props.events){
    const category=props.categories.find(item=>item.id===event.category),frame=props.frames.find(item=>item.id===event.frame)
    const element=document.createElement('div');element.className='event-map-face';element.style.borderColor=category?.color||'#a992ee'
    if(frame){element.style.outline=`4px ${frame.style} ${frame.color}`;element.style.outlineOffset='3px'}
    const glyph=document.createElement('span');glyph.textContent=category?.emoji||'📍';element.append(glyph)
    void setMarkerPhoto(event, element, version)
    const badge=document.createElement('div');badge.className=event.category==='emergency'?'event-map-badge event-map-badge--emergency':'event-map-badge'
    const label=document.createElement('span');label.className='neighbor-pin-name event-pin-title';label.textContent=(event.category==='emergency'?'🚨 ':'')+event.title;badge.append(element,label)
    const distMeters = (props.center?.lat && props.center?.lng)
      ? Math.round(L.latLng(props.center).distanceTo([event.latitude, event.longitude]))
      : null
    const distText = distMeters !== null
      ? (distMeters < 1000 ? `${distMeters} m` : `${(distMeters / 1000).toFixed(1).replace('.0', '')} km`)
      : ''
    if (distText) {
      const distLabel = document.createElement('span')
      distLabel.className = 'event-pin-dist'
      distLabel.textContent = distText
      badge.append(distLabel)
    }
    const marker=L.marker([event.latitude,event.longitude],{icon:L.divIcon({html:badge,className:'event-map-pin',iconSize:[66,88],iconAnchor:[33,26]}),title:`${event.title} · ${category?.label}${distText ? ` · ${distText}` : ''}`,keyboard:true}).addTo(layer)
    marker.on('click',()=>emit('select',event.id));marker.getElement()?.setAttribute('aria-label',`Ver evento: ${event.title}`)
    marker.setZIndexOffset(event.id===props.selected?1000:event.category==='emergency'?500:0)
  }
}
function updateRadarPlayback() {
  radarPlaying.value = originVisible && !document.hidden
}
onMounted(()=>{
  if(!container.value)return
  map=L.map(container.value,{zoomControl:false,scrollWheelZoom:false,minZoom:3,zoomAnimation:!reducedMotion(),fadeAnimation:!reducedMotion(),markerZoomAnimation:!reducedMotion()}).setView(props.center,initialZoom);map.attributionControl.setPrefix(false)
  localTileLayer().on('tileerror',()=>{tileError.value=true}).addTo(map)
  layer=L.layerGroup().addTo(map)
  if (props.pick) {
    const icon = L.divIcon({
      html: '<svg viewBox="0 0 36 44" width="36" height="44" aria-hidden="true"><path d="M18 42C15 38 2 25 2 18a16 16 0 0 1 32 0c0 7-13 20-16 24Z" fill="currentColor" stroke="white" stroke-width="2"/><circle cx="18" cy="18" r="6" fill="white"/></svg>',
      className: 'event-location-marker', iconSize: [36, 44], iconAnchor: [18, 42],
    })
    pin = L.marker(props.center, { draggable: true, icon, title: 'Punto de encuentro' }).addTo(map)
    pin.on('dragend', () => { if (pin) emit('pick', pin.getLatLng()) })
    map.on('click', event => { pin?.setLatLng(event.latlng); emit('pick', event.latlng) })
  } else {
    const content = document.createElement('div')
    origin = L.marker(props.center, {
      icon: L.divIcon({ html: content, className: 'event-map-origin', iconSize: [46, 46], iconAnchor: [23, 23] }),
      keyboard: false, interactive: false, zIndexOffset: -100,
    }).addTo(map)
    originContent.value = content
    map.on('zoom', () => { radarZoom.value = map?.getZoom() ?? initialZoom })
    map.on('zoomanim', event => { radarZoom.value = event.zoom })
    radarObserver = new IntersectionObserver(([entry]) => {
      originVisible = entry?.isIntersecting ?? false
      updateRadarPlayback()
    })
    radarObserver.observe(content)
    document.addEventListener('visibilitychange', updateRadarPlayback, { signal: scope.signal })
    radiusCircle = L.circle(props.center, { radius: props.radius, color: '#bd9bcf', weight: 1, opacity: .45, fillColor: '#c3a7d5', fillOpacity: .045, dashArray: '5 8', interactive: false }).addTo(map)
  }
  void render();observer=new ResizeObserver(()=>map?.invalidateSize());observer.observe(container.value)
})
watch(()=>[props.events,props.categories,props.frames],()=>{void render()})
watch(()=>props.center,center=>{if(typeof center.lat!=='number'||typeof center.lng!=='number'||!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||Math.abs(center.lat)>90||Math.abs(center.lng)>180)return;pin?.setLatLng(center);origin?.setLatLng(center);radiusCircle?.setLatLng(center);map?.setView(center,map.getZoom(),{animate:!reducedMotion()})},{deep:true})
watch(()=>props.radius,radius=>radiusCircle?.setRadius(radius))
watch(()=>props.selected,id=>{const event=props.events.find(item=>item.id===id);if(event)map?.panInside([event.latitude,event.longitude],{paddingTopLeft:[40,70],paddingBottomRight:[55,map.getSize().y<360?43:205],animate:!reducedMotion()});void render()})
function recenter() {
  map?.setView(props.center, initialZoom, { animate: !reducedMotion() })
}
onBeforeUnmount(() => {
  generation++
  scope.abort()
  observer?.disconnect()
  radarObserver?.disconnect()
  urls.forEach(url => URL.revokeObjectURL(url))

  const currentMap = map
  if (!currentMap) return
  if (currentMap.getPane('mapPane')?.classList.contains('leaflet-zoom-anim')) {
    // Leaflet's zoom completion still needs the map pane during zoomend and moveend.
    currentMap.once('zoomend', () => queueMicrotask(() => currentMap.remove()))
  } else {
    currentMap.remove()
  }
})
</script>
<template>
  <div class="event-map-wrap" :class="{ 'event-map-fill': fill, 'event-map-picker': pick }">
    <div ref="canvas" class="event-map-canvas" role="region" :aria-label="pick ? 'Selecciona la ubicación del evento en el mapa' : 'Mapa comunitario de eventos'" />
    <div class="event-map-controls" role="group" aria-label="Controles del mapa">
      <NButton circle aria-label="Ampliar mapa" @click="map?.zoomIn()"><AppIcon name="plus" :size="20" /></NButton>
      <NButton circle aria-label="Reducir mapa" @click="map?.zoomOut()"><AppIcon name="minus" :size="20" /></NButton>
      <NButton circle :aria-label="pick ? 'Centrar en el punto de encuentro' : 'Volver a mi zona'" @click="recenter"><AppIcon name="locate" :size="20" /></NButton>
    </div>
    <Teleport v-if="originContent" :to="originContent">
      <div class="event-origin-avatar">
        <span class="event-origin-radar" :class="{ 'is-playing': radarPlaying }" :style="{ '--event-radar-scale': radarScale }" aria-hidden="true" />
        <CurrentUserAvatar :size="38" />
        <span class="event-origin-label">Tu zona</span>
      </div>
    </Teleport>
    <p v-if="tileError" class="map-hint map-tile-error" role="status">
      {{ pick ? 'No se cargaron algunas calles. Puedes elegir las coordenadas.' : 'No se cargaron algunas calles. Puedes usar la lista de eventos.' }}
    </p>
  </div>
</template>
<style>
.event-map-wrap { position: relative; isolation: isolate; }
.event-map-canvas {
  height: 400px;
  min-height: 280px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: #181d2e;
  z-index: 0;
  font-family: var(--font);
}
.event-map-badge { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.event-pin-title {
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding: 1px 6px;
  border-radius: 5px;
  color: var(--text);
  background: var(--surface);
  font-size: 10px;
  font-weight: 900;
  line-height: 1.5;
}
.event-pin-dist {
  padding: 1px 6px;
  border-radius: 4px;
  color: var(--lavender);
  background: var(--surface);
  font-size: 9px;
  font-weight: 900;
  line-height: 1.4;
  white-space: nowrap;
  box-shadow: 0 2px 6px #0006;
}
.event-map-face {
  position: relative;
  width: 52px;
  height: 52px;
  border: 4px solid;
  border-radius: 50%;
  background: var(--surface-raised);
  display: grid;
  place-items: center;
  box-shadow: 0 3px 14px #0008;
  overflow: hidden;
}
.event-map-face span { font-size: 25px; }
.leaflet-container .event-map-pin .event-map-face img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.event-map-pin:focus-visible .event-map-face { box-shadow: 0 0 0 3px var(--text); }
.event-map-badge--emergency .event-map-face {
  border-color: #ff617e !important;
  border-width: 5px;
  box-shadow: 0 0 0 5px #ff617e45, 0 0 20px #ff617e60, 0 4px 12px #0009;
}
.event-map-badge--emergency .event-pin-title {
  color: #fff0f3;
  background: #932a45;
  border: 1px solid #ff617e;
}
.event-map-pin:focus-visible .event-map-badge--emergency .event-map-face {
  box-shadow: 0 0 0 3px #fff, 0 0 0 7px #ff617e80;
}
.event-location-marker { color: var(--coral-strong); filter: drop-shadow(0 3px 5px #0008); }
.event-map-canvas .leaflet-tile-pane { filter: invert(1) hue-rotate(178deg) saturate(.48) brightness(.78) contrast(.94); }
.event-map-canvas .leaflet-control-attribution { padding: 2px 6px; color: var(--muted); background: var(--surface); font-size: 10px; }
.event-map-canvas .leaflet-control-attribution a { color: var(--lavender); }
.event-map-controls {
  position: absolute;
  top: 136px;
  right: 12px;
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.event-map-controls .n-button {
  width: 40px;
  height: 40px;
  border-radius: 13px;
  background: rgba(15, 23, 42, 0.92);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.6);
  color: #ffffff;
  transition: transform 0.15s ease, background 0.15s ease;
}
.event-map-controls .n-button:active {
  transform: scale(0.92);
  background: rgba(30, 41, 59, 1);
}
.event-map-controls .n-button:last-child {
  color: var(--coral, #ff6389);
  border-color: rgba(255, 99, 137, 0.35);
}
.event-map-origin { pointer-events: none; }
.event-origin-avatar {
  position: relative;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border: 2px solid var(--coral);
  border-radius: 50%;
  background: var(--surface);
  box-shadow: 0 3px 10px #0008;
}
.event-origin-radar,
.event-origin-radar::before,
.event-origin-radar::after {
  position: absolute;
  inset: -7px;
  border: 1px solid color-mix(in srgb, var(--coral) 52%, transparent);
  border-radius: 50%;
  pointer-events: none;
}
.event-origin-radar { transform: scale(var(--event-radar-scale, 1)); }
.leaflet-zoom-anim .event-origin-radar { transition: transform 250ms cubic-bezier(0, 0, .25, 1); }
.event-origin-radar::before,
.event-origin-radar::after {
  content: '';
  inset: 0;
  opacity: 0;
  animation: event-radar-pulse 2.4s ease-out infinite paused;
}
.event-origin-radar::after { animation-delay: -1.2s; }
.event-origin-radar.is-playing::before,
.event-origin-radar.is-playing::after { animation-play-state: running; }
.event-origin-label {
  position: absolute;
  top: calc(100% + 6px);
  padding: 2px 7px;
  border-radius: 5px;
  color: var(--text);
  background: var(--surface);
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
}
@keyframes event-radar-pulse {
  from { transform: scale(.95); opacity: .9; }
  to { transform: scale(1.9); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .leaflet-zoom-anim .event-origin-radar { transition: none; }
  .event-origin-radar::before, .event-origin-radar::after { animation: none; }
}
.event-map-fill { position: absolute; inset: 0; }
.event-map-fill .event-map-canvas { height: 100%; min-height: 0; border: 0; border-radius: 0; }
.event-map-picker .event-map-canvas { height: 300px; min-height: 0; }
.event-map-picker .event-map-controls { top: 11px; }
.map-tile-error {
  position: absolute;
  z-index: 1;
  top: 45px;
  left: 12px;
  right: 68px;
  width: fit-content;
  max-width: calc(100% - 80px);
  margin: 0;
  padding: 8px 11px;
  background: var(--surface-raised);
  border-radius: 9px;
  color: var(--muted);
  font-size: 0.75rem;
  line-height: 1.6;
}
.event-map-picker .map-tile-error { top: 11px; }
@media (min-width: 760px) { .event-map-picker .event-map-canvas { height: 350px; } }
</style>
