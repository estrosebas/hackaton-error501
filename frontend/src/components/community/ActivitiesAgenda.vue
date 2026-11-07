<script setup lang="ts">
import { computed } from 'vue'
import type { Post } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'

const props = defineProps<{ activities: Post[]; loading: boolean; failed: boolean }>()
const emit = defineEmits<{ select: [id: string]; retry: [] }>()
const dayFormat = new Intl.DateTimeFormat('es-PE', { day: 'numeric' })
const monthFormat = new Intl.DateTimeFormat('es-PE', { month: 'short' })
const timeFormat = new Intl.DateTimeFormat('es-PE', { timeStyle: 'short' })

const groups = computed(() => {
  const now = Date.now()
  const rows = props.activities.map((post) => {
    const date = post.activity ? new Date(post.activity.starts_at) : null
    return { post, date, upcoming: post.status !== 'resolved' && date !== null && date.getTime() >= now }
  })
  const upcoming = rows.filter(row => row.upcoming).sort((first, second) =>
    (first.date?.getTime() ?? 0) - (second.date?.getTime() ?? 0),
  )
  return [
    { title: 'Próximas actividades', rows: upcoming },
    { title: 'Otras actividades', rows: rows.filter(row => !row.upcoming) },
  ].filter(group => group.rows.length)
})
</script>

<template>
  <div class="activities-agenda">
    <div v-if="failed" class="agenda-state">
      <p role="alert">No pudimos cargar las actividades.</p>
      <button type="button" class="text-link" @click="emit('retry')">Reintentar</button>
    </div>
    <p v-else-if="loading && !activities.length" class="agenda-state" role="status">Cargando actividades…</p>
    <p v-else-if="!activities.length" class="agenda-state">Todavía no se han publicado actividades.</p>

    <section v-for="group in groups" :key="group.title" class="agenda-group">
      <h3>{{ group.title }}</h3>
      <ul class="agenda-list" role="list">
        <li v-for="{ post, date } in group.rows" :key="post.id">
          <button class="agenda-entry" type="button" @click="emit('select', post.id)">
            <time v-if="date" class="agenda-date" :datetime="post.activity?.starts_at">
              <strong>{{ dayFormat.format(date) }}</strong>
              <span>{{ monthFormat.format(date) }}</span>
            </time>
            <span v-else class="agenda-date" aria-label="Sin fecha programada">
              <AppIcon name="calendar" :size="20" />
            </span>
            <span class="agenda-info">
              <strong>{{ post.activity?.title || post.paragraphs[0] }}</strong>
              <span v-if="post.status === 'resolved'" class="agenda-meta">Finalizada</span>
              <span v-else-if="date" class="agenda-meta">
                <AppIcon name="clock" :size="14" /> {{ timeFormat.format(date) }}
              </span>
              <span v-if="post.activity?.location" class="agenda-meta">
                <AppIcon name="pin" :size="14" /> {{ post.activity.location }}
              </span>
            </span>
            <AppIcon class="agenda-arrow" name="arrow" :size="17" />
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.activities-agenda {
  display: grid;
  gap: 18px;
}
.agenda-state {
  margin: 0;
  color: var(--muted);
  font-size: 0.875rem;
}
.agenda-state p {
  margin: 0;
}
.agenda-state .text-link {
  min-height: 44px;
}
.agenda-group h3 {
  margin: 0 0 8px;
  font-size: 0.9375rem;
  font-weight: 900;
}
.agenda-list {
  margin: 0;
  padding: 0;
  border-block-start: 1px solid var(--line);
  list-style: none;
}
.agenda-entry {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) 17px;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 72px;
  padding: 10px 4px;
  border: 0;
  border-block-end: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  text-align: start;
}
.agenda-entry:hover {
  background: var(--surface-soft);
}
.agenda-entry:focus-visible {
  outline-offset: -2px;
}
.agenda-date {
  display: grid;
  align-content: center;
  justify-items: center;
  min-height: 48px;
  padding: 4px;
  border-radius: 12px;
  background: var(--surface-raised);
  color: var(--lavender);
  font-size: 0.6875rem;
  line-height: 1.2;
}
.agenda-date strong {
  color: var(--text);
  font-size: 1.0625rem;
  font-weight: 900;
}
.agenda-info {
  display: grid;
  gap: 4px;
  overflow-wrap: anywhere;
}
.agenda-info strong {
  font-size: 0.875rem;
  font-weight: 900;
}
.agenda-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--muted);
  font-size: 0.75rem;
}
.agenda-arrow {
  color: var(--lavender);
}
</style>
