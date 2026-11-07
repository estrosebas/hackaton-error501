<script setup lang="ts">
import { ref, onBeforeUnmount, useTemplateRef } from 'vue'
import { NModal, NButton } from 'naive-ui'
import FaceCapture from './FaceCapture.vue'
import { api } from '../../services/api'
import { useProfileAvatar } from '../../composables/useProfileAvatar'
const { refresh } = useProfileAvatar()
const open = ref(false), camera = ref(false), photo = ref<string|null>(null), busy = ref(false), error = ref('')
const photoInput = useTemplateRef<HTMLInputElement>('photoInput')
let disposed = false
async function upload(event: Event) {
  const input = event.currentTarget as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  input.value = ''
  photo.value = null; error.value = ''; camera.value = false
  if(!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 4*1024*1024) {error.value='Elige una imagen JPG, PNG o WebP de hasta 4 MiB.';return}
  const reader = new FileReader()
  reader.onload = () => { if(!disposed) photo.value = String(reader.result) }
  reader.readAsDataURL(file)
}
async function save() {
  if (!photo.value || busy.value) return
  busy.value=true;error.value=''
  try { await api('/profile/avatar',{method:'POST',body:JSON.stringify({foto:photo.value})}); void refresh();open.value=false;photo.value=null;camera.value=false }
  catch(e){error.value=e instanceof Error?e.message:'No pudimos guardar tu foto.'}
  finally{busy.value=false}
}
onBeforeUnmount(() => { disposed = true })
</script>
<template>
  <NButton class="avatar-button" type="primary" secondary block size="large" @click="open=true;error='';photo=null;camera=false">Cambiar foto de perfil</NButton>
  <NModal v-model:show="open" preset="card" title="Tu foto de perfil" style="width:min(440px,94vw)" :mask-closable="!busy" :closable="!busy" @after-leave="camera=false;photo=null">
    <p>Puedes subir una nueva foto donde te veas mejor, pero tu rostro debe ser visible.</p>
    <p>Usa buena luz, mira de frente y evita lentes oscuros o mascarilla. Compararemos tu rostro con el del registro. La foto de perfil no cambia tu referencia biométrica.</p>
    <div class="photo-actions">
      <NButton :disabled="busy" @click="camera=true;photo=null">Tomarme una foto</NButton>
      <NButton
        class="photo-picker"
        type="primary"
        text
        :disabled="busy"
        @click="photoInput?.click()"
      >
        {{ photo && !camera ? 'Elegir otra foto' : 'Elegir una foto' }}
      </NButton>
      <input
        ref="photoInput"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        :disabled="busy"
        hidden
        @change="upload"
      />
    </div>
    <FaceCapture v-if="camera" @validated="(value:string|null)=>photo=value" />
    <img v-else-if="photo" :src="photo" class="preview" alt="Vista previa de tu nueva foto" />
    <p v-if="error" role="alert" style="color:#ffb0c0">{{ error }}</p>
    <NButton type="primary" block :loading="busy" :disabled="!photo || busy" @click="save">Guardar foto</NButton>
  </NModal>
</template>
<style scoped>
.avatar-button{min-height:48px;margin:12px 0 20px;font-size:15px;font-weight:800}.avatar-button img{width:64px;height:64px;object-fit:cover;border-radius:50%}.avatar-button small{color:var(--coral)}
.photo-actions {
  display: grid;
  gap: 6px;
  margin: 16px 0 8px;
}
.photo-picker.n-button {
  justify-self: center;
  min-height: 44px;
  padding-inline: 8px;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 4px;
}
.preview{display:block;max-height:300px;max-width:100%;margin:16px auto;border-radius:12px}
</style>
