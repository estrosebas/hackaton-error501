<script setup lang="ts">
import {computed,onMounted,reactive,ref,watch} from 'vue'
import {useRouter,RouterLink} from 'vue-router'
import {NButton} from 'naive-ui'
import CommunityEventMap from '../components/events/CommunityEventMap.vue'
import {useMapEvents,currentPosition} from '../composables/useMapEvents'
import {currentUser} from '../composables/useAuth'
import {api} from '../services/api'
const {catalog,loadCatalog}=useMapEvents(),router=useRouter()
const localTime=(date:Date)=>new Date(date.getTime()-date.getTimezoneOffset()*60000).toISOString().slice(0,16)
const form=reactive({category:'community',type:'',title:'',description:'',place:'',starts_at:localTime(new Date(Date.now()+3600000)),ends_at:localTime(new Date(Date.now()+7200000)),has_cost:false,frame:''})
const point=ref({lat:Number(currentUser.value?.latitude)||-12.0464,lng:Number(currentUser.value?.longitude)||-77.0428}),confirmed=ref(false),saving=ref(false),locating=ref(false),error=ref(''),photo=ref<File>()
const selectedCategory=computed(()=>catalog.value.categories.find(item=>item.id===form.category))
watch(()=>form.category,category=>{form.type='';if(category==='emergency'){form.starts_at=localTime(new Date());form.ends_at=localTime(new Date(Date.now()+7200000))}})
const reward=computed(()=>form.category==='support'&&form.has_cost?0:selectedCategory.value?.points||0)
const fail=(e:unknown)=>{error.value=e instanceof Error?e.message:'No pudimos completar la solicitud.'}
onMounted(async()=>{try{await loadCatalog()}catch(e){fail(e)}})
async function locate(){locating.value=true;error.value='';try{const position=await currentPosition();point.value={lat:position.latitude,lng:position.longitude};confirmed.value=true}catch(e){fail(e)}finally{locating.value=false}}
function pick(value:{lat:number;lng:number}){point.value=value;confirmed.value=true}
function choosePhoto(event:Event){photo.value=(event.target as HTMLInputElement).files?.[0];error.value='';if(photo.value&&photo.value.size>2*1024*1024){photo.value=undefined;(event.target as HTMLInputElement).value='';error.value='La foto debe pesar hasta 2 MB.'}}
async function submit(){if(saving.value)return;if(!confirmed.value){error.value='Activa tu ubicación o elige un punto en el mapa.';return}saving.value=true;error.value=''
  try{const body=new FormData();for(const [key,value] of Object.entries(form)){if(key==='frame'&&!value)continue;body.append(key,key==='starts_at'||key==='ends_at'?new Date(String(value)).toISOString():typeof value==='boolean'?(value?'1':'0'):String(value))}body.append('latitude',String(point.value.lat));body.append('longitude',String(point.value.lng));if(photo.value)body.append('photo',photo.value)
    await api('/map-events',{method:'POST',body});await router.push({name:'neighbors'})
  }catch(e){fail(e)}finally{saving.value=false}
}
</script>
<template><section class="standard-page create-map-event"><header><h1>Comparte en el mapa</h1><p>Un encuentro, una mano o una causa cerca de ti.</p><RouterLink :to="{name:'neighbors'}">← Volver al mapa comunitario</RouterLink></header>
<form @submit.prevent="submit">
  <fieldset :disabled="saving"><legend>¿Qué quieres crear?</legend><div class="event-category-choices"><button v-for="item in catalog.categories" :key="item.id" type="button" :aria-pressed="form.category===item.id" :style="{'--event-color':item.color}" @click="form.category=item.id"><span>{{item.emoji}}</span><strong>{{item.label}}</strong><small>+{{item.points}} P{{item.id==='support'?' sin costo':''}}</small></button></div><p class="event-examples">{{selectedCategory?.examples}}</p>
  <label>Tipo de evento<select v-model="form.type" required><option value="" disabled>Selecciona un tipo</option><option v-for="type in selectedCategory?.types" :key="type" :value="type">{{type}}</option></select></label>
  <label>Título<input v-model="form.title" required maxlength="120" placeholder="Una pollada para ayudar a nuestra vecina"/></label><label>Cuéntale al barrio<textarea v-model="form.description" required maxlength="2000" rows="4" placeholder="Qué haremos, a quién ayudaremos y qué necesitamos"/></label>
  <div class="event-date-fields"><label>Inicio<input v-model="form.starts_at" type="datetime-local" required/></label><label>Fin<input v-model="form.ends_at" type="datetime-local" required/></label></div>
  <label class="event-cost"><input v-model="form.has_cost" type="checkbox"/> Con costo</label><p class="event-examples">{{form.category==='support'?(form.has_cost?'Apoyo con costo: 0 puntos.':'Apoyo sin costo: +1 punto.'):''}} La asistencia validada otorga +{{reward}} puntos; crear o inscribirse no suma.</p>
  <label>Foto del evento (opcional)<input type="file" accept="image/jpeg,image/png,image/webp" @change="choosePhoto"/><small>Se verá dentro del círculo del mapa. Hasta 2 MB.</small></label>
  <h2>¿Dónde será?</h2><p>Usa tu ubicación actual o mueve el pin. La ubicación del evento será visible para tu distrito.</p><NButton secondary round :loading="locating" :disabled="saving" @click="locate">Usar mi ubicación actual</NButton>
  <CommunityEventMap :center="point" pick @pick="pick"/>
  <div class="event-date-fields"><label>Latitud<input v-model.number="point.lat" type="number" step="any" min="-90" max="90" required @change="confirmed=true"/></label><label>Longitud<input v-model.number="point.lng" type="number" step="any" min="-180" max="180" required @change="confirmed=true"/></label></div><label>Referencia del lugar<input v-model="form.place" required maxlength="180" placeholder="Parque del barrio, frente al quiosco"/></label>
  <h2>Elige tu marco</h2><p>Tienes {{catalog.points}} puntos. Cada 10 puntos desbloqueas un marco nuevo.</p><div class="event-frame-choices"><button type="button" :aria-pressed="!form.frame" @click="form.frame=''">Sin marco extra</button><button v-for="frame in catalog.frames" :key="frame.id" type="button" :disabled="!frame.unlocked" :aria-pressed="form.frame===frame.id" @click="form.frame=frame.id"><span :style="{borderColor:frame.color,borderStyle:frame.style}">{{selectedCategory?.emoji}}</span><strong>{{frame.label}}</strong><small>{{frame.unlocked?'Disponible':`${frame.points} puntos`}}</small></button></div>
  <p v-if="error" role="alert" class="event-form-error">{{error}}</p><NButton type="primary" round attr-type="submit" :loading="saving" :disabled="locating||!catalog.categories.length">Crear evento en el mapa</NButton>
  </fieldset>
