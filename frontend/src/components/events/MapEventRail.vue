<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useMapEvents } from '../../composables/useMapEvents'
import MapEventPhoto from './MapEventPhoto.vue'
import AppIcon from '../AppIcon.vue'
import CommunityPostPhoto from '../community/CommunityPostPhoto.vue'
import { useCommunity, formatActivityDate } from '../../composables/useCommunity'

const props = defineProps<{ agenda?: boolean }>()
const emit = defineEmits<{selectActivity:[id:string];openAgenda:[]}>()
const { activities, activitiesLoading } = useCommunity()
const { events, catalog, loading, error, load } = useMapEvents()
const mapLoaded = ref(false)
const available = computed(() => events.value.filter(event => !event.cancelled && Date.parse(event.ends_at) > Date.now()))
const upcoming = computed(() => props.agenda ? available.value : available.value.slice(0, 12))
const category = (id: string) => catalog.value.categories.find(item => item.id === id)
const date = (value: string) => new Date(value).toLocaleString('es-PE', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
onMounted(async () => { try { await load() } finally { mapLoaded.value = true } })
</script>

<template>
  <section class="map-event-suggestions" :class="{ 'suggestions-agenda': agenda }" aria-label="Eventos del mapa comunitario">
    <header><div><h2>Nos vemos en el barrio</h2><p v-if="mapLoaded">{{available.length}} eventos del mapa · {{activities.length}} actividades de la comunidad</p><p v-else>Eventos y actividades de tu distrito</p></div><RouterLink :to="{name:'neighbors'}">Ver mapa <AppIcon name="arrow" :size="16" /></RouterLink></header>
    <p v-if="!mapLoaded || activitiesLoading || (loading && !upcoming.length && !activities.length)" class="rail-state" role="status">Buscando planes del barrio…</p>
    <div v-else-if="error && !upcoming.length && !activities.length" class="rail-state"><p role="alert">No pudimos cargar los eventos.</p><button type="button" @click="load">Reintentar</button></div>
    <p v-else-if="!upcoming.length && !activities.length" class="rail-state">Aún no hay eventos. Crea un encuentro con el botón +.</p>
    <div v-else class="event-stories">
      <RouterLink v-for="event in upcoming" :key="event.id" :to="{name:'neighbors',query:{event:String(event.id)}}" class="event-story" :class="{'story-emergency':event.category==='emergency'}" :style="{'--event-color':category(event.category)?.color || '#a699ff'}">
        <MapEventPhoto v-if="event.has_photo" lazy :event-id="event.id" :title="event.title" />
        <div v-else class="story-placeholder"><AppIcon name="calendar" :size="32" /></div>
        <div class="story-copy"><span class="story-category">{{category(event.category)?.label || event.category}}</span><strong>{{event.title}}</strong><time :datetime="event.starts_at">{{date(event.starts_at)}}</time><span>{{event.place}}</span><small>{{event.points ? `+${event.points} P al validar asistencia` : 'Apoyo con costo · sin puntos'}}</small></div>
      </RouterLink>
      <button v-for="activity in activities" :key="`activity-${activity.id}`" type="button" class="event-story story-activity" style="--event-color:#a699ff" @click="emit('selectActivity',activity.id)">
        <CommunityPostPhoto v-if="activity.has_photo" :post-id="activity.id" presentation="cover" />
        <div v-else class="story-placeholder"><AppIcon name="calendar" :size="32" /></div>
        <div class="story-copy"><span class="story-category">Actividad comunitaria</span><strong>{{activity.activity?.title || activity.paragraphs.join(' ')}}</strong><time v-if="activity.activity" :datetime="activity.activity.starts_at">{{formatActivityDate(activity.activity.starts_at)}}</time><span>{{activity.activity?.location || activity.neighborhood}}</span></div>
      </button>
    </div>
    <button v-if="!agenda && (available.length || activities.length)" type="button" class="all-plans" @click="emit('openAgenda')">Ver todos los planes ({{available.length + activities.length}}) <AppIcon name="arrow" :size="15" /></button>
  </section>
</template>

<style scoped>
.map-event-suggestions{padding:16px var(--local-gutter,16px);border-block:1px solid var(--line);background:var(--surface-soft);display:grid;gap:12px;min-width:0}.map-event-suggestions header{display:flex;justify-content:space-between;gap:12px;align-items:center}.map-event-suggestions h2{font-size:16px;margin:0}.map-event-suggestions header p{font-size:12px;color:var(--muted);margin:5px 0 0}.map-event-suggestions header a{display:flex;gap:4px;align-items:center;color:var(--coral);font-size:12px;white-space:nowrap}.event-stories{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:6px;overscroll-behavior-inline:contain}.event-story{flex:0 0 220px;scroll-snap-align:start;overflow:hidden;border:1px solid var(--event-color);border-top-width:3px;border-radius:16px;background:var(--surface-raised);color:var(--text);text-decoration:none;display:flex;flex-direction:column}.event-story :deep(.event-photo),.story-placeholder{height:125px;min-height:125px;aspect-ratio:auto}.event-story :deep(img){width:100%;height:125px;object-fit:cover}.story-placeholder{display:grid;place-items:center;background:var(--surface);color:var(--lavender)}.story-copy{display:grid;gap:6px;padding:12px}.story-copy strong{font-size:14px;line-height:1.35}.story-copy time,.story-copy span,.story-copy small{font-size:11px;color:var(--muted)}.story-copy .story-category{color:var(--event-color);font-weight:800}.story-emergency{border-color:#ff5c72;box-shadow:0 0 0 1px #ff5c7240}.rail-state{color:var(--muted);font-size:13px}.rail-state button{border:1px solid var(--line);background:var(--surface-raised);color:var(--text);border-radius:10px;padding:9px 16px}.suggestions-agenda{padding-inline:0;background:transparent;border:0}
.event-story :deep(.event-detail-photo){margin:0;height:125px;min-height:125px;border:0;border-radius:0}
.story-activity{text-align:left;padding:0;font:inherit;cursor:pointer}.story-activity :deep(.post-photo){width:100%;height:125px;aspect-ratio:auto}
.all-plans{justify-self:start;display:flex;align-items:center;gap:8px;border:0;background:transparent;color:var(--coral);padding:8px 0;font:inherit;font-size:12px;cursor:pointer}
</style>
