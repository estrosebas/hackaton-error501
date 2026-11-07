<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { NModal, NButton } from 'naive-ui'
import FaceCapture from './FaceCapture.vue'
import { api, clearSession } from '../../services/api'
const emit = defineEmits<{ loaded: [url: string] }>()
const avatar = ref(''), open = ref(false), camera = ref(false), photo = ref<string|null>(null), busy = ref(false), error = ref('')
let disposed = false
async function load() {
  const token = sessionStorage.getItem('masi.api.token')
  const response = await fetch(`${(import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/,'')}/profile/avatar`, {headers:{Authorization:`Bearer ${token}`,Accept:'image/*'},cache:'no-store'})
  if (response.status === 401) { if(sessionStorage.getItem('masi.api.token') === token) clearSession(); return }
  if (!response.ok) return
  const blob = await response.blob()
  if(disposed) return
  if(avatar.value) URL.revokeObjectURL(avatar.value)
  avatar.value = URL.createObjectURL(blob)
  emit('loaded', avatar.value)
}
async function upload(event: Event) {
  photo.value = null; error.value = ''; camera.value = false
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  if(!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 4*1024*1024) {error.value='Elige una imagen JPG, PNG o WebP de hasta 4 MiB.';return}
  const reader = new FileReader()
  reader.onload = () => { if(!disposed) photo.value = String(reader.result) }
  reader.readAsDataURL(file)
}
async function save() {
  if (!photo.value || busy.value) return
  busy.value=true;error.value=''
  try { await api('/profile/avatar',{method:'POST',body:JSON.stringify({foto:photo.value})}); await load();open.value=false;photo.value=null;camera.value=false }
  catch(e){error.value=e instanceof Error?e.message:'No pudimos guardar tu foto.'}
  finally{busy.value=false}
}
onMounted(() => { void load().catch(() => {}) })
onBeforeUnmount(() => {disposed=true;if(avatar.value)URL.revokeObjectURL(avatar.value)})
</script>
<template>
  <NButton class="avatar-button" type="primary" secondary block size="large" @click="open=true;error='';photo=null;camera=false">Cambiar foto de perfil</NButton>
  <NModal v-model:show="open" preset="card" title="Tu foto de perfil" style="width:min(440px,94vw)" :mask-closable="!busy" :closable="!busy" @after-leave="camera=false;photo=null">
    <p>Puedes subir una nueva foto donde te veas mejor, pero tu rostro debe ser visible.</p>
    <p>Usa buena luz, mira de frente y evita lentes oscuros o mascarilla. Compararemos tu rostro con el del registro. La foto de perfil no cambia tu referencia biométrica.</p>
    <div class="photo-actions"><NButton :disabled="busy" @click="camera=true;photo=null">Tomarme una foto</NButton><label>Subir una foto<input type="file" accept="image/jpeg,image/png,image/webp" :disabled="busy" @change="upload" /></label></div>
    <FaceCapture v-if="camera" @validated="(value:string|null)=>photo=value" />
    <img v-else-if="photo" :src="photo" class="preview" alt="Vista previa de tu nueva foto" />
    <p v-if="error" role="alert" style="color:#ffb0c0">{{ error }}</p>
    <NButton type="primary" block :loading="busy" :disabled="!photo || busy" @click="save">Guardar foto</NButton>
  </NModal>
</template>
<style scoped>
.avatar-button{min-height:48px;margin:12px 0 20px;font-size:15px;font-weight:800}.avatar-button img{width:64px;height:64px;object-fit:cover;border-radius:50%}.avatar-button small{color:var(--coral)}.photo-actions{display:grid;gap:12px;margin:16px 0}.photo-actions label{display:grid;gap:8px}.preview{display:block;max-height:300px;max-width:100%;margin:16px auto;border-radius:12px}
</style>
