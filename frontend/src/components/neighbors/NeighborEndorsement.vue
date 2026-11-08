<script lang="ts">
import {reactive,watch} from 'vue'
import {currentUser} from '../../composables/useAuth'
import {api} from '../../services/api'
interface State {count:number;selected:boolean;loading:boolean;ready:boolean;error:string}
const states=reactive<Record<number,State>>({})
watch(()=>currentUser.value?.id,()=>{Object.keys(states).forEach(id=>delete states[Number(id)])})
</script>
<script setup lang="ts">
import {computed,onMounted,watch as observe} from 'vue'
import AppIcon from '../AppIcon.vue'
const props=defineProps<{neighborId:number;name?:string;compact?:boolean}>()
const state=computed(()=>states[props.neighborId]??(states[props.neighborId]={count:0,selected:false,loading:false,ready:false,error:''}))
async function load(){const s=state.value;if(s.loading||s.ready)return;s.loading=true;try{const {data}=await api<{data:{count:number;selected:boolean}}>(`/neighbors/${props.neighborId}/endorsement`);Object.assign(s,data,{ready:true,error:''})}catch{s.error='No disponible'}finally{s.loading=false}}
async function toggle(){const s=state.value;if(s.loading)return;if(!s.ready){await load();return}s.loading=true;s.error='';try{const {data}=await api<{data:{count:number;selected:boolean}}>(`/neighbors/${props.neighborId}/endorsement`,{method:'PUT',body:JSON.stringify({selected:!s.selected})});Object.assign(s,data)}catch(e){s.error=e instanceof Error?e.message:'No pudimos guardar tu visto bueno.'}finally{s.loading=false}}
onMounted(()=>{void load()});observe(()=>props.neighborId,()=>{void load()})
</script>
<template><div class="neighbor-endorsement" :class="{'endorsement-compact':compact}"><button v-if="neighborId!==currentUser?.id" type="button" :aria-label="`${state.selected?'Retirar':'Dar'} visto bueno vecinal a ${name||'este vecino'}`" :aria-pressed="state.selected" :disabled="state.loading" @click="toggle"><AppIcon name="check" :size="compact?13:17"/><span>{{state.selected?'Tu visto bueno':'Visto bueno vecinal'}}</span><strong v-if="state.ready">{{state.count}}</strong></button><span v-else class="own-endorsements"><AppIcon name="check" :size="17"/>{{state.ready?state.count:'…'}} vistos buenos vecinales</span><small v-if="state.error" role="status">{{state.error}}</small></div></template>
<style scoped>
.neighbor-endorsement{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.neighbor-endorsement button,.own-endorsements{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid var(--line);border-radius:18px;background:var(--surface-soft);color:var(--lavender);font-size:12px;font-weight:800}.neighbor-endorsement button{cursor:pointer}.neighbor-endorsement button[aria-pressed=true]{color:#a1e2c4;border-color:#65cfb0;background:#65cfb018}.neighbor-endorsement button:disabled{opacity:.6}.endorsement-compact button{font-size:10px;min-height:32px;padding:4px 8px}.neighbor-endorsement small{color:var(--muted);font-size:10px}
</style>
