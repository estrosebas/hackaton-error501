<script setup lang="ts">
import {computed,onMounted,onBeforeUnmount,ref,watch} from 'vue'
import {RouterLink} from 'vue-router'
import {NButton,NInput,NSelect,NDrawer,NDrawerContent} from 'naive-ui'
import {latLng} from 'leaflet'
import AppIcon from '../components/AppIcon.vue'
import '../components/neighbors/nearby-neighbors.css'
import CommunityEventMap from '../components/events/CommunityEventMap.vue'
import MapNeighborhoodAvatars from '../components/events/MapNeighborhoodAvatars.vue'
import MapEventPhoto from '../components/events/MapEventPhoto.vue'
import {useMapEvents,currentPosition,type MapEvent} from '../composables/useMapEvents'
import {Capacitor} from '@capacitor/core'
import {backgroundTracking,backgroundError,backgroundPosition,startBackgroundLocation,stopBackgroundLocation} from '../native/backgroundLocation'
import {currentUser} from '../composables/useAuth'
const {events,catalog,error,loading,hasMore,load,action}=useMapEvents()
const category=ref(''),selected=ref<number>(),busy=ref(false),actionError=ref(''),notice=ref(''),tracking=ref(false)
const search=ref(''),radius=ref(0),mode=ref<'map'|'list'>('map'),detailsOpen=ref(false),rewardsOpen=ref(false)
const radiusOptions=[{label:'Todo',value:0},{label:'300 m',value:300},{label:'600 m',value:600},{label:'1 km',value:1000}]
const center=ref({lat:Number(currentUser.value?.latitude)||-12.0464,lng:Number(currentUser.value?.longitude)||-77.0428})
watch(backgroundPosition,position=>{if(position)center.value=position})
const visible=computed(()=>events.value.filter(event=>(!category.value||event.category===category.value)&&(!search.value||`${event.title} ${event.description} ${event.place}`.toLocaleLowerCase('es').includes(search.value.toLocaleLowerCase('es')))&&(!radius.value||latLng(center.value).distanceTo([event.latitude,event.longitude])<=radius.value)))
const active=computed(()=>events.value.find(event=>event.id===selected.value))
const categoryOf=(event:MapEvent)=>catalog.value.categories.find(item=>item.id===event.category)
const date=(value:string)=>new Date(value).toLocaleString('es-PE',{dateStyle:'medium',timeStyle:'short'})
function openEvent(id:number){selected.value=id;actionError.value='';detailsOpen.value=true}
async function stopTracking(){tracking.value=false;try{await stopBackgroundLocation()}catch{actionError.value='No pudimos detener el servicio de ubicación. Intenta nuevamente.'}}
watch([category,search,radius],()=>{selected.value=undefined;detailsOpen.value=false})
async function run(event:MapEvent,kind:'join'|'cancel',method='POST'){if(busy.value)return;busy.value=true;actionError.value='';notice.value='';try{await action(event,kind,undefined,method);notice.value=kind==='cancel'?'Evento cancelado.':method==='DELETE'?'Inscripción cancelada.':'Estás inscrito. Valida tu asistencia cuando estés en el lugar.'}catch(e){actionError.value=e instanceof Error?e.message:'No pudimos completar la acción.'}finally{busy.value=false}}
async function locate(manual?:MapEvent){
  if(busy.value||document.hidden)return;busy.value=true;actionError.value='';notice.value=''
  try{const location=await currentPosition();if(Capacitor.isNativePlatform())await startBackgroundLocation();center.value={lat:location.latitude,lng:location.longitude};tracking.value=true
    const now=Date.now(),candidates=manual?[manual]:events.value.filter(event=>event.joined&&!event.attended&&Date.parse(event.starts_at)<=now&&Date.parse(event.ends_at)>=now)
    for(const event of candidates){try{await action(event,'check-in',location);notice.value=`Asistencia registrada · +${event.points} puntos de empatía.`}catch(e){if(manual)throw e}}
    if(!candidates.length)notice.value='Ubicación activada. Se verificará tu asistencia a eventos inscritos mientras este mapa esté abierto.'
  }catch(e){actionError.value=e instanceof Error?e.message:'Activa el permiso de ubicación para validar tu asistencia.'}finally{busy.value=false}
}
let timer:ReturnType<typeof setInterval>|undefined
onMounted(()=>{void load();timer=setInterval(()=>{if(tracking.value)void locate()},45000)})
watch(()=>currentUser.value?.ubigeo,()=>{selected.value=undefined;tracking.value=false;void load()})
onBeforeUnmount(()=>clearInterval(timer))
</script>
<template>
  <section class="nearby-page community-events-explorer">
    <header class="neighbor-tools">
      <div class="neighbor-tools-heading">
        <div><h1>Mapa comunitario</h1><button class="neighbor-area-button" type="button" @click="rewardsOpen=true"><AppIcon name="heart" :size="13"/>{{catalog.points}} puntos de empatía<AppIcon name="chevron" :size="13"/></button></div>
        <div class="neighbor-view-switch" role="group" aria-label="Vista de eventos">
          <button type="button" :aria-pressed="mode==='map'" :class="{selected:mode==='map'}" aria-label="Ver mapa" @click="mode='map'"><AppIcon name="map" :size="19"/></button>
          <button type="button" :aria-pressed="mode==='list'" :class="{selected:mode==='list'}" aria-label="Ver lista" @click="mode='list'"><AppIcon name="list" :size="19"/></button>
        </div>
      </div>
      <div class="neighbor-search-row"><NInput v-model:value="search" clearable placeholder="Buscar eventos" :input-props="{'aria-label':'Buscar eventos por nombre o lugar'}"><template #prefix><AppIcon name="search" :size="17"/></template></NInput><NSelect v-model:value="radius" class="neighbor-radius" :options="radiusOptions" aria-label="Distancia máxima"/></div>
      <div class="neighbor-filter-chips event-category-chips" role="group" aria-label="Tipos de eventos">
        <button type="button" :aria-pressed="!category" :class="{ selected: !category }" @click="category = ''">Todos</button>
        <button v-for="item in catalog.categories" :key="item.id" type="button" :aria-pressed="category === item.id" :class="{ selected: category === item.id }" @click="category = item.id">
          <span aria-hidden="true">{{ item.emoji }}</span>{{ item.id === 'community' ? 'Comunitarios' : item.label }}
        </button>
      </div>
      <MapNeighborhoodAvatars v-if="mode === 'map'" />
    </header>
    <div v-if="backgroundTracking" class="map-feedback">Ubicación en segundo plano activa <button type="button" class="text-link" @click="stopTracking()">Detener</button></div><p v-if="backgroundError" class="map-feedback" role="status">{{backgroundError}}</p><p v-if="error||actionError" role="alert" class="map-feedback">{{error||actionError}}</p><p v-if="notice" role="status" class="map-feedback">{{notice}}</p>
    <div v-if="mode==='map'" class="neighbor-map-stage">
      <CommunityEventMap fill :center="center" :radius="radius" :events="visible" :categories="catalog.categories" :frames="catalog.frames" :selected="selected" @select="openEvent"/>
      <button class="map-demo-label map-zone-button" type="button" :disabled="busy" @click="locate()"><AppIcon name="locate" :size="11"/>{{tracking?'Ubicación activa':'Activar ubicación'}}</button>
      <div class="neighbor-map-count" role="status"><span/>{{visible.length}} {{visible.length===1?'evento':'eventos'}} en tu zona</div>
      <span v-if="loading" class="map-load-state" role="status">Cargando eventos del barrio…</span>
      <div v-if="!loading&&!visible.length" class="neighbor-map-empty event-empty"><strong>No hay eventos con esos filtros</strong><p>Una buena idea puede empezar contigo.</p><RouterLink :to="{name:'create'}">Crear en el mapa <AppIcon name="plus" :size="14"/></RouterLink></div>
    </div>
    <div v-else class="neighbor-results-list">
      <div class="neighbor-results-heading"><span>{{visible.length}} eventos en tu zona</span><button class="event-refresh" type="button" :disabled="loading" @click="load"><AppIcon name="refresh" :size="14"/> Actualizar</button></div>
      <button v-for="event in visible" :key="event.id" class="neighbor-result-row" :class="{'event-row--emergency':event.category==='emergency'}" type="button" @click="openEvent(event.id)"><span class="event-preview-glyph" :style="{borderColor:categoryOf(event)?.color}">{{categoryOf(event)?.emoji}}</span><span><strong>{{event.title}}</strong><small>{{date(event.starts_at)}} · {{event.place}}</small><span class="neighbor-result-meta">{{categoryOf(event)?.label}} · +{{event.points}} P <span v-if="event.joined" class="neighbor-can-help">{{event.attended?'Asististe':'Inscrito'}}</span></span></span><AppIcon name="arrow" :size="18"/></button>
      <p v-if="!loading&&!visible.length" class="map-feedback">No hay eventos con esos filtros.</p><p v-if="hasMore" class="map-feedback">Se muestran hasta 200 eventos.</p>
    </div>
    <NDrawer v-model:show="detailsOpen" placement="bottom" height="min(85dvh,640px)" class="app-bottom-drawer"><NDrawerContent v-if="active" :title="active.title" closable><article class="event-details" :class="{'event-details--emergency':active.category==='emergency'}"><MapEventPhoto v-if="active.has_photo" :key="active.id" :event-id="active.id" :title="active.title"/><div v-else class="event-detail-placeholder" aria-hidden="true" :style="{color:categoryOf(active)?.color}">{{categoryOf(active)?.emoji}}</div><p v-if="active.category==='emergency'" class="event-emergency-label"><span aria-hidden="true">🚨</span> Emergencia vecinal</p><p :style="{color:categoryOf(active)?.color}">{{categoryOf(active)?.emoji}} {{categoryOf(active)?.label}} · +{{active.points}} P</p><p class="event-description">{{active.description}}</p><p>📍 {{active.place}}</p><p>{{date(active.starts_at)}} — {{date(active.ends_at)}}</p><p>{{active.has_cost?'Con costo':'Sin costo'}} · {{active.participants}} inscritos · Por {{active.author.name}}</p><p v-if="active.attended" class="event-attended">✓ Asistencia validada · {{active.points}} puntos acreditados</p><p v-if="actionError" role="alert">{{actionError}}</p><div class="event-actions"><NButton v-if="!active.joined" type="primary" round :disabled="busy" @click="run(active,'join')">Inscribirme</NButton><template v-else-if="!active.attended"><NButton type="primary" round :disabled="busy" @click="locate(active)">Validar mi asistencia</NButton><NButton secondary round :disabled="busy" @click="run(active,'join','DELETE')">Cancelar inscripción</NButton></template><NButton v-if="active.is_owner" secondary round :disabled="busy" @click="run(active,'cancel');detailsOpen=false">Cancelar evento</NButton></div></article></NDrawerContent></NDrawer>
    <NDrawer v-model:show="rewardsOpen" placement="bottom" height="min(85dvh,640px)" class="app-bottom-drawer"><NDrawerContent title="Tus puntos de empatía" closable><section class="frames-gallery"><strong class="empathy-total">{{catalog.points}} puntos</strong><p>Inscribirte no suma puntos: asiste durante el evento a 100 m o menos y activa tu ubicación. Con el mapa abierto se comprueba tu asistencia.</p><NButton secondary round :loading="busy" @click="locate()">{{tracking?'Actualizar mi ubicación':'Activar ubicación'}}</NButton><h2>Tus próximos marcos</h2><p>Un nuevo marco cada 10 puntos, disponible al crear un evento.</p><div class="frames-grid"><div v-for="frame in catalog.frames" :key="frame.id" :class="{'frame-locked':!frame.unlocked}"><span :style="{borderColor:frame.color,borderStyle:frame.style}">✨</span><strong>{{frame.label}}</strong><small>{{frame.unlocked?'Desbloqueado':`${frame.points} puntos`}}</small></div></div></section></NDrawerContent></NDrawer>
  </section>
