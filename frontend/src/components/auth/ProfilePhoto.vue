<script setup lang="ts">
import { NButton, NModal, useMessage } from 'naive-ui'
import { onBeforeUnmount, shallowRef, useTemplateRef } from 'vue'
import AppIcon from '../AppIcon.vue'
import FaceCapture from './FaceCapture.vue'
import { useProfileAvatar } from '../../composables/useProfileAvatar'
import { api } from '../../services/api'

const { refresh } = useProfileAvatar()
const message = useMessage()
const open = shallowRef(false)
const camera = shallowRef(false)
const photo = shallowRef<string | null>(null)
const busy = shallowRef(false)
const reading = shallowRef(false)
const error = shallowRef('')
const photoInput = useTemplateRef<HTMLInputElement>('photoInput')
let reader: FileReader | undefined

function resetSelection() {
  reader?.abort()
  reader = undefined
  reading.value = false
  camera.value = false
  photo.value = null
  error.value = ''
}

function upload(event: Event) {
  const input = event.currentTarget as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  input.value = ''
  resetSelection()

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024) {
    error.value = 'Elige una imagen JPG, PNG o WebP de hasta 4 MiB.'
    return
  }

  const fileReader = new FileReader()
  reader = fileReader
  reading.value = true
  fileReader.onload = () => {
    if (reader !== fileReader) return
    photo.value = String(fileReader.result)
    reading.value = false
    reader = undefined
  }
  fileReader.onerror = () => {
    if (reader !== fileReader) return
    error.value = 'No pudimos abrir la imagen. Elige otra foto.'
    reading.value = false
    reader = undefined
  }
  fileReader.readAsDataURL(file)
}

function startCamera() {
  resetSelection()
  camera.value = true
}

async function save() {
  if (!photo.value || busy.value || reading.value) return
  busy.value = true
  error.value = ''
  try {
    await api('/profile/avatar', { method: 'POST', body: JSON.stringify({ foto: photo.value }) })
    void refresh()
    open.value = false
    message.success('Tu foto de perfil se actualizó.')
  } catch (failure) {
    error.value = failure instanceof Error ? failure.message : 'No pudimos guardar tu foto. Inténtalo de nuevo.'
  } finally {
    busy.value = false
  }
}

onBeforeUnmount(() => reader?.abort())
</script>

<template>
  <NButton
    type="primary"
    secondary
    size="large"
    aria-haspopup="dialog"
    @click="resetSelection(); open = true"
  >
    <template #icon><AppIcon name="camera" :size="19" /></template>
    Cambiar foto de perfil
  </NButton>

  <NModal
    v-model:show="open"
    preset="card"
    title="Cambiar foto de perfil"
    aria-label="Cambiar foto de perfil"
    style="width: min(460px, calc(100vw - 24px))"
    :closable="false"
    :mask-closable="!busy"
    :close-on-esc="!busy"
    @after-leave="resetSelection"
  >
    <p class="photo-intro">
      Elige una imagen o usa la cámara. Procura que tu rostro esté de frente,
      con buena luz y sin accesorios que lo cubran.
    </p>

    <div class="photo-actions">
      <NButton :disabled="busy" @click="photoInput?.click()">
        {{ photo && !camera ? 'Elegir otra imagen' : 'Elegir imagen' }}
      </NButton>
      <NButton :disabled="busy || camera" @click="startCamera">
        <template #icon><AppIcon name="camera" :size="18" /></template>
        Usar cámara
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
    <p v-if="reading" class="photo-status" role="status">Abriendo imagen…</p>
    <FaceCapture v-if="camera" @validated="photo = $event" />
    <figure v-else-if="photo" class="photo-preview">
      <img :src="photo" alt="Nueva foto seleccionada" />
      <figcaption>Vista previa</figcaption>
    </figure>
    <p v-if="error" class="photo-error" role="alert">{{ error }}</p>

    <p class="photo-privacy">
      <AppIcon name="lock" :size="18" />
      <span>Compararemos la foto con tu rostro de registro. Tu referencia biométrica se conserva.</span>
    </p>

    <template #footer>
      <div class="photo-actions">
        <NButton :disabled="busy" @click="open = false">Cancelar</NButton>
        <NButton
          type="primary"
          :loading="busy"
          :disabled="!photo || busy || reading"
          @click="save"
        >
          {{ busy ? 'Guardando…' : 'Guardar foto' }}
        </NButton>
      </div>
    </template>
  </NModal>
</template>

<style scoped>
.photo-intro,
.photo-privacy,
.photo-status,
.photo-error {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.55;
}

.photo-intro,
.photo-privacy,
.photo-status {
  color: var(--muted);
}

.photo-intro {
  margin-bottom: 14px;
}

.photo-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.photo-actions .n-button {
  flex: 1 0 auto;
  min-height: 44px;
  font-weight: 750;
}

.photo-actions .n-button--primary-type {
  color: var(--surface);
}

.photo-privacy {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--line);
}

.photo-privacy svg {
  margin-top: 2px;
  color: var(--lavender);
}

.photo-status,
.photo-error {
  margin-top: 12px;
}

.photo-error {
  color: var(--local-accent-text);
}

.photo-preview {
  margin: 14px 0 0;
  text-align: center;
}

.photo-preview img {
  display: block;
  width: auto;
  max-width: 100%;
  max-height: 220px;
  margin-inline: auto;
  border-radius: 12px;
}

.photo-preview figcaption {
  margin-top: 6px;
  color: var(--muted);
  font-size: 0.8125rem;
}
</style>
