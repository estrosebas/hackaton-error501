<script setup lang="ts">
import { shallowRef, watch } from 'vue'
import { NAvatar, NButton, NEmpty } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import NeighborFilters from './NeighborFilters.vue'
import NearbyMap from './NearbyMap.vue'
import NeighborPreview from './NeighborPreview.vue'
import NeighborProfileDrawer from './NeighborProfileDrawer.vue'
import { useCommunity } from '../../composables/useCommunity'
import { useNearbyNeighbors } from '../../composables/useNearbyNeighbors'
import type { Panel } from '../community/CommunityPanels.vue'
import './nearby-neighbors.css'

const { neighborhood } = useCommunity()
const {
  search,
  radius,
  filter,
  selectedId,
  mode,
  center,
  filteredNeighbors,
  selectedNeighbor,
} = useNearbyNeighbors(neighborhood)
const showProfile = shallowRef(false)
const emit = defineEmits<{ openPanel: [panel: Panel] }>()

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
  selectedId.value = 'miguel'
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
        :selected-id="selectedNeighbor?.id"
        :radius="radius"
        @select="selectedId = $event"
      />
      <div class="neighbor-map-count" role="status">
        <span />{{ filteredNeighbors.length }}
        {{
          filteredNeighbors.length === 1
            ? 'vecino cerca de ti'
            : 'vecinos cerca de ti'
        }}
      </div>
      <NeighborPreview
        v-if="selectedNeighbor"
        :neighbor="selectedNeighbor"
        @profile="showProfile = true"
      />
      <div v-else class="neighbor-map-empty">
        <NEmpty description="No encontramos vecinos con esos filtros"
          ><template #extra
            ><NButton size="small" @click="resetFilters"
              >Ver todos los vecinos</NButton
            ></template
          ></NEmpty
        >
      </div>
    </div>
    <div v-else class="neighbor-results-list">
      <div class="neighbor-results-heading">
        <span>{{ filteredNeighbors.length }} vecinos en tu zona</span
        ><span>Datos de ejemplo</span>
      </div>
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
        /><span
          ><strong>{{ person.name }}</strong
          ><small>{{ person.skills.join(' · ') }}</small
          ><span class="neighbor-result-meta"
            ><AppIcon name="pin" :size="12" />A {{ person.distance }} m<span
              v-if="person.canHelp"
              class="neighbor-can-help"
              >Puede ayudar</span
            ></span
          ></span
        ><AppIcon name="arrow" :size="18" /></button
      ><NEmpty
        v-if="!filteredNeighbors.length"
        description="No encontramos vecinos"
        ><template #extra
          ><NButton @click="resetFilters">Limpiar filtros</NButton></template
        ></NEmpty
      >
    </div>
    <NeighborProfileDrawer
      v-model:show="showProfile"
      :neighbor="selectedNeighbor"
    />
  </section>
</template>
