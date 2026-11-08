<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { apiImage } from '../../services/api'

const props = defineProps<{ eventId: number; title: string }>()
const url = ref(''), failed = ref(false), loading = ref(false)
const controller = new AbortController()

async function load() {
  if (loading.value) return
  loading.value = true
  failed.value = false
  if (url.value) URL.revokeObjectURL(url.value)
  url.value = ''
  try {
    const blob = await apiImage(`/map-events/${props.eventId}/photo`, controller.signal)
    if (!controller.signal.aborted) url.value = URL.createObjectURL(blob)
  } catch {
    if (!controller.signal.aborted) failed.value = true
  } finally {
    loading.value = false
  }
}
onMounted(() => { void load() })
onBeforeUnmount(() => {
  controller.abort()
  if (url.value) URL.revokeObjectURL(url.value)
})
</script>

<template>
  <figure class="event-detail-photo">
    <img v-if="url && !failed" :src="url" :alt="`Foto del evento: ${title}`" decoding="async" @error="failed = true" />
    <figcaption v-else class="event-photo-state" role="status">
      {{ failed ? 'No pudimos cargar la foto del evento.' : 'Cargando foto del evento…' }}
      <button v-if="failed" type="button" class="text-link" @click="load">Reintentar</button>
    </figcaption>
  </figure>
</template>

<style scoped>
.event-detail-photo{display:grid;place-items:center;overflow:hidden;margin:0 0 18px;min-height:180px;border:1px solid var(--line);border-radius:16px;background:var(--surface)}
.event-detail-photo img{display:block;width:100%;height:clamp(200px,32dvh,320px);object-fit:contain}
.event-photo-state{display:grid;gap:10px;justify-items:center;padding:24px;color:var(--muted);font-size:12px;text-align:center}
</style>
