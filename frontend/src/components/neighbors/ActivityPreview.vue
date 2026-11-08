<script setup lang="ts">
import { NButton } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import { formatActivityDate } from '../../composables/useCommunity'
import type { NearbyActivity } from '../../composables/useNearbyNeighbors'

defineProps<{ activity: NearbyActivity }>()
const emit = defineEmits<{ view: [] }>()
</script>

<template>
  <article
    class="neighbor-preview activity-preview"
    :aria-label="`Actividad seleccionada, ${activity.title}`"
    aria-live="polite"
  >
    <div class="neighbor-preview-top">
      <div class="activity-preview-avatar" aria-hidden="true">
        <span>📅</span>
      </div>
      <div>
        <div class="activity-preview-badge">Evento comunitario</div>
        <h2>{{ activity.title }}</h2>
        <p>
          <AppIcon name="pin" :size="12" />{{ activity.location }} · A {{ activity.distance }} m
        </p>
      </div>
    </div>
    <p class="neighbor-preview-bio">
      <strong>Horario:</strong> {{ formatActivityDate(activity.startsAt) }}
      <span v-if="activity.body"> — {{ activity.body }}</span>
    </p>
    <div class="neighbor-preview-bottom">
      <span class="neighbor-skill-label">Organiza: {{ activity.author }}</span>
      <NButton type="primary" size="small" round @click="emit('view')">
        Ver en Inicio<AppIcon name="arrow" :size="15" />
      </NButton>
    </div>
  </article>
</template>

<style scoped>
.activity-preview-avatar {
  display: grid;
  place-items: center;
  width: 49px;
  height: 49px;
  border-radius: 50%;
  background: #3d2215;
  border: 3px solid #f97316;
  font-size: 22px;
  box-shadow: 0 4px 10px rgba(249, 115, 22, 0.35);
  flex-shrink: 0;
}
.activity-preview-badge {
  display: inline-block;
  padding: 1px 7px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #ffedd5;
  background: #ea580c;
  margin-bottom: 2px;
}
</style>

