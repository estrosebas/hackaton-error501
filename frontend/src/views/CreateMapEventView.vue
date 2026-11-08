<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { NButton, NCheckbox, NInput, useMessage } from 'naive-ui'
import AppIcon, { type IconName } from '../components/AppIcon.vue'
import AppSelect from '../components/AppSelect.vue'
import PhotoPicker from '../components/PhotoPicker.vue'
import MapEventFramePicker from '../components/events/MapEventFramePicker.vue'
import MapEventLocationFields from '../components/events/MapEventLocationFields.vue'
import { useMapEvents } from '../composables/useMapEvents'
import { currentUser } from '../composables/useAuth'
import { api } from '../services/api'

const { catalog, loadCatalog } = useMapEvents()
const router = useRouter()
const route = useRoute()
const isEditing = computed(() => Boolean(route.params.id))
const eventId = computed(() => route.params.id ? Number(route.params.id) : null)
const message = useMessage()
const categoryIcons: Record<string, IconName> = { community: 'users', emergency: 'bell', trend: 'calendar', support: 'hand' }
const localTime = (date: Date) => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
const form = reactive({
  category: 'community', type: null as string | null, title: '', description: '', place: '',
  starts_at: localTime(new Date(Date.now() + 3600000)),
  ends_at: localTime(new Date(Date.now() + 7200000)),
  has_cost: false, frame: '',
})
const point = ref({ lat: Number(currentUser.value?.latitude ?? -12.0464), lng: Number(currentUser.value?.longitude ?? -77.0428) })
const confirmed = ref(currentUser.value?.latitude != null && currentUser.value?.longitude != null)
const saving = ref(false)
const locating = ref(false)
const loading = ref(true)
const error = ref('')
const typeError = ref('')
const locationRequired = ref(false)
const photoError = ref('')
const photo = ref<File>()
const photoPreview = ref<string>()
const selectedCategory = computed(() => catalog.value.categories.find(item => item.id === form.category))
const typeOptions = computed(() => (selectedCategory.value?.types ?? []).map(value => ({ label: value, value })))
const reward = computed(() => form.category === 'support' && form.has_cost ? 0 : selectedCategory.value?.points ?? 0)

function selectCategory(category: string) {
  if (form.category === category) return
  form.category = category
  form.type = null
  if (category === 'emergency') {
    form.starts_at = localTime(new Date())
    form.ends_at = localTime(new Date(Date.now() + 7200000))
  }
}
watch(() => form.type, () => { typeError.value = '' })
watch(photo, (file, _, onCleanup) => {
  photoPreview.value = undefined
  if (!file) return
  const url = URL.createObjectURL(file)
  photoPreview.value = url
  onCleanup(() => URL.revokeObjectURL(url))
})

async function loadOptions() {
  loading.value = true
  error.value = ''
  try {
    await loadCatalog()
    if (eventId.value) {
      const res = await api<{ data: any }>(`/map-events/${eventId.value}`)
      const ev = res.data
      form.category = ev.category
      form.type = ev.type
      form.title = ev.title
      form.description = ev.description
      form.place = ev.place
      form.starts_at = localTime(new Date(ev.starts_at))
      form.ends_at = localTime(new Date(ev.ends_at))
      form.has_cost = Boolean(ev.has_cost)
      form.frame = ev.frame || ''
      point.value = { lat: Number(ev.latitude), lng: Number(ev.longitude) }
      confirmed.value = true
      if (ev.has_photo) {
        photoPreview.value = `/api/v1/map-events/${ev.id}/photo`
      }
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'No pudimos cargar la información.'
  } finally {
    loading.value = false
  }
}
onMounted(loadOptions)

function choosePhoto([file]: File[]) {
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    photoError.value = 'Elige una foto que pese hasta 2 MB.'
    return
  }
  photo.value = file
  photoError.value = ''
}

function removePhoto() {
  photo.value = undefined
  photoError.value = ''
}

