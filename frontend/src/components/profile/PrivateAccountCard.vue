<script setup lang="ts">
import { ref,reactive,onBeforeUnmount } from 'vue'
import { NButton } from 'naive-ui'
import { api } from '../../services/api'
import { refreshAccount } from '../../composables/useAuth'
interface PrivateData {name:string;dni:string|null;birth_date:string|null;email:string;phone:string|null;ubigeo:string|null;region:string|null;province:string|null;district:string|null;neighborhood:string|null}
interface Place {code:string;name:string}
const data=ref<PrivateData|null>(null),editing=ref(false),saving=ref(false),loading=ref(false),error=ref(''),saved=ref(false)
const regions=ref<Place[]>([]),provinces=ref<Place[]>([]),districts=ref<Place[]>([]),region=ref(''),province=ref('')
const draft=reactive({email:'',phone:'',ubigeo:'',neighborhood:'',current_password:''})
const scope=new AbortController();let generation=0
async function places(url:string){return(await api<{data:Place[]}>(url,{signal:scope.signal})).data}
function fail(e:unknown){if(!scope.signal.aborted)error.value=e instanceof Error?e.message:'No pudimos cargar tus datos.'}
async function edit(){if(loading.value||saving.value)return;loading.value=true;error.value='';saved.value=false
  try{data.value=(await api<{data:PrivateData}>('/profile/private',{signal:scope.signal,cache:'no-store'})).data}catch(e){fail(e);loading.value=false;return}
  if(!data.value){loading.value=false;return}editing.value=true
  Object.assign(draft,{email:data.value.email,phone:data.value.phone||'',ubigeo:data.value.ubigeo||'',neighborhood:data.value.neighborhood||'',current_password:''})
  region.value=draft.ubigeo.slice(0,2);province.value=draft.ubigeo.slice(0,4)
  try{regions.value=await places('/locations/regions');provinces.value=region.value?await places(`/locations/regions/${region.value}/provinces`):[];districts.value=province.value?await places(`/locations/provinces/${province.value}/districts`):[]}catch(e){fail(e)}finally{loading.value=false}
}
async function changeRegion(){const version=++generation;province.value='';draft.ubigeo='';provinces.value=[];districts.value=[];loading.value=true
  try{const list=await places(`/locations/regions/${region.value}/provinces`);if(version===generation)provinces.value=list}catch(e){fail(e)}finally{if(version===generation)loading.value=false}
}
async function changeProvince(){const version=++generation;draft.ubigeo='';districts.value=[];loading.value=true
  try{const list=await places(`/locations/provinces/${province.value}/districts`);if(version===generation)districts.value=list}catch(e){fail(e)}finally{if(version===generation)loading.value=false}
}
async function save(){if(saving.value||loading.value)return;saving.value=true;error.value=''
  try{await api<{data:PrivateData}>('/profile/private',{method:'PATCH',signal:scope.signal,body:JSON.stringify({...draft,email:draft.email.trim(),phone:draft.phone.trim()||null})});draft.current_password='';await refreshAccount();cancel();saved.value=true}catch(e){fail(e)}finally{saving.value=false}
}
function cancel(){generation++;editing.value=false;data.value=null;Object.assign(draft,{email:'',phone:'',ubigeo:'',neighborhood:'',current_password:''});error.value=''}
onBeforeUnmount(()=>{scope.abort();draft.current_password=''})
</script>
<template>
  <section class="private-account-card" aria-labelledby="private-account-title">
    <header><div><h2 id="private-account-title">Mis datos privados</h2><p>Solo tú puedes ver esta información.</p></div><NButton v-if="!editing" secondary round :loading="loading" :disabled="loading" @click="edit">Gestionar datos</NButton></header>
    <p v-if="error" role="alert" class="private-error">{{error}}</p>
    <p v-if="loading&&!editing" role="status">Cargando tus datos…</p>
    <template v-if="editing&&data">
      <dl class="private-identity"><div><dt>Nombre validado</dt><dd>{{data.name}}</dd></div><div><dt>DNI</dt><dd>{{data.dni||'Sin indicar'}}</dd></div><div><dt>Fecha de nacimiento</dt><dd>{{data.birth_date?data.birth_date.slice(0,10).split('-').reverse().join('/'):'Sin indicar'}}</dd></div></dl>
      <p class="identity-hint">Los datos de identidad provienen de tu registro validado.</p>
      <dl v-if="!editing" class="private-details"><div><dt>Correo electrónico</dt><dd>{{data.email}}</dd></div><div><dt>Teléfono</dt><dd>{{data.phone||'Sin indicar'}}</dd></div><div><dt>Región</dt><dd>{{data.region||'Sin indicar'}}</dd></div><div><dt>Provincia</dt><dd>{{data.province||'Sin indicar'}}</dd></div><div><dt>Distrito</dt><dd>{{data.district||'Sin indicar'}}</dd></div><div><dt>Barrio</dt><dd>{{data.neighborhood||'Sin indicar'}}</dd></div></dl>
      <form v-else class="private-edit-form" aria-label="Gestionar mis datos privados" @submit.prevent="save">
        <label>Correo electrónico<input v-model="draft.email" type="email" autocomplete="email" required maxlength="255" :disabled="saving" /></label>
        <label v-if="draft.email!==data.email">Contraseña actual para cambiar el correo<input v-model="draft.current_password" type="password" autocomplete="current-password" required maxlength="255" :disabled="saving" /><small>El correo se usa para recuperar tu cuenta.</small></label>
        <label>Teléfono<input v-model="draft.phone" type="tel" autocomplete="tel" maxlength="20" :disabled="saving" /></label>
        <label>Región<select v-model="region" required :disabled="saving||loading" @change="changeRegion"><option value="" disabled>Selecciona tu región</option><option v-for="item in regions" :key="item.code" :value="item.code">{{item.name}}</option></select></label>
        <label>Provincia<select v-model="province" required :disabled="saving||loading||!provinces.length" @change="changeProvince"><option value="" disabled>Selecciona tu provincia</option><option v-for="item in provinces" :key="item.code" :value="item.code">{{item.name}}</option></select></label>
        <label>Distrito<select v-model="draft.ubigeo" required :disabled="saving||loading||!districts.length"><option value="" disabled>Selecciona tu distrito</option><option v-for="item in districts" :key="item.code" :value="item.code">{{item.name}}</option></select></label>
        <label>Barrio<input v-model="draft.neighborhood" required maxlength="120" :disabled="saving" /></label>
        <p v-if="loading" role="status">Cargando lugares…</p>
        <div class="private-actions"><NButton secondary round :disabled="saving" @click="cancel">Cancelar</NButton><NButton type="primary" round attr-type="submit" :disabled="loading||saving||!draft.ubigeo" :loading="saving">Guardar datos privados</NButton></div>
      </form>
    </template>
    <p v-if="saved" role="status" class="private-saved">Tus datos privados fueron actualizados.</p>
  </section>
