<script setup lang="ts">
import {computed,onMounted,onBeforeUnmount,ref,watch} from 'vue'
import {RouterLink, useRouter} from 'vue-router'
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
const formatTime=(value:string)=>new Date(value).toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'})
function formatEventSchedule(startsAt:string,endsAt:string):string{
  const s=new Date(startsAt),e=new Date(endsAt)
  const sDate=s.toLocaleDateString('es-PE',{day:'numeric',month:'short'}),eDate=e.toLocaleDateString('es-PE',{day:'numeric',month:'short'})
  const sTime=formatTime(startsAt),eTime=formatTime(endsAt)
  return sDate===eDate?`${sDate} · ${sTime} — ${eTime}`:`${sDate} ${sTime} — ${eDate} ${eTime}`
}
function initialsOf(name?:string):string{
  if(!name)return 'V'
  const parts=name.trim().split(/\s+/)
  return parts.length>=2?`${parts[0][0]}${parts[1][0]}`.toUpperCase():name.slice(0,2).toUpperCase()
}
const eventTiming=computed<'pending'|'active'|'ended'>(()=>{
  if(!active.value)return 'pending'
  const now=Date.now()
  if(now<Date.parse(active.value.starts_at))return 'pending'
  if(now>Date.parse(active.value.ends_at))return 'ended'
  return 'active'
})
function distanceMeters(event?:{latitude:number;longitude:number}|null):number|null{
  if(!event||!center.value?.lat||!center.value?.lng||typeof event.latitude!=='number'||typeof event.longitude!=='number')return null
  return Math.round(latLng(center.value).distanceTo([event.latitude,event.longitude]))
}
function formatDistance(event?:{latitude:number;longitude:number}|null):string{
  const meters=distanceMeters(event)
  if(meters===null)return ''
  if(meters<1000)return `a ${meters} m`
  const km=(meters/1000).toFixed(1).replace('.0','')
  return `a ${km} km`
}
const router = useRouter()
function openEvent(id:number){selected.value=id;actionError.value='';detailsOpen.value=true}
function editEvent(event:MapEvent){detailsOpen.value=false;void router.push({name:'edit-map-event',params:{id:event.id}})}
async function stopTracking(){tracking.value=false;try{await stopBackgroundLocation()}catch{actionError.value='No pudimos detener el servicio de ubicación. Intenta nuevamente.'}}
watch([category,search,radius],()=>{selected.value=undefined;detailsOpen.value=false})
async function run(event:MapEvent,kind:'join'|'cancel',method='POST'){if(busy.value)return;busy.value=true;actionError.value='';notice.value='';try{await action(event,kind,undefined,method);notice.value=kind==='cancel'?'Evento cancelado.':method==='DELETE'?'Inscripción cancelada.':'Estás inscrito. Valida tu asistencia cuando estés en el lugar.'}catch(e){actionError.value=e instanceof Error?e.message:'No pudimos completar la acción.'}finally{busy.value=false}}
async function locate(manual?:MapEvent){
  if(busy.value||document.hidden)return;busy.value=true;actionError.value='';notice.value=''
  try{
    if(manual){
      const now = Date.now()
      const start = Date.parse(manual.starts_at)
      const end = Date.parse(manual.ends_at)
      if(now < start){
        const startStr = new Date(manual.starts_at).toLocaleTimeString('es-PE', {hour: '2-digit', minute:'2-digit'})
        actionError.value = `El evento aún no comienza. Podrás validar tu asistencia a partir de las ${startStr}.`
        return
      }
      if(now > end){
        actionError.value = 'El evento ya ha finalizado. La asistencia solo se puede validar durante el horario del evento.'
        return
      }
    }
    const location=await currentPosition()
    if(Capacitor.isNativePlatform())await startBackgroundLocation()
    center.value={lat:location.latitude,lng:location.longitude}
    tracking.value=true
    if(manual && location.accuracy > 100){
      actionError.value = `Precisión GPS insuficiente (${Math.round(location.accuracy)} m). Se requiere 100 m o menos para registrar asistencia.`
      return
    }
    const now=Date.now()
    const candidates=manual?[manual]:events.value.filter(event=>event.joined&&!event.attended&&Date.parse(event.starts_at)<=now&&Date.parse(event.ends_at)>=now)
    for(const event of candidates){
      if(location.accuracy > 100) continue
      try{await action(event,'check-in',location);notice.value=`Asistencia registrada · +${event.points} puntos de empatía.`}catch(e){if(manual)throw e}
    }
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
      <button v-for="event in visible" :key="event.id" class="neighbor-result-row" :class="{'event-row--emergency':event.category==='emergency'}" type="button" @click="openEvent(event.id)"><span class="event-preview-glyph" :style="{borderColor:categoryOf(event)?.color}">{{categoryOf(event)?.emoji}}</span><span><strong>{{event.title}}</strong><small>{{date(event.starts_at)}} · {{event.place}} <strong v-if="formatDistance(event)" class="neighbor-distance-tag">· {{formatDistance(event)}}</strong></small><span class="neighbor-result-meta">{{categoryOf(event)?.label}} · +{{event.points}} P <span v-if="event.joined" class="neighbor-can-help">{{event.attended?'Asististe':'Inscrito'}}</span></span></span><AppIcon name="arrow" :size="18"/></button>
      <p v-if="!loading&&!visible.length" class="map-feedback">No hay eventos con esos filtros.</p><p v-if="hasMore" class="map-feedback">Se muestran hasta 200 eventos.</p>
    </div>
    <NDrawer v-model:show="detailsOpen" placement="bottom" height="min(90dvh, 700px)" class="app-bottom-drawer">
      <NDrawerContent v-if="active" :title="active.title" closable>
        <article class="event-details-card" :class="{'event-details--emergency': active.category === 'emergency'}">
          <!-- HERO BANNER O FOTO -->
          <div v-if="active.has_photo" class="event-hero-photo">
            <MapEventPhoto :key="active.id" :event-id="active.id" :title="active.title" />
          </div>
          <div
            v-else
            class="event-hero-banner"
            :style="{ '--cat-color': categoryOf(active)?.color || '#ff6b6b' }"
          >
            <div class="hero-emblem-badge">
              <span class="hero-emblem-emoji">{{ categoryOf(active)?.emoji }}</span>
            </div>
            <div class="hero-banner-content">
              <span class="hero-category-tag">{{ categoryOf(active)?.label }}</span>
              <strong class="hero-type-title">{{ active.type || 'Punto comunitario' }}</strong>
            </div>
            <span v-if="active.category === 'emergency'" class="hero-emergency-tag">
              🚨 Emergencia activa
            </span>
          </div>

          <!-- CHIPS STRIP -->
          <div class="event-chips-strip">
            <span class="event-chip chip-points">
              <AppIcon name="heart" :size="13" />
              +{{ active.points }} P empatía
            </span>
            <span class="event-chip chip-cost">
              {{ active.has_cost ? 'Con costo' : 'Sin costo' }}
            </span>
            <span v-if="active.participants > 0" class="event-chip chip-participants">
              <AppIcon name="users" :size="13" />
              {{ active.participants }} {{ active.participants === 1 ? 'inscrito' : 'inscritos' }}
            </span>
          </div>

          <!-- DESCRIPCIÓN -->
          <div v-if="active.description" class="event-card-desc">
            <p>{{ active.description }}</p>
          </div>

          <!-- CUADRANTE DE LOGÍSTICA -->
          <div class="event-logistics-quadrant">
            <div class="logistics-cell">
              <div class="logistics-cell-icon">
                <AppIcon name="clock" :size="16" />
              </div>
              <div class="logistics-cell-body">
                <span class="cell-label">Horario</span>
                <strong class="cell-value">{{ formatEventSchedule(active.starts_at, active.ends_at) }}</strong>
              </div>
            </div>
            <div class="logistics-cell">
              <div class="logistics-cell-icon">
                <AppIcon name="pin" :size="16" />
              </div>
              <div class="logistics-cell-body">
                <span class="cell-label">Punto de referencia</span>
                <strong class="cell-value">{{ active.place }}</strong>
                <small v-if="distanceMeters(active) !== null" class="cell-sub">
                  A {{ distanceMeters(active) }} m de tu punto actual
                </small>
              </div>
            </div>
          </div>

          <!-- ORGANIZADOR -->
          <div class="event-organizer-row">
            <div class="organizer-avatar">
              <img v-if="active.author.avatar" :src="active.author.avatar" :alt="active.author.name" />
              <span v-else>{{ initialsOf(active.author.name) }}</span>
            </div>
            <div class="organizer-info">
              <span class="organizer-role">Organizado por</span>
              <strong class="organizer-name">{{ active.author.name }}</strong>
            </div>
            <span v-if="active.is_owner" class="organizer-badge-owner">Tu evento</span>
          </div>

          <!-- ESTADO DINÁMICO Y ALERTAS -->
          <div v-if="active.attended" class="event-context-alert alert--attended">
            <span class="alert-icon">✓</span>
            <div class="alert-body">
              <strong>Asistencia confirmada</strong>
              <p>¡Gracias por participar! Se acreditaron +{{ active.points }} puntos de empatía a tu perfil.</p>
            </div>
          </div>

          <div v-else-if="eventTiming === 'ended'" class="event-context-alert alert--ended">
            <span class="alert-icon">⌛</span>
            <div class="alert-body">
              <strong>Evento concluido</strong>
              <p>Este evento finalizó a las {{ formatTime(active.ends_at) }}.</p>
            </div>
          </div>

          <div v-else-if="distanceMeters(active) !== null && distanceMeters(active)! > 100" class="event-context-alert alert--out-range">
            <span class="alert-icon">📍</span>
            <div class="alert-body">
              <strong>Estás a {{ distanceMeters(active) }} m del punto</strong>
              <p>Para registrar tu asistencia debes estar a 100 metros o menos durante el evento.</p>
            </div>
          </div>

          <!-- ERROR DE ACCIÓN -->
          <div v-if="actionError" class="event-context-alert alert--error" role="alert">
            <span class="alert-icon">⚠️</span>
            <div class="alert-body">
              <p>{{ actionError }}</p>
            </div>
          </div>

          <!-- ACCIONES DE ASISTENCIA -->
          <div class="event-primary-actions">
            <template v-if="!active.joined">
              <NButton
                type="primary"
                round
                class="btn-cta-submit"
                :disabled="busy || eventTiming === 'ended'"
                @click="run(active, 'join')"
              >
                Inscribirme al evento
              </NButton>
            </template>
            <template v-else-if="!active.attended">
              <NButton
                v-if="eventTiming === 'pending'"
                disabled
                round
                class="btn-cta-time-pending"
              >
                ⏳ Disponible a las {{ formatTime(active.starts_at) }}
              </NButton>
              <NButton
                v-else-if="eventTiming === 'active'"
                type="primary"
                round
                class="btn-cta-submit"
                :loading="busy"
                @click="locate(active)"
              >
                Validar mi asistencia
              </NButton>
              <NButton
                secondary
                round
                class="btn-cta-cancel-join"
                :disabled="busy"
                @click="run(active, 'join', 'DELETE')"
              >
                Cancelar inscripción
              </NButton>
            </template>
          </div>

          <!-- ACCIONES DEL CREADOR (MODIFICAR / CANCELAR) -->
          <div v-if="active.is_owner" class="event-creator-panel">
            <div class="creator-panel-header">
              <span>Herramientas del organizador</span>
            </div>
            <div class="creator-panel-buttons">
              <button
                type="button"
                class="btn-creator-action btn-creator-edit"
                :disabled="busy"
                @click="editEvent(active)"
              >
                <AppIcon name="edit" :size="16" /> Modificar evento
              </button>
              <button
                type="button"
                class="btn-creator-action btn-creator-cancel"
                :disabled="busy"
                @click="run(active, 'cancel'); detailsOpen = false"
              >
                <AppIcon name="close" :size="15" /> Cancelar evento
              </button>
            </div>
          </div>
        </article>
      </NDrawerContent>
    </NDrawer>
    <NDrawer v-model:show="rewardsOpen" placement="bottom" height="min(85dvh,640px)" class="app-bottom-drawer"><NDrawerContent title="Tus puntos de empatía" closable><section class="frames-gallery"><strong class="empathy-total">{{catalog.points}} puntos</strong><p>Inscribirte no suma puntos: asiste durante el evento a 100 m o menos y activa tu ubicación. Con el mapa abierto se comprueba tu asistencia.</p><NButton secondary round :loading="busy" @click="locate()">{{tracking?'Actualizar mi ubicación':'Activar ubicación'}}</NButton><h2>Tus próximos marcos</h2><p>Un nuevo marco cada 10 puntos, disponible al crear un evento.</p><div class="frames-grid"><div v-for="frame in catalog.frames" :key="frame.id" :class="{'frame-locked':!frame.unlocked}"><span :style="{borderColor:frame.color,borderStyle:frame.style}">✨</span><strong>{{frame.label}}</strong><small>{{frame.unlocked?'Desbloqueado':`${frame.points} puntos`}}</small></div></div></section></NDrawerContent></NDrawer>
  </section>
</template>
<style scoped>
.community-events-explorer { margin: 0; max-width: none; }
.event-category-chips { column-gap: 6px; flex-wrap: nowrap; overflow: auto; padding-bottom: 2px; }
.event-category-chips button { min-height: 44px; padding-inline: 7px; white-space: nowrap; cursor: pointer; flex-shrink: 0; }
.map-zone-button { cursor: pointer; font-family: inherit; }
.map-feedback { padding: 8px 14px; margin: 0; color: var(--muted); font-size: 12px; line-height: 1.5; }
.event-preview-glyph { display: grid; place-items: center; flex-shrink: 0; width: 46px; height: 46px; border: 3px solid; border-radius: 50%; background: #25283d; font-size: 22px; }
.event-preview-close { display: grid; place-items: center; align-self: flex-start; padding: 5px; border: 0; background: transparent; color: var(--muted); }
.event-map-preview h2 { font-size: 17px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.event-empty strong { font-size: 13px; }
.event-empty p, .frames-gallery p { color: var(--muted); font-size: 12px; line-height: 1.65; }
.event-empty a { display: inline-flex; align-items: center; gap: 6px; color: var(--coral); font-size: 12px; font-weight: 800; text-decoration: none; }
.event-row--emergency { margin-block: 6px; padding-inline: 10px; border: 1px solid #ff617e; border-left: 4px solid #ff617e; border-radius: 12px; background: #ff617e0d; }
.event-refresh { display: inline-flex; gap: 5px; align-items: center; background: none; border: 0; color: var(--lavender); font: inherit; }
.neighbor-distance-tag { color: var(--lavender); font-weight: 700; }
.empathy-total { font-size: 26px; color: var(--coral); }
.frames-gallery h2 { font-size: 18px; margin-top: 24px; }
.frames-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 20px 12px; margin: 24px 0; }
.frames-grid > div { display: grid; gap: 8px; text-align: center; justify-items: center; }
.frames-grid span { display: grid; place-items: center; width: 54px; height: 54px; border: 5px solid; border-radius: 50%; background: var(--surface-soft); font-size: 24px; }
.frames-grid strong { font-size: 12px; }
.frames-grid small { font-size: 11px; color: var(--muted); }
.frame-locked { opacity: .55; }

/* EVENT DETAILS CARD IN DRAWER */
.event-details-card {
  display: flex;
  flex-direction: column;
  padding-bottom: 24px;
}
.event-details--emergency {
  border-radius: 16px;
}

/* HERO BANNER */
.event-hero-banner {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 16px;
  background: radial-gradient(circle at 18% 50%, color-mix(in srgb, var(--cat-color) 20%, transparent) 0%, #171926 70%);
  border: 1px solid color-mix(in srgb, var(--cat-color) 35%, var(--line));
  margin-bottom: 14px;
  overflow: hidden;
}
.hero-emblem-badge {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: color-mix(in srgb, var(--cat-color) 18%, #1f2233);
  border: 1.5px solid color-mix(in srgb, var(--cat-color) 45%, transparent);
  box-shadow: 0 4px 16px color-mix(in srgb, var(--cat-color) 25%, transparent);
  flex-shrink: 0;
}
.hero-emblem-emoji { font-size: 26px; }
.hero-banner-content { display: flex; flex-direction: column; min-width: 0; }
.hero-category-tag { font-size: 11px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: var(--cat-color); }
.hero-type-title { font-size: 15px; font-weight: 800; color: #f1f3f9; margin-top: 2px; }
.hero-emergency-tag {
  position: absolute;
  top: 10px;
  right: 12px;
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: 9999px;
  font-size: 10.5px;
  font-weight: 800;
  background: #ff617e25;
  border: 1px solid #ff617e;
  color: #ff859d;
}
.event-hero-photo {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--line);
  margin-bottom: 14px;
  max-height: 220px;
}

/* CHIPS STRIP */
.event-chips-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 7px;
  margin-bottom: 14px;
}
.event-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 11.5px;
  font-weight: 700;
  line-height: 1.3;
  background: var(--surface-soft);
  border: 1px solid var(--line);
  color: var(--text);
}
.chip-points {
  color: #ff758c;
  background: #ff758c14;
  border-color: #ff758c35;
}
.chip-distance {
  color: var(--lavender);
  background: #25283d;
  font-weight: 800;
}
.chip-cost {
  color: #d1d5db;
}
.chip-participants {
  color: var(--lavender);
}

/* DESCRIPTION */
.event-card-desc {
  margin: 0 0 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-soft);
  border: 1px solid var(--line);
  color: #e2e5f1;
  font-size: 13.5px;
  line-height: 1.6;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.event-card-desc p { margin: 0; }

/* LOGISTICS QUADRANT */
.event-logistics-quadrant {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 14px;
  background: #191c2b;
  border: 1px solid var(--line);
  margin-bottom: 12px;
}
.logistics-cell {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.logistics-cell-icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: #23273c;
  color: var(--lavender);
  flex-shrink: 0;
  margin-top: 1px;
}
.logistics-cell-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.cell-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted);
  margin-bottom: 2px;
}
.cell-value {
  font-size: 13px;
  font-weight: 700;
  color: #f1f4fa;
  line-height: 1.4;
}
.cell-sub {
  font-size: 11px;
  color: var(--lavender);
  font-weight: 700;
  margin-top: 2px;
}

/* ORGANIZER ROW */
.event-organizer-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #171926;
  border: 1px solid var(--line);
  margin-bottom: 14px;
}
.organizer-avatar {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #2b2f48;
  color: #ff9cb0;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
  flex-shrink: 0;
}
.organizer-avatar img { width: 100%; height: 100%; object-fit: cover; }
.organizer-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.organizer-role { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
.organizer-name { font-size: 12.5px; font-weight: 800; color: #f1f3f9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.organizer-badge-owner {
  font-size: 10.5px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 6px;
  background: #ff6b6b20;
  color: #ff859d;
  border: 1px solid #ff6b6b40;
}

/* CONTEXTUAL ALERTS */
.event-context-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 13px;
  border-radius: 12px;
  margin-bottom: 14px;
  font-size: 12px;
  line-height: 1.5;
}
.alert-icon { font-size: 16px; line-height: 1.2; flex-shrink: 0; }
.alert-body strong { display: block; font-size: 12.5px; font-weight: 800; margin-bottom: 2px; }
.alert-body p { margin: 0; font-size: 11.5px; line-height: 1.45; }
.alert--attended { background: #16362d; border: 1px solid #34d39960; color: #6ee7b7; }
.alert--attended strong { color: #a7f3d0; }
.alert--pending { background: #182845; border: 1px solid #3b82f650; color: #93c5fd; }
.alert--pending strong { color: #bfdbfe; }
.alert--ended { background: #222638; border: 1px solid #475569; color: #cbd5e1; }
.alert--in-range { background: #133328; border: 1px solid #10b98160; color: #6ee7b7; }
.alert--in-range strong { color: #a7f3d0; }
.alert--out-range { background: #3b2816; border: 1px solid #f59e0b50; color: #fcd34d; }
.alert--out-range strong { color: #fef08a; }
.alert--error { background: #3c1922; border: 1px solid #f43f5e60; color: #fda4af; }

/* ACTIONS */
.event-primary-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 4px;
}
.btn-cta-submit {
  min-height: 48px;
  font-weight: 800;
  font-size: 14px;
  box-shadow: 0 4px 14px rgba(255, 97, 126, 0.25);
}
.btn-cta-time-pending {
  min-height: 46px;
  font-weight: 700;
  font-size: 13.5px;
  opacity: 0.85;
}
.btn-cta-cancel-join {
  min-height: 42px;
  font-size: 13px;
  font-weight: 700;
}

/* CREATOR MANAGEMENT */
.event-creator-panel {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px dashed var(--line);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.creator-panel-header {
  font-size: 10.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted);
}
.creator-panel-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.btn-creator-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 42px;
  padding: 8px 12px;
  border-radius: 12px;
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s ease;
}
.btn-creator-edit {
  background: #25283d;
  color: #e2e8f0;
  border: 1px solid #4a5075;
}
.btn-creator-edit:hover:not(:disabled) {
  background: #2e334d;
  border-color: #6366f1;
  color: #ffffff;
}
.btn-creator-cancel {
  background: #2a1b24;
  color: #ff9cb0;
  border: 1px solid #663242;
}
.btn-creator-cancel:hover:not(:disabled) {
  background: #381f2f;
  border-color: #ff617e;
  color: #ffb8c7;
}
.btn-creator-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