async function submit() {
  if (saving.value || locating.value || loading.value || !catalog.value.categories.length) return
  error.value = ''
  if (!form.type) {
    typeError.value = 'Selecciona un tipo de evento.'
    return
  }
  if (!confirmed.value) {
    locationRequired.value = true
    return
  }
  if (new Date(form.ends_at) <= new Date(form.starts_at)) {
    error.value = 'La hora de fin debe ser posterior a la hora de inicio.'
    return
  }
  const diffHours = (new Date(form.ends_at).getTime() - new Date(form.starts_at).getTime()) / 3600000
  if (diffHours > 48) {
    error.value = 'El evento puede durar hasta 48 horas.'
    return
  }
  saving.value = true
  try {
    const body = new FormData()
    for (const [key, value] of Object.entries(form)) {
      if (key === 'frame' && !value) continue
      const field = key === 'starts_at' || key === 'ends_at'
        ? new Date(String(value)).toISOString()
        : typeof value === 'boolean' ? (value ? '1' : '0') : String(value)
      body.append(key, field)
    }
    body.append('latitude', String(point.value.lat))
    body.append('longitude', String(point.value.lng))
    if (photo.value) body.append('photo', photo.value)
    const url = isEditing.value ? `/map-events/${eventId.value}` : '/map-events'
    await api(url, { method: 'POST', body })
    message.success(isEditing.value ? 'Tu evento ha sido actualizado.' : 'Tu evento ya está en el mapa comunitario.')
    await router.push({ name: 'neighbors' })
  } catch (e) {
    error.value = e instanceof Error ? e.message : (isEditing.value ? 'No pudimos actualizar el evento. Puedes volver a intentarlo.' : 'No pudimos crear el evento. Puedes volver a intentarlo.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="standard-page create-map-event" aria-labelledby="create-map-event-title">
    <RouterLink class="event-back-link" :to="{ name: 'neighbors' }">
      <AppIcon name="arrow" :size="17" /> Mapa comunitario
    </RouterLink>
    <header class="page-heading event-page-heading">
      <div>
        <h1 id="create-map-event-title">{{ isEditing ? 'Editar evento' : 'Comparte en el mapa' }}</h1>
        <p>{{ isEditing ? 'Actualiza los detalles o el punto de encuentro de tu evento.' : 'Un encuentro, una mano o una causa cerca de ti.' }}</p>
      </div>
      <span class="event-page-icon"><AppIcon name="map" :size="24" /></span>
    </header>

    <div v-if="loading" class="event-loading-card" role="status">
      <div class="loading-spinner" />
      <p>{{ isEditing ? 'Cargando datos del evento…' : 'Preparando opciones del mapa…' }}</p>
    </div>

    <form v-else @submit.prevent="submit">
      <fieldset class="map-event-form" :disabled="saving">
        <section class="event-form-panel event-form-info" aria-labelledby="event-info-title">
          <div class="event-section-heading">
            <AppIcon name="calendar" :size="20" />
            <h2 id="event-info-title">Datos del evento</h2>
          </div>
          <fieldset class="event-category-field">
            <legend>¿Qué quieres crear?</legend>
            <div class="event-category-choices">
              <button
                v-for="item in catalog.categories" :key="item.id" type="button"
                :aria-pressed="form.category === item.id" :style="{ '--event-color': item.color }"
                @click="selectCategory(item.id)"
              >
                <AppIcon :name="categoryIcons[item.id] ?? 'calendar'" :size="19" />
                <strong>{{ item.label }}</strong>
                <AppIcon v-if="form.category === item.id" name="check" :size="15" class="event-category-check" />
              </button>
            </div>
            <p v-if="selectedCategory" class="event-help">{{ selectedCategory.examples }}</p>
          </fieldset>

          <div class="event-field">
            <span class="event-field-label">Tipo de evento</span>
            <AppSelect v-model:value="form.type" :options="typeOptions" label="Tipo de evento" placeholder="Selecciona un tipo" :disabled="saving || !typeOptions.length" :aria-invalid="!!typeError" :aria-describedby="typeError ? 'map-event-type-error' : undefined" />
            <p v-if="typeError" id="map-event-type-error" class="event-field-error" role="alert">{{ typeError }}</p>
          </div>
          <div class="event-field">
            <label for="map-event-title">Título</label>
            <NInput v-model:value="form.title" :input-props="{ id: 'map-event-title', required: true }" :disabled="saving" :maxlength="120" placeholder="Ej. Jornada de voluntariado" />
          </div>
          <div class="event-field">
            <label for="map-event-description">Cuéntale al barrio</label>
            <NInput v-model:value="form.description" type="textarea" :input-props="{ id: 'map-event-description', required: true }" :disabled="saving" :maxlength="2000" :rows="4" placeholder="Qué haremos y qué necesitamos" />
          </div>
          <div class="event-date-fields">
            <label class="event-field">Inicio <input v-model="form.starts_at" type="datetime-local" required /></label>
            <label class="event-field">Fin <input v-model="form.ends_at" type="datetime-local" required /></label>
          </div>
          <NCheckbox v-model:checked="form.has_cost" :disabled="saving">Con costo</NCheckbox>
          <p v-if="selectedCategory" class="event-reward">
            <AppIcon name="heart" :size="17" />
            <span><strong>{{ reward ? `+${reward} puntos de empatía` : 'Sin puntos de empatía' }}</strong> · Se acreditan al validar la asistencia.</span>
          </p>
          <div class="event-field event-photo-field">
            <span class="event-field-label">Foto del evento <small>Opcional</small></span>
            <PhotoPicker
              :previews="photoPreview ? [photoPreview] : []" input-label="Foto del evento"
              preview-alt="Vista previa de la foto del evento" :disabled="saving"
              :described-by="photoError ? 'map-event-photo-error' : 'map-event-photo-hint'" :invalid="!!photoError"
              @select="choosePhoto" @remove="removePhoto"
            >
              <span id="map-event-photo-hint">Hasta 2 MB. Se verá en el marcador.</span>
            </PhotoPicker>
            <p v-if="photoError" id="map-event-photo-error" class="event-field-error" role="alert">{{ photoError }}</p>
          </div>
        </section>

        <div class="event-form-side">
          <MapEventLocationFields
            v-model:point="point" v-model:confirmed="confirmed" v-model:place="form.place" v-model:locating="locating"
            :disabled="saving" class="event-form-panel"
          />
          <MapEventFramePicker v-if="catalog.frames.length" v-model="form.frame" :frames="catalog.frames" :points="catalog.points" :glyph="selectedCategory?.emoji" class="event-form-panel event-frame-panel" />
        </div>

        <div class="event-form-footer">
          <p v-if="error" class="event-field-error" role="alert">{{ error }}</p>
          <p v-if="locationRequired && !confirmed" class="event-field-error" role="alert">Activa tu ubicación o elige un punto en el mapa.</p>
          <NButton v-if="!catalog.categories.length" secondary round @click="loadOptions">Volver a cargar</NButton>
          <div v-else class="event-footer-buttons">
            <NButton type="primary" round attr-type="submit" :loading="saving" :disabled="locating">
              <template #icon><AppIcon name="pin" :size="18" /></template>
              {{ isEditing ? 'Guardar cambios' : 'Crear evento en el mapa' }}
            </NButton>
            <NButton v-if="isEditing" secondary round :disabled="saving" @click="router.push({ name: 'neighbors' })">
              Descartar cambios
            </NButton>
          </div>
        </div>
      </fieldset>
    </form>
  </section>
</template>

<style scoped>
.create-map-event { container-type: inline-size; color-scheme: dark; }
.event-back-link { display: inline-flex; align-items: center; gap: 7px; min-height: 44px; margin: -8px 0 5px; color: var(--lavender); font-size: 0.8125rem; font-weight: 800; text-decoration: none; }
.event-back-link:hover { color: var(--text); }
.event-back-link svg { transform: rotate(180deg); color: var(--coral); }
.event-page-heading { padding-bottom: 18px; border-bottom: 1px solid var(--line); margin-bottom: 16px; }
.event-page-icon { display: grid; flex: 0 0 48px; width: 48px; height: 48px; place-items: center; border-radius: 14px; color: var(--coral); background: var(--surface-raised); }
.map-event-form, .event-form-side { display: grid; gap: 14px; min-width: 0; }
.map-event-form, .event-category-field { margin: 0; border: 0; padding: 0; }
.event-form-panel { min-width: 0; border: 1px solid var(--line); border-radius: 16px; padding: 16px; background: var(--surface); }
.event-form-info { display: grid; gap: 15px; align-content: start; }
.event-section-heading { display: flex; align-items: center; gap: 8px; }
.event-section-heading svg, .event-reward svg { color: var(--coral); flex-shrink: 0; }
.event-section-heading h2 { margin: 0; font-size: 1.125rem; font-weight: 900; }
.event-category-field { min-width: 0; }
.event-category-field legend, .event-field { color: var(--text); font-size: 0.875rem; font-weight: 800; }
.event-category-field legend { padding: 0; margin-bottom: 8px; }
.event-category-choices { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.event-category-choices button {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 6px;
  min-height: 57px;
  padding: 8px 22px 8px 8px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-soft);
  color: var(--text);
  text-align: start;
  font: inherit;
  font-size: 0.75rem;
  line-height: 1.25;
  overflow-wrap: anywhere;
  cursor: pointer;
}
.event-category-choices button:hover:not(:disabled), .event-category-choices button[aria-pressed='true'] { border-color: var(--event-color); }
.event-category-choices button[aria-pressed='true'] { background: color-mix(in srgb, var(--event-color) 13%, var(--surface-soft)); }
.event-category-choices button > svg { color: var(--event-color); flex-shrink: 0; }
.event-category-check { position: absolute; top: 5px; right: 5px; }
.event-help, .event-field-error { margin: 5px 0 0; font-size: 0.8125rem; font-weight: 500; line-height: 1.6; }
.event-help, .event-field small { color: var(--muted); }
.event-field-error { color: var(--local-accent-text); }
.event-field, .event-date-fields { display: grid; gap: 6px; min-width: 0; }
.event-date-fields { gap: 12px; }
.event-field :deep(.n-input), .event-field :deep(.n-select) { font-weight: 500; }
.event-date-fields input { width: 100%; min-width: 0; min-height: 44px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface-soft); color: var(--text); font: inherit; font-weight: 500; }
.event-reward { display: flex; align-items: flex-start; gap: 8px; margin: -3px 0 0; padding: 10px 11px; border-radius: 10px; background: var(--surface-raised); color: var(--muted); font-size: 0.8125rem; line-height: 1.45; }
.event-reward strong { color: var(--text); }
.event-photo-field { padding-top: 13px; border-top: 1px solid var(--line); }
.event-field-label small { margin-inline-start: 4px; font-weight: 500; }
.event-frame-panel { padding-block: 7px; }
.event-form-footer { display: grid; gap: 8px; }
.event-form-footer .n-button { min-height: 46px; }
.event-footer-buttons { display: flex; flex-direction: column; gap: 8px; }
.event-loading-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  min-height: 260px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--surface);
  color: var(--muted);
  font-size: 0.875rem;
}
.loading-spinner {
  width: 34px;
  height: 34px;
  border: 3px solid #2b3046;
  border-top-color: var(--coral);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
@container (min-width: 430px) {
  .event-date-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
