<script setup lang="ts">
import {computed,onMounted,onBeforeUnmount,ref,watch} from 'vue'
import {RouterLink, useRouter, useRoute} from 'vue-router'
import {NButton,NInput,NDrawer,NDrawerContent} from 'naive-ui'
import {latLng} from 'leaflet'
import AppIcon from '../components/AppIcon.vue'
import '../components/neighbors/nearby-neighbors.css'
import CommunityEventMap from '../components/events/CommunityEventMap.vue'
import MapEventPhoto from '../components/events/MapEventPhoto.vue'
import {useMapEvents,currentPosition,type MapEvent} from '../composables/useMapEvents'
import {Capacitor} from '@capacitor/core'
import {backgroundTracking,backgroundError,backgroundPosition,startBackgroundLocation,stopBackgroundLocation} from '../native/backgroundLocation'
import {currentUser} from '../composables/useAuth'
const {events,catalog,error,loading,hasMore,load,action}=useMapEvents()
const category=ref(''),selected=ref<number>(),busy=ref(false),actionError=ref(''),notice=ref(''),tracking=ref(false)
const search=ref(''),radius=ref(300),mode=ref<'map'|'list'>('map'),detailsOpen=ref(false),rewardsOpen=ref(false)
const filtersOpen=ref(false),emptyDismissed=ref(false),emptyCollapsed=ref(false)
const radiusOptions = [
  { label: '300 m', value: 300 },
  { label: '600 m', value: 600 },
  { label: '1 km', value: 1000 },
  { label: 'Todo mi distrito', value: 0 },
]
function selectCategory(catId: string) {
  category.value = catId
  filtersOpen.value = false
}
function countForCategory(catId: string): number {
  if (!catId) return events.value.length
  return events.value.filter(e => e.category === catId).length
}
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
  if(now>Date.parse(active.value.ends_at)+60000)return 'ended'
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
const route = useRoute()
watch([()=>route.query.event,events],()=>{
  const event=events.value.find(item=>item.id===Number(route.query.event))
  if(event){center.value={lat:event.latitude,lng:event.longitude};selected.value=event.id;detailsOpen.value=true;actionError.value=''}
},{immediate:true})
function openEvent(id:number){selected.value=id;actionError.value='';detailsOpen.value=true}
function editEvent(event:MapEvent){detailsOpen.value=false;void router.push({name:'edit-map-event',params:{id:event.id}})}
async function stopTracking(){tracking.value=false;try{await stopBackgroundLocation()}catch{actionError.value='No pudimos detener el servicio de ubicación. Intenta nuevamente.'}}
watch([category,search,radius],()=>{selected.value=undefined;detailsOpen.value=false;emptyDismissed.value=false})
async function run(event:MapEvent,kind:'join'|'cancel',method='POST'){
  if(busy.value)return
  if(kind==='join' && event.is_owner){
    actionError.value='Como creador del evento no necesitas inscribirte ni recibes puntos de asistencia.'
    return
  }
  busy.value=true;actionError.value='';notice.value=''
  try{await action(event,kind,undefined,method);notice.value=kind==='cancel'?'Evento cancelado.':method==='DELETE'?'Inscripción cancelada.':'Estás inscrito. Valida tu asistencia cuando estés en el lugar.'}catch(e){actionError.value=e instanceof Error?e.message:'No pudimos completar la acción.'}finally{busy.value=false}
}
async function locate(manual?:MapEvent){
  if(busy.value||document.hidden)return;busy.value=true;actionError.value='';notice.value=''
  try{
    if(manual){
      if(manual.is_owner){
        actionError.value='Como creador del evento no necesitas registrar asistencia.'
        return
      }
      const now = Date.now()
      const start = Date.parse(manual.starts_at)
      const end = Date.parse(manual.ends_at) + 60000
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
    const candidates=manual?[manual]:events.value.filter(event=>!event.is_owner&&event.joined&&!event.attended&&Date.parse(event.starts_at)<=now&&Date.parse(event.ends_at)+60000>=now)
    for(const event of candidates){
      if(location.accuracy > 100) continue
      try{await action(event,'check-in',location);notice.value=event.points?`Asistencia registrada · +${event.points} puntos de empatía.`:'Asistencia registrada · el apoyo con costo no suma puntos.'}catch(e){if(manual)throw e}
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
  <section class="nearby-page community-events-explorer" :class="{ 'community-events-explorer--map': mode === 'map' }">
    <!-- MODO MAPA: Experiencia Inmersiva de Pantalla Completa con Controles Flotantes Glassmorphism -->
    <div v-if="mode === 'map'" class="neighbor-map-stage neighbor-map-stage--full">
      <CommunityEventMap
        fill
        :center="center"
        :radius="radius"
        :events="visible"
        :categories="catalog.categories"
        :frames="catalog.frames"
        :selected="selected"
        @select="openEvent"
      />

      <!-- Header Flotante Superior -->
      <header class="map-floating-header">
        <!-- Fila Superior: Título + Puntos de empatía + Conmutador Mapa/Lista -->
        <div class="map-floating-topbar">
          <div class="map-floating-title-box">
            <h1 class="map-floating-title">Mapa comunitario</h1>
            <button class="map-floating-points-pill" type="button" @click="rewardsOpen = true">
              <AppIcon name="heart" :size="13" />
              <span>{{ catalog.points }} P empatía</span>
              <AppIcon name="chevron" :size="11" />
            </button>
          </div>
          <div class="map-floating-view-switch" role="group" aria-label="Vista de eventos">
            <button
              type="button"
              :class="{ active: mode === 'map' }"
              aria-label="Ver mapa"
              @click="mode = 'map'"
            >
              <AppIcon name="map" :size="17" />
            </button>
            <button
              type="button"
              aria-label="Ver lista"
              @click="mode = 'list'"
            >
              <AppIcon name="list" :size="17" />
            </button>
          </div>
        </div>

        <!-- Cápsula Compacta de Búsqueda y Radio -->
        <div class="map-search-capsule glass-panel">
          <div class="map-search-input-wrapper">
            <AppIcon name="search" :size="15" class="map-search-icon" />
            <input
              v-model="search"
              type="text"
              placeholder="Buscar eventos…"
              class="map-search-input"
              aria-label="Buscar eventos por nombre o lugar"
            />
            <button
              v-if="search"
              type="button"
              class="map-search-clear"
              aria-label="Limpiar búsqueda"
              @click="search = ''"
            >
              <AppIcon name="close" :size="13" />
            </button>
          </div>

          <!-- Selector de Radio Nativo (Sin truncamiento) -->
          <div class="map-radius-container">
            <select
              v-model.number="radius"
              class="map-radius-native-select"
              aria-label="Distancia máxima"
            >
              <option v-for="opt in radiusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <AppIcon name="chevron" :size="10" class="map-radius-arrow" />
          </div>

          <!-- Botón Desplegable de Filtros -->
          <button
            type="button"
            class="map-filter-toggle-btn"
            :class="{ active: filtersOpen || category }"
            aria-label="Filtrar por categorías"
            @click="filtersOpen = !filtersOpen"
          >
            <AppIcon name="sliders" :size="15" />
            <span v-if="category" class="map-filter-badge">1</span>
          </button>
        </div>

        <!-- Panel Flotante de Categorías -->
        <Transition name="fade-slide">
          <div v-if="filtersOpen" class="map-categories-overlay glass-panel">
            <div class="map-categories-overlay-header">
              <span>Filtrar categorías</span>
              <button
                v-if="category"
                type="button"
                class="map-clear-category-btn"
                @click="selectCategory('')"
              >
                Restablecer
              </button>
            </div>
            <div class="map-categories-grid">
              <button
                type="button"
                class="category-grid-card"
                :class="{ selected: !category }"
                @click="selectCategory('')"
              >
                <div class="category-card-left">
                  <span class="category-card-emoji">🌟</span>
                  <span class="category-card-label">Todos</span>
                </div>
                <span class="category-card-count">{{ events.length }}</span>
              </button>
              <button
                v-for="item in catalog.categories"
                :key="item.id"
                type="button"
                class="category-grid-card"
                :class="{ selected: category === item.id }"
                :style="{ '--cat-accent': item.color || '#ff6389' }"
                @click="selectCategory(item.id)"
              >
                <div class="category-card-left">
                  <span class="category-card-emoji">{{ item.emoji }}</span>
                  <span class="category-card-label">{{ item.id === 'community' ? 'Comunitarios' : item.label }}</span>
                </div>
                <span class="category-card-count">{{ countForCategory(item.id) }}</span>
              </button>
            </div>
          </div>
        </Transition>
      </header>

      <!-- Radar Live Status Pill -->
      <div class="map-radar-status-wrapper">
        <button
          class="map-radar-pill icon-btn-contrast"
          type="button"
          :disabled="busy"
          @click="locate()"
          :title="tracking ? 'Ubicación activa · Toca para actualizar' : 'Toca para activar ubicación GPS'"
        >
          <span class="radar-ping-dot">
            <span class="radar-pulse-ring" />
            <span class="radar-core-dot" />
          </span>
          <span class="map-radar-text">{{ visible.length }} {{ visible.length === 1 ? 'evento' : 'eventos' }} · {{radius ? (radius < 1000 ? `${radius} m` : `${radius / 1000} km`) : 'Todo mi distrito'}} · {{events.length}} en el distrito</span>
        </button>
      </div>

      <!-- Feedback / Notificaciones Flotantes -->
      <div v-if="notice || error || actionError || backgroundTracking" class="map-floating-feedback-stack">
        <p v-if="notice" class="map-floating-feedback-pill pill-notice" role="status">
          {{ notice }}
        </p>
        <p v-if="error || actionError" class="map-floating-feedback-pill pill-error" role="alert">
          {{ error || actionError }}
        </p>
        <div v-if="backgroundTracking" class="map-floating-feedback-pill pill-tracking">
          <span>GPS en segundo plano activo</span>
          <button type="button" class="btn-tracking-stop" @click="stopTracking()">Detener</button>
        </div>
      </div>

      <!-- Indicador flotante de carga -->
      <div v-if="loading" class="map-floating-loading-pill icon-btn-contrast" role="status">
        <AppIcon name="refresh" :size="13" class="spin-icon" />
        <span>Cargando eventos…</span>
      </div>

      <!-- Aviso Flotante Reducible y Ocultable cuando no hay eventos con los filtros -->
      <Transition name="fade-slide">
        <div v-if="!loading && !visible.length && !emptyDismissed" class="map-floating-empty-wrapper">
          <!-- Modo Mini-Píldora Ultra Compacta -->
          <div v-if="emptyCollapsed" class="map-empty-mini-pill icon-btn-contrast">
            <span class="mini-pill-tag">📍 Sin eventos</span>
            <RouterLink :to="{ name: 'create' }" class="mini-pill-btn-cta">
              <AppIcon name="plus" :size="12" />
              <span>Crear</span>
            </RouterLink>
            <button
              type="button"
              class="mini-pill-icon-btn"
              aria-label="Expandir aviso"
              title="Expandir aviso"
              @click="emptyCollapsed = false"
            >
              <AppIcon name="chevron" :size="12" class="icon-expand-up" />
            </button>
            <button
              type="button"
              class="mini-pill-icon-btn"
              aria-label="Ocultar aviso"
              title="Ocultar aviso"
              @click="emptyDismissed = true"
            >
              <AppIcon name="close" :size="11" />
            </button>
          </div>

          <!-- Modo Compacto con botones para reducir o cerrar -->
          <div v-else class="map-floating-empty-card glass-panel">
            <div class="empty-card-top-row">
              <div class="empty-card-heading">
                <span class="empty-card-icon">📍</span>
                <strong class="empty-card-title">No hay eventos con esos filtros</strong>
              </div>
              <div class="empty-card-controls">
                <button
                  type="button"
                  class="empty-card-ctrl-btn"
                  aria-label="Reducir aviso a píldora"
                  title="Reducir tamaño"
                  @click="emptyCollapsed = true"
                >
                  <AppIcon name="minus" :size="12" />
                </button>
                <button
                  type="button"
                  class="empty-card-ctrl-btn"
                  aria-label="Ocultar aviso"
                  title="Ocultar aviso"
                  @click="emptyDismissed = true"
                >
                  <AppIcon name="close" :size="12" />
                </button>
              </div>
            </div>

            <p class="empty-card-desc">
              Una buena idea puede empezar contigo. Organiza o reporta algo en tu zona.
            </p>

            <RouterLink :to="{ name: 'create' }" class="empty-card-cta-btn">
              <AppIcon name="plus" :size="13" />
              <span>Crear en el mapa</span>
            </RouterLink>
          </div>
        </div>
      </Transition>
    </div>

    <!-- MODO LISTA: Navegación clásica y scrollable de resultados -->
    <div v-else class="community-events-list-wrapper">
      <header class="neighbor-tools">
        <div class="neighbor-tools-heading">
          <div>
            <h1>Mapa comunitario</h1>
            <button class="neighbor-area-button" type="button" @click="rewardsOpen = true">
              <AppIcon name="heart" :size="13" />{{ catalog.points }} puntos de empatía<AppIcon name="chevron" :size="13" />
            </button>
          </div>
          <div class="neighbor-view-switch" role="group" aria-label="Vista de eventos">
            <button type="button" aria-label="Ver mapa" @click="mode = 'map'">
              <AppIcon name="map" :size="19" />
            </button>
            <button type="button" :class="{ selected: mode === 'list' }" aria-label="Ver lista" @click="mode = 'list'">
              <AppIcon name="list" :size="19" />
            </button>
          </div>
        </div>
        <div class="neighbor-search-row">
          <NInput
            v-model:value="search"
            clearable
            placeholder="Buscar eventos"
            :input-props="{ 'aria-label': 'Buscar eventos por nombre o lugar' }"
          >
            <template #prefix><AppIcon name="search" :size="17" /></template>
          </NInput>
          <div class="map-radius-container">
            <select
              v-model.number="radius"
              class="map-radius-native-select"
              aria-label="Distancia máxima"
            >
              <option v-for="opt in radiusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <AppIcon name="chevron" :size="10" class="map-radius-arrow" />
          </div>
        </div>
        <div class="neighbor-filter-chips event-category-chips" role="group" aria-label="Tipos de eventos">
          <button type="button" :class="{ selected: !category }" @click="category = ''">
            Todos ({{ events.length }})
          </button>
          <button
            v-for="item in catalog.categories"
            :key="item.id"
            type="button"
            :class="{ selected: category === item.id }"
            @click="category = item.id"
          >
            <span aria-hidden="true">{{ item.emoji }}</span>{{ item.id === 'community' ? 'Comunitarios' : item.label }} ({{ countForCategory(item.id) }})
          </button>
        </div>
      </header>

      <div v-if="backgroundTracking" class="map-feedback">
        Ubicación en segundo plano activa <button type="button" class="text-link" @click="stopTracking()">Detener</button>
      </div>
      <p v-if="backgroundError" class="map-feedback" role="status">{{ backgroundError }}</p>
      <p v-if="error || actionError" role="alert" class="map-feedback">{{ error || actionError }}</p>
      <p v-if="notice" role="status" class="map-feedback">{{ notice }}</p>

      <div class="neighbor-results-list">
        <div class="neighbor-results-heading">
          <span>{{ visible.length }} eventos en tu zona</span>
          <button class="event-refresh" type="button" :disabled="loading" @click="load">
            <AppIcon name="refresh" :size="14" /> Actualizar
          </button>
        </div>
        <button
          v-for="event in visible"
          :key="event.id"
          class="neighbor-result-row"
          :class="{ 'event-row--emergency': event.category === 'emergency' }"
          type="button"
          @click="openEvent(event.id)"
        >
          <span class="event-preview-glyph" :style="{ borderColor: categoryOf(event)?.color }">
            {{ categoryOf(event)?.emoji }}
          </span>
          <span>
            <strong>{{ event.title }}</strong>
            <small>{{ date(event.starts_at) }} · {{ event.place }} <strong v-if="formatDistance(event)" class="neighbor-distance-tag">· {{ formatDistance(event) }}</strong></small>
            <span class="neighbor-result-meta">
              {{ categoryOf(event)?.label }} · {{event.points ? `+${event.points} P` : 'Sin puntos · apoyo con costo'}}
              <span v-if="event.is_owner" class="neighbor-can-help">Tu evento</span>
              <span v-else-if="event.joined" class="neighbor-can-help">{{ event.attended ? 'Asististe' : 'Inscrito' }}</span>
            </span>
          </span>
          <AppIcon name="arrow" :size="18" />
        </button>
        <p v-if="!loading && !visible.length" class="map-feedback">No hay eventos con esos filtros.</p>
        <p v-if="hasMore" class="map-feedback">Se muestran hasta 200 eventos.</p>
      </div>
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
              {{active.points ? `+${active.points} P empatía` : 'Sin puntos · apoyo con costo'}}
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
          <template v-if="!active.is_owner">
            <div v-if="active.attended" class="event-context-alert alert--attended">
              <span class="alert-icon">✓</span>
              <div class="alert-body">
                <strong>Asistencia confirmada</strong>
                <p>{{active.points ? `¡Gracias por participar! Se acreditaron +${active.points} puntos de empatía a tu perfil.` : 'Asistencia validada. El apoyo con costo no otorga puntos de empatía.'}}</p>
              </div>
            </div>

            <div v-else-if="eventTiming === 'pending'" class="event-context-alert alert--pending">
              <span class="alert-icon">⏳</span>
              <div class="alert-body">
                <strong>El evento aún no inicia</strong>
                <p>Inicia a las {{ formatTime(active.starts_at) }}. Podrás validar tu asistencia durante el horario del evento.</p>
              </div>
            </div>

            <div v-else-if="eventTiming === 'ended'" class="event-context-alert alert--ended">
              <span class="alert-icon">⌛</span>
              <div class="alert-body">
                <strong>Evento concluido</strong>
                <p>Este evento finalizó a las {{ formatTime(active.ends_at) }}.</p>
              </div>
            </div>

            <div v-else-if="distanceMeters(active) !== null && distanceMeters(active)! <= 100" class="event-context-alert alert--in-range">
              <span class="alert-icon">🎯</span>
              <div class="alert-body">
                <strong>Estás en el lugar (a {{ distanceMeters(active) }} m)</strong>
                <p>Estás dentro del radio de 100 m. Ya puedes validar tu asistencia.</p>
              </div>
            </div>

            <div v-else-if="distanceMeters(active) !== null && distanceMeters(active)! > 100" class="event-context-alert alert--out-range">
              <span class="alert-icon">📍</span>
              <div class="alert-body">
                <strong>Estás a {{ distanceMeters(active) }} m del punto</strong>
                <p>Para registrar tu asistencia debes estar a 100 metros o menos durante el evento.</p>
              </div>
            </div>
          </template>

          <template v-else>
            <!-- ESTADO PARA EL CREADOR -->
            <div v-if="eventTiming === 'ended'" class="event-context-alert alert--ended">
              <span class="alert-icon">⌛</span>
              <div class="alert-body">
                <strong>Tu evento ha concluido</strong>
                <p>Este evento finalizó a las {{ formatTime(active.ends_at) }}. Hubo {{ active.participants }} personas inscritas.</p>
              </div>
            </div>
            <div v-else-if="eventTiming === 'pending'" class="event-context-alert alert--pending">
              <span class="alert-icon">⏳</span>
              <div class="alert-body">
                <strong>Tu evento está programado</strong>
                <p>Inicia a las {{ formatTime(active.starts_at) }}. Actualmente tienes {{ active.participants }} inscritos.</p>
              </div>
            </div>
            <div v-else class="event-context-alert alert--in-range">
              <span class="alert-icon">✨</span>
              <div class="alert-body">
                <strong>Tu evento está en curso</strong>
                <p>Finaliza a las {{ formatTime(active.ends_at) }}. {{active.points ? `Los asistentes inscritos sumarán +${active.points} puntos al validar asistencia.` : 'Este apoyo con costo no otorga puntos de empatía.'}}</p>
              </div>
            </div>
          </template>

          <!-- ERROR DE ACCIÓN -->
          <div v-if="actionError" class="event-context-alert alert--error" role="alert">
            <span class="alert-icon">⚠️</span>
            <div class="alert-body">
              <p>{{ actionError }}</p>
            </div>
          </div>

          <!-- ACCIONES DE ASISTENCIA (SOLO PARA ASISTENTES) -->
          <div v-if="!active.is_owner" class="event-primary-actions">
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

          <!-- AVISO INFORMATIVO PARA EL CREADOR -->
          <div v-else class="event-owner-host-notice">
            <span class="host-notice-icon">📢</span>
            <div class="host-notice-text">
              <strong>Eres el organizador de este evento</strong>
              <p>Los creadores coordinan el encuentro y no requieren inscribirse ni reciben puntos de asistencia.</p>
            </div>
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
/* Glassmorphism & High-Contrast Capsules */
.glass-panel {
  background: rgba(15, 23, 42, 0.94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.65);
}

.icon-btn-contrast {
  background: rgba(15, 23, 42, 0.94);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.55);
  color: #ffffff;
}

/* Map Mode Full-Bleed Layout */
.community-events-explorer--map {
  height: calc(100dvh - 160px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) !important;
  min-height: 480px;
  position: relative !important;
  overflow: hidden !important;
  display: flex !important;
  flex-direction: column !important;
  padding: 0 !important;
  margin: 0 !important;
  border: 0 !important;
}

.neighbor-map-stage--full {
  position: absolute !important;
  inset: 0 !important;
  width: 100% !important;
  height: 100% !important;
  min-height: 0 !important;
  overflow: hidden !important;
  background: #090b15;
}

/* Floating Header */
.map-floating-header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 20;
  padding: 12px 12px 6px;
  pointer-events: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.map-floating-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  pointer-events: auto;
}

.map-floating-title-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.map-floating-title {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
  color: #ffffff;
  letter-spacing: -0.4px;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
}

.map-floating-points-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.92);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 99, 137, 0.35);
  color: #ff9cb7;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
  transition: transform 0.15s ease;
}
.map-floating-points-pill:active {
  transform: scale(0.95);
}

