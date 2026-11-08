<script setup lang="ts">
import {computed,onMounted,onBeforeUnmount,ref,watch} from 'vue'
import {RouterLink} from 'vue-router'
import {NButton} from 'naive-ui'
import CommunityEventMap from '../components/events/CommunityEventMap.vue'
import {useMapEvents,currentPosition,type MapEvent} from '../composables/useMapEvents'
import {currentUser} from '../composables/useAuth'
const {events,catalog,error,loading,hasMore,load,action}=useMapEvents()
const category=ref(''),selected=ref<number>(),busy=ref(false),actionError=ref(''),notice=ref(''),tracking=ref(false)
const center=ref({lat:Number(currentUser.value?.latitude)||-12.0464,lng:Number(currentUser.value?.longitude)||-77.0428})
const visible=computed(()=>events.value.filter(event=>!category.value||event.category===category.value))
const active=computed(()=>events.value.find(event=>event.id===selected.value))
const categoryOf=(event:MapEvent)=>catalog.value.categories.find(item=>item.id===event.category)
const date=(value:string)=>new Date(value).toLocaleString('es-PE',{dateStyle:'medium',timeStyle:'short'})
async function run(event:MapEvent,kind:'join'|'cancel',method='POST'){if(busy.value)return;busy.value=true;actionError.value='';notice.value='';try{await action(event,kind,undefined,method);notice.value=kind==='cancel'?'Evento cancelado.':method==='DELETE'?'Inscripción cancelada.':'Estás inscrito. Valida tu asistencia cuando estés en el lugar.'}catch(e){actionError.value=e instanceof Error?e.message:'No pudimos completar la acción.'}finally{busy.value=false}}
async function locate(manual?:MapEvent){
  if(busy.value||document.hidden)return;busy.value=true;actionError.value='';notice.value=''
  try{const location=await currentPosition();center.value={lat:location.latitude,lng:location.longitude};tracking.value=true
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
<section class="standard-page map-events-page">
  <header class="map-page-heading"><div><h1>Mapa comunitario</h1><p>Encuentra un plan. Acércate. Haz comunidad.</p></div><RouterLink class="event-primary-link" :to="{name:'create'}">+ Crear en el mapa</RouterLink></header>
  <section class="empathy-summary"><div><strong>{{catalog.points}}</strong><span>puntos de empatía</span></div><p>La inscripción no suma puntos. Asiste durante el evento a 100 m o menos y activa tu ubicación.</p><NButton secondary round :loading="busy" @click="locate()">{{tracking?'Actualizar mi ubicación':'Activar ubicación'}}</NButton></section>
  <div class="event-filters" aria-label="Tipos de eventos"><button :aria-pressed="!category" @click="category=''">Todos</button><button v-for="item in catalog.categories" :key="item.id" :aria-pressed="category===item.id" :style="{'--event-color':item.color}" @click="category=item.id">{{item.emoji}} {{item.label}}</button></div>
  <p v-if="error||actionError" role="alert">{{error||actionError}}</p><p v-if="notice" role="status">{{notice}}</p><p v-if="loading" role="status">Cargando eventos…</p>
  <CommunityEventMap :center="center" :events="visible" :categories="catalog.categories" :frames="catalog.frames" :selected="selected" @select="selected=$event"/>
  <article v-if="active" class="event-details" :style="{'--event-color':categoryOf(active)?.color}"><header><span>{{categoryOf(active)?.emoji}} {{categoryOf(active)?.label}} · +{{active.points}} P</span><button aria-label="Cerrar evento" @click="selected=undefined">×</button></header><h2>{{active.title}}</h2><p class="event-description">{{active.description}}</p><p>📍 {{active.place}}</p><p>{{date(active.starts_at)}} — {{date(active.ends_at)}}</p><p>{{active.has_cost?'Con costo':'Sin costo'}} · {{active.participants}} inscritos · Por {{active.author.name}}</p>
    <p v-if="active.attended" class="event-attended">✓ Asistencia validada · {{active.points}} puntos acreditados</p>
    <div class="event-actions"><NButton v-if="!active.joined" type="primary" round :disabled="busy" @click="run(active,'join')">Inscribirme</NButton><template v-else-if="!active.attended"><NButton type="primary" round :disabled="busy" @click="locate(active)">Validar mi asistencia</NButton><NButton secondary round :disabled="busy" @click="run(active,'join','DELETE')">Cancelar inscripción</NButton></template><NButton v-if="active.is_owner" secondary round :disabled="busy" @click="run(active,'cancel')">Cancelar evento</NButton></div>
  </article>
  <div class="map-event-list"><button v-for="event in visible" :key="event.id" class="map-event-row" :class="{'is-selected':event.id===selected}" :style="{'--event-color':categoryOf(event)?.color}" @click="selected=event.id"><span class="event-list-glyph">{{categoryOf(event)?.emoji}}</span><span><strong>{{event.title}}</strong><small>{{date(event.starts_at)}} · {{event.place}}</small><small>{{event.joined?(event.attended?'✓ Asististe':'✓ Inscrito'):categoryOf(event)?.label}}</small></span><b>+{{event.points}} P</b></button></div>
  <p v-if="!loading&&!visible.length" class="map-empty">Todavía no hay eventos de este tipo en tu distrito. Crea el primero.</p><p v-if="hasMore">Se muestran los primeros 200 eventos. Filtra por tipo.</p><NButton secondary round :loading="loading" @click="load">Actualizar eventos</NButton>
  <section class="frames-gallery"><h2>Tus próximos marcos</h2><p>Un nuevo marco cada 10 puntos. Podrás elegirlo al crear un evento.</p><div class="frames-grid"><div v-for="frame in catalog.frames" :key="frame.id" :class="{'frame-locked':!frame.unlocked}"><span :style="{borderColor:frame.color,borderStyle:frame.style}">✨</span><strong>{{frame.label}}</strong><small>{{frame.unlocked?'Desbloqueado':`${frame.points} puntos`}}</small></div></div></section>
</section>
</template>
<style scoped>
.map-events-page{max-width:1050px;margin:auto}.map-page-heading{display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:20px}h1{font-size:1.7rem;margin:0}h2{font-size:1.15rem}p{font-size:.8125rem;line-height:1.65;color:var(--muted)}.event-primary-link{padding:12px 16px;border-radius:24px;background:var(--local-accent-text);color:#151529;text-decoration:none;font-size:.8125rem;font-weight:800}.empathy-summary,.frames-gallery,.event-details{padding:20px;border:1px solid var(--line);border-radius:20px;background:var(--surface-soft);margin:18px 0}.empathy-summary>div{display:flex;align-items:baseline;gap:10px}.empathy-summary strong{font-size:2rem;color:var(--local-accent-text)}.empathy-summary span{font-size:.8125rem}.event-filters{display:flex;gap:8px;overflow:auto;padding:5px 0 16px}.event-filters button{white-space:nowrap;border:1px solid var(--line);border-radius:22px;padding:9px 12px;background:var(--surface-soft);color:var(--text);font-size:.75rem}.event-filters button[aria-pressed=true]{border-color:var(--event-color,var(--local-accent-text));color:var(--event-color,var(--local-accent-text))}.event-details{border-left:4px solid var(--event-color)}.event-details header{display:flex;justify-content:space-between;color:var(--event-color);font-size:.8rem}.event-details header button{background:none;border:0;color:var(--text);font-size:24px}.event-description{white-space:pre-wrap;overflow-wrap:anywhere}.event-actions{display:flex;gap:10px;flex-wrap:wrap}.event-attended{color:#65cfb0}.map-event-list{display:grid;gap:9px;margin:18px 0}.map-event-row{display:flex;align-items:center;gap:12px;text-align:left;border:1px solid var(--line);border-radius:15px;padding:14px;background:var(--surface-soft);color:var(--text)}.map-event-row.is-selected{border-color:var(--event-color)}.map-event-row>span:nth-child(2){flex:1;min-width:0}.map-event-row strong{display:block;font-size:.875rem;overflow-wrap:anywhere}.map-event-row small{display:block;color:var(--muted);font-size:.7rem;margin-top:6px}.map-event-row b{color:var(--event-color);font-size:.8rem}.event-list-glyph{font-size:25px}.frames-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:18px;margin-top:20px}.frames-grid>div{display:grid;gap:8px;text-align:center;justify-items:center}.frames-grid span{display:grid;place-items:center;width:54px;height:54px;border:5px solid;border-radius:50%;background:var(--surface);font-size:24px}.frames-grid strong{font-size:.75rem}.frames-grid small{font-size:.7rem;color:var(--muted)}.frame-locked{opacity:.55}
</style>
