<script setup lang="ts">
import { computed, onScopeDispose, shallowRef } from 'vue'
import { postKinds, type ActivityDetails } from '../../composables/useCommunity'
import { currentUser } from '../../composables/useAuth'
import { NButton, NFormItem, NInput } from 'naive-ui'
import AppSelect from '../AppSelect.vue'
import PhotoPicker from '../PhotoPicker.vue'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'
import ActivityFields from './ActivityFields.vue'

const props = defineProps<{ neighborhood: string; saving: boolean; error: string; activity?: boolean }>()
const emit = defineEmits<{ publish: [text: string, kind: string, photos: Blob[], activity?: ActivityDetails] }>()
const draft = shallowRef('')
const kind = shallowRef<string | null>('moment')
const options = postKinds.filter((item) => item.value !== 'activity')
const emergency = computed(() => !props.activity && kind.value === 'emergency')
const publishLabel = computed(() => {
  if (props.activity) return 'Publicar actividad'
  return emergency.value ? 'Publicar emergencia' : 'Publicar en mi barrio'
})
const draftPlaceholder = computed(() => {
  if (props.activity) return 'Cuenta qué harán y qué necesitan llevar.'
  return emergency.value
    ? 'Describe qué pasó, en qué zona y qué ayuda necesitas.'
    : 'Cuenta qué quieres compartir con tus vecinos.'
})
const activityTitle = shallowRef('')
const activityStarts = shallowRef('')
const activityLocation = shallowRef('')
const startTime = computed(() => new Date(activityStarts.value).getTime())
const invalidDate = computed(() => Boolean(activityStarts.value && !(startTime.value > Date.now())))
const dateExpired = shallowRef(false)
const draftId = computed(() => props.activity ? 'activity-draft' : 'community-draft')
const maxPhotos = 4
const photos = shallowRef<{ blob: Blob; url: string }[]>([])
const previews = computed(() => photos.value.map(photo => photo.url))
const photoError = shallowRef('')
const preparingPhoto = shallowRef(false)
const canPublish = computed(() => props.activity
  ? Boolean(activityTitle.value.trim() && startTime.value > Date.now() && activityLocation.value.trim() && draft.value.trim())
  : Boolean(kind.value && (draft.value.trim() || (kind.value === 'moment' && photos.value.length))),
)
let disposed = false
const draftTheme = {
  color: 'var(--surface-soft)',
  colorFocus: 'var(--surface-raised)',
  border: '1px solid var(--line)',
  placeholderColor: 'var(--muted)',
  paddingMedium: '0 14px',
}

function removePhoto(index: number) {
  const selected = photos.value[index]
  if (!selected) return
  URL.revokeObjectURL(selected.url)
  photos.value = photos.value.filter((_, position) => position !== index)
  photoError.value = ''
}

async function preparePhoto(file: File) {
  const source = URL.createObjectURL(file)
  try {
    const image = new Image()
    image.src = source
    await image.decode()
    if (disposed) return null
    const scale = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight))
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale))
    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale))
    const context = canvas.getContext('2d')
    if (!context) throw new Error()
    context.drawImage(image, 0, 0, canvas.width, canvas.height)
    return await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/webp', 0.85))
  } finally {
    URL.revokeObjectURL(source)
  }
}

async function selectPhotos(files: File[]) {
  if (preparingPhoto.value || props.saving) return
  photoError.value = ''
  if (photos.value.length + files.length > maxPhotos) {
    photoError.value = `Puedes adjuntar hasta ${maxPhotos} fotos por publicación.`
    return
  }
  if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 15 * 1024 * 1024)) {
    photoError.value = 'Elige fotos JPG, PNG o WebP de hasta 15 MB cada una.'
    return
  }

  preparingPhoto.value = true
  try {
    const prepared: Blob[] = []
    for (const file of files) {
      const image = await preparePhoto(file)
      if (disposed) return
      if (!image || image.size > 1536 * 1024) {
        photoError.value = 'Una foto es demasiado pesada. Elige una más pequeña.'
        return
      }
      prepared.push(image)
    }
    photos.value = [...photos.value, ...prepared.map(blob => ({ blob, url: URL.createObjectURL(blob) }))]
  } catch {
    if (!disposed) photoError.value = 'No pudimos abrir una de las fotos. Elige otro archivo.'
  } finally {
    preparingPhoto.value = false
  }
}