.map-floating-view-switch {
  display: flex;
  gap: 3px;
  padding: 3px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.92);
  backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
}

.map-floating-view-switch button {
  display: grid;
  place-items: center;
  width: 36px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  color: #a0a7c5;
  background: transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}
.map-floating-view-switch button.active {
  color: #ffffff;
  background: #3c365e;
}

/* Floating Search Capsule */
.map-search-capsule {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  border-radius: 16px;
  pointer-events: auto;
}

.map-search-input-wrapper {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  min-width: 0;
}

.map-search-icon {
  position: absolute;
  left: 9px;
  color: #a0a7c5;
  pointer-events: none;
}

.map-search-input {
  width: 100%;
  height: 36px;
  padding: 0 28px 0 30px;
  border-radius: 11px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(10, 14, 28, 0.85);
  color: #ffffff;
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 600;
  outline: none;
  transition: border-color 0.15s ease;
}
.map-search-input::placeholder {
  color: rgba(255, 255, 255, 0.55);
}
.map-search-input:focus {
  border-color: #ff6389;
  box-shadow: 0 0 0 2px rgba(255, 99, 137, 0.25);
}

.map-search-clear {
  position: absolute;
  right: 6px;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border: 0;
  background: transparent;
  color: #a0a7c5;
  cursor: pointer;
}

