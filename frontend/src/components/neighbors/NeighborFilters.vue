<script setup lang="ts">
import { NInput, NSelect } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import type { NeighborFilter } from '../../composables/useNearbyNeighbors'

defineProps<{ neighborhood: string }>()
const search = defineModel<string>('search', { required: true })
const radius = defineModel<number>('radius', { required: true })
const filter = defineModel<NeighborFilter>('filter', { required: true })
const mode = defineModel<'map' | 'list'>('mode', { required: true })
const emit = defineEmits<{ changeNeighborhood: [] }>()
const radiusOptions = [
  { label: '300 m', value: 300 },
  { label: '600 m', value: 600 },
  { label: '1 km', value: 1000 },
]
const filters: { label: string; value: NeighborFilter }[] = [
  { label: 'Todos', value: 'all' },
  { label: 'Pueden ayudar', value: 'help' },
  { label: 'Actividades', value: 'activities' },
]
</script>

<template>
  <header class="neighbor-tools">
    <div class="neighbor-tools-heading">
      <div>
        <h1>Vecinos cerca de ti</h1>
        <button
          class="neighbor-area-button"
          type="button"
          @click="emit('changeNeighborhood')"
        >
          <AppIcon name="pin" :size="13" />{{ neighborhood
          }}<AppIcon name="chevron" :size="13" />
        </button>
      </div>
      <div
        class="neighbor-view-switch"
        role="group"
        aria-label="Vista de vecinos"
      >
        <button
          type="button"
          :aria-pressed="mode === 'map'"
          :class="{ selected: mode === 'map' }"
          aria-label="Ver mapa"
          @click="mode = 'map'"
        >
          <AppIcon name="map" :size="19" /></button
        ><button
          type="button"
          :aria-pressed="mode === 'list'"
          :class="{ selected: mode === 'list' }"
          aria-label="Ver lista"
          @click="mode = 'list'"
        >
          <AppIcon name="list" :size="19" />
        </button>
      </div>
    </div>
    <div class="neighbor-search-row">
      <NInput
        v-model:value="search"
        clearable
        placeholder="Buscar vecinos"
        :input-props="{
          'aria-label': 'Buscar vecinos por nombre, habilidad o interés',
        }"
        ><template #prefix
          ><AppIcon name="search" :size="17" /></template></NInput
      ><NSelect
        v-model:value="radius"
        class="neighbor-radius"
        :options="radiusOptions"
        aria-label="Distancia máxima"
      />
    </div>
    <div
      class="neighbor-filter-chips"
      role="group"
      aria-label="Filtrar vecinos"
    >
      <button
        v-for="item in filters"
        :key="item.value"
        type="button"
        :aria-pressed="filter === item.value"
        :class="{ selected: filter === item.value }"
        @click="filter = item.value"
      >
        <AppIcon v-if="item.value === 'help'" name="hand" :size="14" /><AppIcon
          v-else-if="item.value === 'activities'"
          name="calendar"
          :size="14"
        />{{ item.label }}
      </button>
    </div>
  </header>
</template>