</form></section></template>
<style scoped>
.create-map-event{max-width:760px;margin:auto}h1{font-size:1.7rem;margin:0}h2{font-size:1.15rem;margin:24px 0 8px}header p,p,small{color:var(--muted);font-size:.8125rem;line-height:1.6}header a{color:var(--lavender);font-size:.8rem}form{margin-top:24px}fieldset{padding:0;border:0;min-width:0}legend{font-weight:800;margin-bottom:15px}.event-category-choices{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.event-category-choices button,.event-frame-choices button{display:grid;gap:8px;padding:15px;border:1px solid var(--line);border-radius:17px;background:var(--surface-soft);color:var(--text);text-align:left}.event-category-choices button[aria-pressed=true]{border-color:var(--event-color);box-shadow:inset 0 0 0 1px var(--event-color)}.event-category-choices span{font-size:1.6rem}.event-category-choices strong{font-size:.8125rem}.event-category-choices small{font-size:.7rem}.event-examples{margin:12px 0 22px}label{display:grid;gap:8px;font-size:.8125rem;font-weight:800;color:var(--lavender);margin:18px 0}input:not([type=checkbox]),textarea,select{width:100%;min-width:0;min-height:44px;padding:12px;border-radius:12px;border:1px solid var(--line);background:var(--surface-soft);color:var(--text);font:inherit}.event-cost{display:flex;align-items:center;gap:10px}.event-cost input{width:18px;height:18px;accent-color:var(--local-accent-text)}.event-date-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.event-frame-choices{display:flex;gap:10px;overflow:auto;padding:8px 0 18px;margin-bottom:16px}.event-frame-choices button{min-width:116px;justify-items:center;text-align:center;font-size:.75rem}.event-frame-choices button[aria-pressed=true]{border-color:var(--local-accent-text)}.event-frame-choices button:disabled{opacity:.45}.event-frame-choices span{display:grid;place-items:center;border:5px solid;border-radius:50%;width:52px;height:52px;font-size:22px}.event-frame-choices small{font-size:.7rem}.event-form-error{color:var(--local-accent-text)}.event-map-wrap{margin-top:18px}
</style>