/* Native High-Contrast Radius Select */
.map-radius-container {
  position: relative;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.map-radius-native-select {
  appearance: none;
  -webkit-appearance: none;
  height: 36px;
  padding: 0 26px 0 10px;
  border-radius: 11px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  background: rgba(10, 14, 28, 0.95);
  color: #ffffff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  outline: none;
  white-space: nowrap;
}
.map-radius-native-select:focus {
  border-color: #ff6389;
}
.map-radius-native-select option {
  background: #121629;
  color: #ffffff;
  font-weight: 700;
  padding: 6px 10px;
}

.map-radius-arrow {
  position: absolute;
  right: 8px;
  color: #a0a7c5;
  pointer-events: none;
}

/* Filter Toggle Button */
.map-filter-toggle-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(10, 14, 28, 0.85);
  color: #ffffff;
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}
.map-filter-toggle-btn:active {
  transform: scale(0.94);
}
.map-filter-toggle-btn.active {
  border-color: #ff6389;
  color: #ff6389;
  background: rgba(255, 99, 137, 0.15);
}

.map-filter-badge {
  position: absolute;
  top: -3px;
  right: -3px;
  min-width: 15px;
  height: 15px;
  padding: 0 3px;
  border-radius: 999px;
  background: #ff4a75;
  color: #ffffff;
  font-size: 9px;
  font-weight: 900;
  display: grid;
  place-items: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.5);
}