</template>
<style scoped>
.private-account-card{margin-top:24px;padding:20px;border:1px solid var(--line);border-radius:18px;background:var(--surface-soft)}header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}h2{font-size:1.125rem;margin:0}header p,.identity-hint{font-size:.75rem;color:var(--muted);line-height:1.6}.private-identity,.private-details{display:grid;gap:16px;margin:20px 0}.private-identity>div,.private-details>div{min-width:0}dt{font-size:.75rem;color:var(--muted);margin-bottom:4px}dd{margin:0;font-size:.875rem;color:var(--text);overflow-wrap:anywhere}.private-identity{padding-bottom:16px;border-bottom:1px solid var(--line)}.private-edit-form{display:grid;gap:16px;margin-top:18px}.private-edit-form label{display:grid;gap:7px;font-size:.8125rem;color:var(--lavender);font-weight:800}.private-edit-form input,.private-edit-form select{width:100%;min-width:0;min-height:44px;border:1px solid var(--line);padding:10px;border-radius:11px;background:var(--surface);color:var(--text);font:inherit}.private-edit-form small{color:var(--muted);font-weight:400}.private-actions{display:flex;gap:10px;flex-wrap:wrap}.private-error{color:var(--local-accent-text);font-size:.8125rem}.private-saved{font-size:.8125rem;color:var(--lavender)}@media(min-width:480px){.private-identity,.private-details{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
