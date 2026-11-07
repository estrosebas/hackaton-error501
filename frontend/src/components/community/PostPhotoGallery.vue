<script setup lang="ts">
import { shallowRef, useTemplateRef } from 'vue'
import AppIcon from '../AppIcon.vue'
import CommunityPostPhoto from './CommunityPostPhoto.vue'

const props = defineProps<{ postId: string; count: number; edge?: boolean }>()
const track = useTemplateRef<HTMLDivElement>('track')
const active = shallowRef(0)

function sync() {
  const viewport = track.value
  if (!viewport?.clientWidth) return
  active.value = Math.max(0, Math.min(props.count - 1, Math.round(viewport.scrollLeft / viewport.clientWidth)))
}

function move(direction: number) {
  const viewport = track.value
  if (!viewport) return
  const next = Math.max(0, Math.min(props.count - 1, active.value + direction))
  viewport.scrollTo({ left: next * viewport.clientWidth })
}
</script>

<template>
  <CommunityPostPhoto v-if="count === 1" :post-id="postId" :presentation="edge ? 'edge' : undefined" />
  <div v-else-if="count > 1" class="photo-gallery" :class="{ 'photo-gallery--edge': edge }">
    <div
      ref="track"
      class="photo-gallery-track"
      role="group"
      aria-label="Galería de fotos"
      tabindex="0"
      @scroll.passive="sync"
      @keydown.left.prevent="move(-1)"
      @keydown.right.prevent="move(1)"
    >
      <CommunityPostPhoto
        v-for="position in count"
        :key="position"
        class="photo-gallery-slide"
        :post-id="postId"
        :index="position - 1"
        presentation="slide"
      />
    </div>
    <span class="photo-gallery-count" aria-live="polite" :aria-label="`Foto ${active + 1} de ${count}`">
      {{ active + 1 }}/{{ count }}
    </span>
    <button
      type="button"
      class="photo-gallery-control photo-gallery-previous"
      aria-label="Foto anterior"
      :disabled="active === 0"
      @click="move(-1)"
    >
      <AppIcon name="arrow" :size="18" />
    </button>
    <button
      type="button"
      class="photo-gallery-control photo-gallery-next"
      aria-label="Siguiente foto"
      :disabled="active === count - 1"
      @click="move(1)"
    >
      <AppIcon name="arrow" :size="18" />
    </button>
  </div>
</template>

<style scoped>
.photo-gallery {
  position: relative;
  overflow: hidden;
  margin-top: 12px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface);
}
.photo-gallery--edge {
  margin: 0;
  border: 0;
  border-radius: 0;
}
.photo-gallery-track {
  display: flex;
  width: 100%;
  aspect-ratio: 1;
  overflow: auto hidden;
  scroll-snap-type: x mandatory;
  scroll-behavior: smooth;
  scrollbar-width: none;
  overscroll-behavior-inline: contain;
}
.photo-gallery-track:focus-visible {
  outline: 2px solid var(--coral);
  outline-offset: -2px;
}
.photo-gallery-slide {
  flex: 0 0 100%;
  min-width: 0;
  height: 100%;
  scroll-snap-align: start;
}
.photo-gallery-count,
.photo-gallery-control {
  position: absolute;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--surface);
  color: var(--text);
}
.photo-gallery-count {
  top: 10px;
  inset-inline-end: 10px;
  padding: 4px 9px;
  font-size: 0.75rem;
  font-weight: 800;
}
.photo-gallery-control {
  top: 50%;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  transform: translateY(-50%);
}
.photo-gallery-control:disabled {
  opacity: 0.45;
  cursor: default;
}
.photo-gallery-previous {
  inset-inline-start: 8px;
}
.photo-gallery-previous svg {
  transform: rotate(180deg);
}
.photo-gallery-next {
  inset-inline-end: 8px;
}
</style>