onScopeDispose(() => {
  disposed = true
  for (const photo of photos.value) URL.revokeObjectURL(photo.url)
})

function submit() {
  if (!canPublish.value || props.saving || preparingPhoto.value) return
  const attachments = photos.value.map(photo => photo.blob)
  if (props.activity) {
    if (startTime.value <= Date.now()) {
      dateExpired.value = true
      return
    }
    const userLat = currentUser.value?.latitude != null ? Number(currentUser.value.latitude) : null
    const userLng = currentUser.value?.longitude != null ? Number(currentUser.value.longitude) : null
    emit('publish', draft.value.trim(), 'activity', attachments, {
      title: activityTitle.value.trim(),
      starts_at: new Date(startTime.value).toISOString(),
      location: activityLocation.value.trim(),
      latitude: userLat,
      longitude: userLng,
    })
  } else if (kind.value) {
    emit('publish', draft.value.trim(), kind.value, attachments)
  }
}
</script>

<template>
  <form class="compose-form" @submit.prevent="submit">
    <div class="compose-author">
      <CurrentUserAvatar :size="42" />
      <div>
        <strong>{{ currentUser?.name || 'Tú' }}</strong>
        <p>Para vecinos de tu distrito · {{ neighborhood }}</p>
      </div>
    </div>
    <ActivityFields
      v-if="activity"
      v-model:title="activityTitle"
      v-model:starts="activityStarts"
      v-model:location="activityLocation"
      :disabled="saving"
      :invalid-date="invalidDate || dateExpired"
      @update:starts="dateExpired = false"
    />
    <NFormItem v-else label="¿Qué quieres compartir?" label-placement="top" :show-feedback="false">
      <AppSelect
        v-model:value="kind"
        :options="options"
        :disabled="saving"
        label="¿Qué quieres compartir?"
      />
    </NFormItem>
    <div class="compose-draft">
      <label :for="draftId">{{ activity ? 'Descripción de la actividad' : 'Tu publicación' }}</label>
      <NInput
        v-model:value="draft"
        type="textarea"
        :placeholder="draftPlaceholder"
        :input-props="{ id: draftId, 'aria-describedby': `${draftId}-help` }"
        :theme-overrides="draftTheme"
        :disabled="saving"
        :maxlength="2000"
        show-count
        :autosize="{ minRows: 5, maxRows: 12 }"
      />
      <PhotoPicker
        :previews="previews"
        :max-files="maxPhotos"
        input-label="Foto de la publicación"
        preview-alt="Vista previa de la foto de la publicación"
        :disabled="saving"
        :loading="preparingPhoto"
        :invalid="Boolean(photoError)"
        @select="selectPhotos"
        @remove="removePhoto"
      >
        <span>{{ photos.length ? `${photos.length} de ${maxPhotos} fotos` : `Hasta ${maxPhotos} fotos` }}</span>
        <span v-if="!activity && kind === 'moment' && photos.length">Texto opcional</span>
      </PhotoPicker>
      <p v-if="photoError" class="compose-error" role="alert">{{ photoError }}</p>
      <p v-if="emergency" :id="`${draftId}-help`" class="emergency-note">
        La emergencia aparecerá como prioridad para los vecinos conectados de tu distrito.
      </p>
      <p v-else :id="`${draftId}-help`">Se comparte con tu distrito. Puedes incluir hasta cinco hashtags.</p>
    </div>
    <p v-if="error" class="compose-error" role="alert">{{ error }}</p>
    <NButton
      type="primary"
      size="large"
      block
      attr-type="submit"
      :disabled="!canPublish || saving || preparingPhoto"
      :loading="saving"
      >{{ publishLabel }}</NButton
    >
  </form>
</template>

<style scoped>
.compose-draft {
  display: grid;
  gap: 8px;
}
.compose-draft label {
  font-size: 0.875rem;
  font-weight: 800;
}
.compose-draft p,
.compose-error {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.5;
}
.compose-draft p:not(.compose-error) {
  color: var(--muted);
}
.compose-error {
  color: #ffb0c0;
}
.compose-draft p.emergency-note {
  color: var(--local-accent-text);
  font-weight: 800;
}
</style>
