<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { useRouter } from 'vue-router'
import { NAvatar, NButton, NEmpty } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import NeighborFilters from './NeighborFilters.vue'
import NearbyMap from './NearbyMap.vue'
import NeighborPreview from './NeighborPreview.vue'
import ActivityPreview from './ActivityPreview.vue'
import NeighborProfileDrawer from './NeighborProfileDrawer.vue'
import { useCommunity, formatActivityDate } from '../../composables/useCommunity'
import { useNearbyNeighbors } from '../../composables/useNearbyNeighbors'
import type { Panel } from '../community/CommunityPanels.vue'
import './nearby-neighbors.css'

const router = useRouter()
const { neighborhood } = useCommunity()
const {
  search,
  radius,
  filter,
  selectedId,
  mode,
  center,
  filteredNeighbors,
  filteredActivities,
  selectedNeighbor,
  selectedActivity,
} = useNearbyNeighbors(neighborhood)
const showProfile = shallowRef(false)
const emit = defineEmits<{ openPanel: [panel: Panel] }>()

const totalCount = computed(
  () => filteredNeighbors.value.length + filteredActivities.value.length,
)

function resetFilters() {
  search.value = ''
  filter.value = 'all'
  radius.value = 600
}
function openProfile(id: string) {
  selectedId.value = id
  showProfile.value = true
}
watch(neighborhood, () => {
  showProfile.value = false
  selectedId.value = ''
})
</script>

<template>
  <section class="nearby-page">
    <NeighborFilters
      v-model:search="search"
      v-model:radius="radius"
      v-model:filter="filter"
      v-model:mode="mode"
      :neighborhood="neighborhood"
      @change-neighborhood="emit('openPanel', 'neighborhood')"
    />
    <div v-if="mode === 'map'" class="neighbor-map-stage">
      <NearbyMap
        :center="center"
        :neighbors="filteredNeighbors"
        :activities="filteredActivities"
        :selected-id="selectedId"
        :radius="radius"
        @select="selectedId = $event"
      />
      <div class="neighbor-map-count" role="status">
        <span />{{ totalCount }}
        {{ totalCount === 1 ? 'punto en tu zona' : 'puntos en tu zona' }}
      </div>
      <ActivityPreview
        v-if="selectedActivity"
        :activity="selectedActivity"
        @view="router.push({ name: 'home' })"
      />
      <NeighborPreview
        v-else-if="selectedNeighbor"
        :neighbor="selectedNeighbor"
        @profile="showProfile = true"
      />
      <div v-else class="neighbor-map-empty">
        <NEmpty description="No encontramos vecinos ni actividades con esos filtros">
          <template #extra>
            <NButton size="small" @click="resetFilters">Ver todo</NButton>
          </template>
        </NEmpty>
      </div>
    </div>
    <div v-else class="neighbor-results-list">
      <div class="neighbor-results-heading">
        <span>{{ totalCount }} resultados en tu zona</span>
      </div>
      <!-- Actividades / Eventos fijados -->
      <button
        v-for="act in filteredActivities"
        :key="act.id"
        class="neighbor-result-row"
        type="button"
        @click="router.push({ name: 'home' })"
      >
        <div class="activity-row-icon" aria-hidden="true">
          <span>📅</span>
        </div>
        <span>
          <strong>{{ act.title }}</strong>
          <small>{{ act.location }} · {{ formatActivityDate(act.startsAt) }}</small>
          <span class="neighbor-result-meta">
            <AppIcon name="pin" :size="12" />A {{ act.distance }} m
            <span class="activity-row-tag">Actividad</span>
          </span>
        </span>
        <AppIcon name="arrow" :size="18" />
      </button>

      <!-- Vecinos -->
      <button
        v-for="person in filteredNeighbors"
        :key="person.id"
        class="neighbor-result-row"
        type="button"
        @click="openProfile(person.id)"
      >
        <NAvatar
          round
          :size="52"
          :src="person.avatar"
          :img-props="{ alt: '' }"
        />
        <span>
          <strong>{{ person.name }}</strong>
          <small>{{ person.skills.join(' · ') }}</small>
          <span class="neighbor-result-meta">
            <AppIcon name="pin" :size="12" />A {{ person.distance }} m
            <span v-if="person.canHelp" class="neighbor-can-help">Puede ayudar</span>
          </span>
        </span>
        <AppIcon name="arrow" :size="18" />
      </button>
      <NEmpty
        v-if="!totalCount"
        description="No encontramos resultados"
      >
        <template #extra>
          <NButton @click="resetFilters">Limpiar filtros</NButton>
        </template>
      </NEmpty>
    </div>
    <NeighborProfileDrawer
      v-model:show="showProfile"
      :neighbor="selectedNeighbor"
    />
  </section>
</template>
