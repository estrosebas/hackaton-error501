<script setup lang="ts">
import { ref, useTemplateRef } from 'vue'
import { NButton, NInput } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import CommunityEventMap from './CommunityEventMap.vue'
import { currentPosition } from '../../composables/useMapEvents'

type Point = { lat: number; lng: number }

defineProps<{ disabled: boolean }>()
const point = defineModel<Point>('point', { required: true })
const confirmed = defineModel<boolean>('confirmed', { required: true })
const place = defineModel<string>('place', { required: true })
const locating = defineModel<boolean>('locating', { required: true })
const locationError = ref('')
const coordinates = useTemplateRef<HTMLDetailsElement>('coordinates')

function pick(value: Point) {
  point.value = value
  confirmed.value = true
  locationError.value = ''
}

async function locate() {
  locating.value = true
  locationError.value = ''
  try {
    const position = await currentPosition()
    pick({ lat: position.latitude, lng: position.longitude })
  } catch {
    locationError.value = 'No pudimos obtener tu ubicación. Mueve el pin o escribe las coordenadas.'
  } finally {
    locating.value = false
  }
}

function updateCoordinate(key: keyof Point, event: Event) {
  const input = event.currentTarget as HTMLInputElement
  if (input.validity.valid) pick({ ...point.value, [key]: input.valueAsNumber })
}

function revealCoordinates() {
  if (coordinates.value) coordinates.value.open = true
}
</script>

<template>
  <section class="map-event-location" aria-labelledby="map-event-location-title">
    <div class="location-heading">
      <AppIcon name="pin" :size="20" />
      <h2 id="map-event-location-title">¿Dónde será?</h2>
    </div>
    <p>El punto de encuentro será visible para los vecinos de tu distrito.</p>

    <label for="map-event-place">Referencia del lugar</label>
    <NInput v-model:value="place" :input-props="{ id: 'map-event-place', required: true }" :maxlength="180" :disabled="disabled" placeholder="Ej. Entrada principal del parque" />

    <CommunityEventMap :center="point" pick @pick="pick" />
    <p>Toca el mapa o arrastra el pin para cambiar el lugar.</p>
    <div class="location-actions">
      <NButton secondary round :loading="locating" :disabled="disabled" @click="locate">
        <template #icon><AppIcon name="locate" :size="18" /></template>
        Usar mi ubicación
      </NButton>
      <span v-if="confirmed" class="location-confirmed" role="status"><AppIcon name="check" :size="17" /> Punto elegido</span>
    </div>
    <p v-if="locationError" class="location-error" role="alert">{{ locationError }}</p>

    <details ref="coordinates" class="coordinate-fields">
      <summary>Editar coordenadas <AppIcon name="chevron" :size="16" /></summary>
      <div>
        <label>Latitud <input :value="point.lat" type="number" step="any" min="-90" max="90" required @change="updateCoordinate('lat', $event)" @invalid="revealCoordinates" /></label>
        <label>Longitud <input :value="point.lng" type="number" step="any" min="-180" max="180" required @change="updateCoordinate('lng', $event)" @invalid="revealCoordinates" /></label>
      </div>
    </details>
  </section>
</template>

<style scoped>
.map-event-location { display: grid; gap: 12px; min-width: 0; }
.location-heading { display: flex; gap: 8px; align-items: center; }
.location-heading svg, .coordinate-fields summary svg { color: var(--coral); flex-shrink: 0; }
.location-heading h2 { margin: 0; font-size: 1.125rem; font-weight: 900; }
.map-event-location p { margin: 0; color: var(--muted); font-size: 0.8125rem; line-height: 1.6; }
.map-event-location label { font-size: 0.875rem; font-weight: 800; }
.map-event-location > label { margin: 4px 0 -6px; }
.location-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.location-actions .n-button { min-height: 44px; }
.location-confirmed { display: inline-flex; align-items: center; gap: 5px; color: var(--lavender); font-size: 0.8125rem; font-weight: 800; }
.map-event-location .location-error { color: var(--local-accent-text); }
.coordinate-fields { border-top: 1px solid var(--line); }
.coordinate-fields summary {
  display: flex;
  min-height: 44px;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: var(--lavender);
  cursor: pointer;
  font-size: 0.8125rem;
  font-weight: 800;
}
.coordinate-fields summary::-webkit-details-marker { display: none; }
.coordinate-fields[open] summary svg { transform: rotate(180deg); }
.coordinate-fields > div { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.coordinate-fields label { display: grid; gap: 6px; }
.coordinate-fields input {
  width: 100%;
  min-width: 0;
  min-height: 44px;
  padding: 8px 10px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-soft);
  color: var(--text);
  font: inherit;
  font-weight: 500;
}
</style>