/* Floating Category Overlay */
.map-categories-overlay {
  padding: 12px;
  border-radius: 18px;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 2px;
}

.map-categories-overlay-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 6px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  font-size: 11.5px;
  font-weight: 800;
  color: #ffffff;
}

.map-clear-category-btn {
  background: none;
  border: 0;
  color: #ff9cb7;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.map-categories-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.category-grid-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(10, 14, 28, 0.88);
  color: #ffffff;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.15s ease;
}
.category-grid-card:active {
  transform: scale(0.96);
}
.category-grid-card.selected {
  border-color: var(--cat-accent, #ff4a75);
  background: var(--cat-accent, #ff4a75);
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}
.category-grid-card.selected .category-card-count {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

.category-card-left {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
}

.category-card-emoji {
  font-size: 14px;
}

.category-card-label {
  font-size: 11.5px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.category-card-count {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.1);
  color: #a0a7c5;
}

/* Radar Pulse Indicator Pill */
.map-radar-status-wrapper {
  position: absolute;
  top: 136px;
  left: 12px;
  z-index: 10;
  pointer-events: auto;
}

.map-radar-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  cursor: pointer;
  font-family: inherit;
  font-size: 11px;
  font-weight: 700;
  transition: transform 0.15s ease;
}
.map-radar-pill:active {
  transform: scale(0.94);
}

.radar-ping-dot {
  position: relative;
  display: flex;
  width: 8px;
  height: 8px;
}

.radar-pulse-ring {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #34d399;
  animation: radar-ping 2.5s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.radar-core-dot {
  position: relative;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #10b981;
}

@keyframes radar-ping {
  0% { transform: scale(0.95); opacity: 0.85; }
  50% { transform: scale(2.2); opacity: 0; }
  100% { transform: scale(0.95); opacity: 0; }
}

/* Floating Feedback & Loading Pills */
.map-floating-feedback-stack {
  position: absolute;
  bottom: 84px;
  left: 14px;
  right: 14px;
  z-index: 15;
  display: flex;
  flex-direction: column;
  gap: 6px;
  pointer-events: none;
}

.map-floating-feedback-pill {
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
  pointer-events: auto;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(12px);
}
.pill-notice {
  background: rgba(30, 41, 59, 0.95);
  color: #a7f3d0;
  border: 1px solid rgba(52, 211, 153, 0.4);
}
.pill-error {
  background: rgba(69, 10, 10, 0.95);
  color: #fca5a5;
  border: 1px solid rgba(248, 113, 113, 0.4);
}
.pill-tracking {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(15, 23, 42, 0.95);
  color: #c7d2fe;
  border: 1px solid rgba(99, 102, 241, 0.4);
}
.btn-tracking-stop {
  border: 0;
  background: transparent;
  color: #ff6389;
  font-weight: 800;
  font-size: 11px;
  cursor: pointer;
}

.map-floating-loading-pill {
  position: absolute;
  top: 180px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 15;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}
.spin-icon {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  100% { transform: rotate(360deg); }
}

/* Floating Empty State Card Wrapper */
.map-floating-empty-wrapper {
  position: absolute;
  bottom: 14px;
  left: 12px;
  right: 12px;
  max-width: 360px;
  margin-inline: auto;
  z-index: 15;
  pointer-events: auto;
}

/* Modo Compacto */
.map-floating-empty-card {
  border-radius: 16px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.empty-card-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.empty-card-heading {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.empty-card-icon {
  font-size: 15px;
  line-height: 1;
}

.empty-card-title {
  font-size: 12px;
  font-weight: 800;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.empty-card-controls {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.empty-card-ctrl-btn {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.08);
  color: #a0a7c5;
  cursor: pointer;
  transition: all 0.15s ease;
}
.empty-card-ctrl-btn:hover {
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
}
.empty-card-ctrl-btn:active {
  transform: scale(0.92);
}

.empty-card-desc {
  margin: 0;
  font-size: 11px;
  color: #a0a7c5;
  line-height: 1.35;
}

.empty-card-cta-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ff4a75, #e03e63);
  color: #ffffff;
  font-size: 12px;
  font-weight: 800;
  text-decoration: none;
  box-shadow: 0 3px 10px rgba(255, 74, 117, 0.3);
  transition: transform 0.15s ease, opacity 0.15s ease;
}
.empty-card-cta-btn:active {
  transform: scale(0.97);
}

/* Modo Mini-Píldora Ultra Compacta */
.map-empty-mini-pill {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 4px 6px 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  width: fit-content;
  max-width: 100%;
  margin-inline: auto;
}

.mini-pill-tag {
  color: #ffffff;
  white-space: nowrap;
  font-size: 11px;
}

.mini-pill-btn-cta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 3px 8px;
  border-radius: 999px;
  background: linear-gradient(135deg, #ff4a75, #e03e63);
  color: #ffffff;
  font-size: 10.5px;
  font-weight: 800;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 2px 6px rgba(255, 74, 117, 0.35);
}

.mini-pill-icon-btn {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 0;
  background: rgba(255, 255, 255, 0.12);
  color: #a0a7c5;
  cursor: pointer;
  transition: all 0.15s ease;
}
.mini-pill-icon-btn:hover {
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff;
}
.mini-pill-icon-btn:active {
  transform: scale(0.9);
}

.icon-expand-up {
  transform: rotate(180deg);
}

/* Fade slide animation */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

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

/* NOTICE FOR CREATOR */
.event-owner-host-notice {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: #1e2235;
  border: 1px solid #383e5e;
  margin-top: 4px;
}
.host-notice-icon {
  font-size: 20px;
  line-height: 1.2;
  flex-shrink: 0;
}
.host-notice-text strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
  color: #f1f3f9;
  margin-bottom: 2px;
}
.host-notice-text p {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: var(--muted);
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
