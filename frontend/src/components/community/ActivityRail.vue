<script setup lang="ts">
import { shallowRef, useTemplateRef, watch } from 'vue'
import { formatActivityDate, useCommunity } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'
import CommunityPostPhoto from './CommunityPostPhoto.vue'

const emit = defineEmits<{ select: [id: string]; openAgenda: [] }>()
const { activities, activitiesLoading, activitiesError, loadActivities } = useCommunity()
const rail = useTemplateRef<HTMLDivElement>('activity-rail')
const canGoBack = shallowRef(false)
const canGoForward = shallowRef(false)

function syncControls() {
  const track = rail.value
  if (!track) return
  canGoBack.value = track.scrollLeft > 1
  canGoForward.value = track.scrollLeft + track.clientWidth < track.scrollWidth - 1
}

watch(rail, (track, _, onCleanup) => {
  if (!track) return
  const observer = new ResizeObserver(syncControls)
  observer.observe(track)
  onCleanup(() => observer.disconnect())
  syncControls()
})
watch(() => activities.value.length, syncControls, { flush: 'post' })

function move(direction: number) {
  const track = rail.value
  const first = track?.children.item(0) as HTMLElement | null
  const second = track?.children.item(1) as HTMLElement | null
  if (track && first && second) {
    track.scrollBy({ left: direction * (second.offsetLeft - first.offsetLeft) })
  }
}
</script>

<template>
  <section class="activities-section" aria-labelledby="activities-title">
    <div class="local-section-heading">
      <div class="activities-heading">
        <AppIcon name="calendar" :size="19" />
        <h2 id="activities-title">Nos vemos en el barrio</h2>
      </div>
      <div v-if="activities.length > 1" class="rail-controls">
        <button type="button" aria-label="Actividad anterior" :disabled="!canGoBack" @click="move(-1)">
          <AppIcon name="arrow" :size="16" />
        </button>
        <button type="button" aria-label="Siguiente actividad" :disabled="!canGoForward" @click="move(1)">
          <AppIcon name="arrow" :size="16" />
        </button>
      </div>
    </div>
    <p v-if="activitiesLoading && !activities.length" class="activity-message" role="status">
      Cargando actividades…
    </p>
    <div v-else-if="activitiesError && !activities.length" class="activity-message">
      <span role="alert">No pudimos cargar las actividades.</span>
      <button class="text-link" type="button" @click="loadActivities">Reintentar</button>
    </div>
    <div v-else-if="!activities.length" class="activity-message">
      <strong>Todavía no hay actividades</strong>
      <p>Los planes que publiquen tus vecinos aparecerán aquí.</p>
    </div>
    <div v-else ref="activity-rail" class="activity-rail" @scroll.passive="syncControls">
      <article
        v-for="activity in activities"
        :key="activity.id"
        class="activity-card"
      >
        <CommunityPostPhoto
          v-if="activity.has_photo"
          :post-id="activity.id"
          presentation="cover"
        />
        <div v-else class="activity-placeholder" aria-hidden="true">
          <AppIcon name="calendar" :size="30" />
        </div>
        <span v-if="(activity.photo_count ?? 0) > 1" class="activity-photo-count">
          <AppIcon name="camera" :size="14" /> {{ activity.photo_count }} fotos
        </span>
        <button class="activity-link" type="button" @click="emit('select', activity.id)">
          <span class="activity-card-meta">
            <span class="activity-body">{{ activity.activity?.title || activity.paragraphs.join(' ') }}</span>
            <AppIcon name="arrow" :size="16" />
          </span>
          <span v-if="activity.status === 'resolved'" class="activity-author">Finalizada</span>
          <template v-if="activity.activity">
            <span class="activity-line">
              <AppIcon name="calendar" :size="14" />
              <time :datetime="activity.activity.starts_at">{{ formatActivityDate(activity.activity.starts_at) }}</time>
            </span>
            <span class="activity-line">
              <AppIcon name="pin" :size="14" /> {{ activity.activity.location }}
            </span>
          </template>
          <span v-else class="activity-author">{{ activity.author }} · {{ activity.neighborhood }}</span>
        </button>
      </article>
    </div>
    <button v-if="activities.length" class="activity-posts-link text-link" type="button" @click="emit('openAgenda')">
      Ver agenda de actividades <AppIcon name="arrow" :size="16" />
    </button>
  </section>
</template>

<style scoped>
.activities-section {
  display: grid;
  gap: 12px;
  padding-block: 16px;
  border-block: 1px solid var(--line);
  background: var(--surface-soft);
}
.local-section-heading {
  min-height: 44px;
  margin-inline: var(--local-gutter);
}
.activities-heading,
.rail-controls {
  display: flex;
  align-items: center;
  gap: 6px;
}
.activities-heading svg {
  color: var(--lavender);
}
.rail-controls button {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--surface-raised);
  color: var(--lavender);
}
.rail-controls button:not(:disabled):hover {
  border-color: var(--lavender);
}
.rail-controls button:disabled {
  opacity: 0.45;
  cursor: default;
}
.rail-controls button:first-child svg {
  transform: rotate(180deg);
}
.activity-message {
  margin: 0 var(--local-gutter) 3px;
  padding: 4px 0 4px 13px;
  border-inline-start: 1px solid var(--line);
  color: var(--muted);
  font-size: 0.875rem;
}
.activity-message strong {
  color: var(--text);
}
.activity-message p {
  margin: 4px 0 0;
}
.activity-rail {
  display: flex;
  gap: 10px;
  min-width: 0;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  padding: 5px var(--local-gutter) 8px;
  scroll-padding-inline: var(--local-gutter);
  scroll-snap-type: x mandatory;
  scroll-behavior: smooth;
  scrollbar-width: thin;
  scrollbar-color: var(--line) transparent;
}
.activity-card {
  position: relative;
  display: flex;
  flex: 0 0 min(76%, 310px);
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-raised);
  scroll-snap-align: start;
}
.activity-card:only-child {
  flex-basis: 100%;
}
.activity-card:hover {
  border-color: var(--lavender);
}
.activity-card:has(.activity-link:focus-visible) {
  outline: 2px solid var(--coral);
  outline-offset: 2px;
}
.activity-placeholder {
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 9;
  background: var(--surface-soft);
  color: var(--lavender);
}
.activity-photo-count {
  position: absolute;
  top: 8px;
  inset-inline-end: 8px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 999px;
  background: var(--surface);
  color: var(--text);
  font-size: 0.75rem;
}
.activity-link {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  border: 0;
  background: transparent;
  color: var(--text);
  text-align: start;
}
.activity-link::after {
  position: absolute;
  inset: 0;
  content: '';
}
.activity-card-meta {
  display: flex;
  align-self: stretch;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px;
}
.activity-card-meta svg {
  color: var(--lavender);
}
.activity-body {
  display: -webkit-box;
  overflow: hidden;
  overflow-wrap: anywhere;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  font-size: 0.9375rem;
  line-height: 1.5;
  font-weight: 800;
}
.activity-author {
  margin-top: auto;
  color: var(--muted);
  font-size: 0.75rem;
  overflow-wrap: anywhere;
}
.activity-line {
  display: flex;
  align-items: center;
  gap: 5px;
  color: var(--muted);
  font-size: 0.75rem;
  overflow-wrap: anywhere;
}
.activity-posts-link {
  justify-self: start;
  min-height: 44px;
  margin-inline: var(--local-gutter);
  font-size: 0.8125rem;
}
</style>