</template>
<style scoped>
.community-events-explorer{margin:0;max-width:none}
.event-category-chips { column-gap: 6px; flex-wrap: nowrap; overflow: auto; padding-bottom: 2px; }
.event-category-chips button { min-height: 44px; padding-inline: 7px; white-space: nowrap; cursor: pointer; flex-shrink: 0; }
.map-zone-button{cursor:pointer;font-family:inherit}.map-feedback{padding:8px 14px;margin:0;color:var(--muted);font-size:12px;line-height:1.5}.event-preview-glyph{display:grid;place-items:center;flex-shrink:0;width:46px;height:46px;border:3px solid;border-radius:50%;background:#25283d;font-size:22px}.event-preview-close{display:grid;place-items:center;align-self:flex-start;padding:5px;border:0;background:transparent;color:var(--muted)}.event-map-preview h2{font-size:17px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.event-empty strong{font-size:13px}.event-empty p,.event-details p,.frames-gallery p{color:var(--muted);font-size:12px;line-height:1.65}.event-empty a{display:inline-flex;align-items:center;gap:6px;color:var(--coral);font-size:12px;font-weight:800;text-decoration:none}.event-detail-placeholder{display:grid;place-items:center;min-height:160px;margin-bottom:18px;border:1px solid var(--line);border-radius:16px;background:var(--surface-soft);font-size:54px}.event-row--emergency{margin-block:6px;padding-inline:10px;border:1px solid #ff617e;border-left:4px solid #ff617e;border-radius:12px;background:#ff617e0d}.event-details--emergency{padding:14px;border:2px solid #ff617e;border-radius:16px;background:#ff617e08}.event-emergency-label{display:flex;align-items:center;gap:7px;color:#ff9cb0!important;font-weight:900}.event-description{white-space:pre-wrap;overflow-wrap:anywhere}.event-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}.event-attended{color:#65cfb0!important}.event-refresh{display:inline-flex;gap:5px;align-items:center;background:none;border:0;color:var(--lavender);font:inherit}.empathy-total{font-size:26px;color:var(--coral)}.frames-gallery h2{font-size:18px;margin-top:24px}.frames-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:20px 12px;margin:24px 0}.frames-grid>div{display:grid;gap:8px;text-align:center;justify-items:center}.frames-grid span{display:grid;place-items:center;width:54px;height:54px;border:5px solid;border-radius:50%;background:var(--surface-soft);font-size:24px}.frames-grid strong{font-size:12px}.frames-grid small{font-size:11px;color:var(--muted)}.frame-locked{opacity:.55}
</style>
