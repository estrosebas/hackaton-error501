<script setup lang="ts">
import { onMounted, onScopeDispose, shallowRef, useTemplateRef } from 'vue'
import { apiImage } from '../../services/api'
import AppIcon from '../AppIcon.vue'

const props = defineProps<{
  postId: string
  index?: number
  presentation?: 'cover' | 'edge' | 'slide'
}>()
const photo = useTemplateRef<HTMLElement>('post-photo')
const url = shallowRef('')
const failed = shallowRef(false)
const loading = shallowRef(false)
const request = new AbortController()
let observer: IntersectionObserver | undefined

async function load() {
  if (loading.value || request.signal.aborted) return
  loading.value = true
  if (url.value) {
    URL.revokeObjectURL(url.value)
    url.value = ''
  }
  failed.value = false
  try {
    const index = props.index ? `/${props.index}` : ''
    const image = await apiImage(`/feed/${encodeURIComponent(props.postId)}/photo${index}`, request.signal)
    if (!request.signal.aborted) url.value = URL.createObjectURL(image)
  } catch {
    if (!request.signal.aborted) failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (!photo.value || typeof IntersectionObserver === 'undefined') {
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
  <figure ref="post-photo" class="post-photo" :class="presentation && `post-photo--${presentation}`">
    <img
      v-if="url && !failed"
      :src="url"
      :alt="presentation === 'cover' ? '' : presentation === 'slide' ? `Foto ${(index ?? 0) + 1} de la publicación` : 'Foto de la publicación'"
      decoding="async"
      @error="failed = true"
    />
    <div v-if="presentation === 'cover' && (!url || failed)" class="photo-cover-placeholder">
      <AppIcon name="calendar" :size="30" />
    </div>
    <div v-else-if="!url || failed" class="photo-state">
      <p v-if="failed || loading" :role="loading ? 'status' : undefined">{{ failed ? 'No pudimos cargar la foto.' : 'Cargando foto…' }}</p>
      <AppIcon v-else name="camera" :size="26" aria-label="Foto de la publicación" />
      <button v-if="failed" class="text-link" type="button" @click="load">Reintentar</button>
    </div>
  </figure>
</template>

<style scoped>
.post-photo {
  overflow: hidden;
  margin: 12px 0 0;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface);
}
.post-photo img {
  display: block;
  width: 100%;
  max-height: 460px;
  object-fit: contain;
}
.post-photo--edge,
.post-photo--cover,
.post-photo--slide {
  margin: 0;
  border: 0;
  border-radius: 0;
}
.post-photo--edge .photo-state,
.post-photo--slide .photo-state {
  display: grid;
  min-height: 180px;
  place-content: center;
}
.post-photo--cover {
  position: relative;
  aspect-ratio: 16 / 9;
}
.post-photo--cover img {
  width: 100%;
  height: 100%;
  max-height: none;
  object-fit: cover;
}
.post-photo--slide img {
  width: 100%;
  height: 100%;
  max-height: none;
}
.post-photo--slide .photo-state {
  min-height: 100%;
}
.photo-cover-placeholder {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: var(--lavender);
  background: var(--surface-soft);
}
.photo-state {
  padding: 18px 12px;
  color: var(--muted);
  font-size: 0.8125rem;
  text-align: center;
}
.photo-state p {
  margin: 0;
}
.photo-state .text-link {
  min-height: 44px;
}
</style>
