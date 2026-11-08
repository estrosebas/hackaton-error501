<script setup lang="ts">
import { onMounted, onScopeDispose, shallowRef, useTemplateRef } from 'vue'
import { apiImage } from '../../services/api'
import AppIcon from '../AppIcon.vue'

const props = defineProps<{ listingId: string; alt: string; eager?: boolean }>()
const photo = useTemplateRef<HTMLElement>('photo')
const url = shallowRef('')
const failed = shallowRef(false)
const request = new AbortController()
let observer: IntersectionObserver | undefined

async function load() {
  try {
    const image = await apiImage(`/listings/${props.listingId}/photo`, request.signal)
    if (!request.signal.aborted) url.value = URL.createObjectURL(image)
  } catch {
    if (!request.signal.aborted) failed.value = true
  }
}

onMounted(() => {
  if (props.eager || !photo.value || typeof IntersectionObserver === 'undefined') {
    void load()
    return
  }
  observer = new IntersectionObserver(([entry]) => {
    if (!entry?.isIntersecting) return
    observer?.disconnect()
    void load()
  }, { rootMargin: '180px' })
  observer.observe(photo.value)
})

onScopeDispose(() => {
  observer?.disconnect()
  request.abort()
  if (url.value) URL.revokeObjectURL(url.value)
})
</script>

<template>
  <div ref="photo" class="listing-image">
    <img v-if="url && !failed" :src="url" :alt="alt" decoding="async" @error="failed = true" />
    <span v-else-if="failed" class="listing-image-fallback" :role="alt ? 'img' : undefined" :aria-label="alt ? 'Foto no disponible' : undefined">
      <AppIcon name="camera" :size="28" />
    </span>
  </div>
</template>

<style scoped>
.listing-image {
  overflow: hidden;
  background: var(--surface-soft);
}
.listing-image img,
.listing-image-fallback {
  display: block;
  width: 100%;
  height: 100%;
}
.listing-image img {
  object-fit: cover;
}
.listing-image-fallback {
  display: grid;
  place-items: center;
  color: var(--muted);
}
</style>
