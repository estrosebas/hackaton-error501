<script setup lang="ts">
import { shallowRef, watch } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../AppIcon.vue'
import NeighborAvatar from '../neighbors/NeighborAvatar.vue'
import { currentUser } from '../../composables/useAuth'
import { api, apiImage } from '../../services/api'

interface Neighbor {
  id: number
  name: string
  avatar: string
  has_community_avatar: boolean
}

const neighbors = shallowRef<Neighbor[]>([])
const photos = shallowRef<Record<number, string>>({})

watch([
  () => currentUser.value?.id,
  () => currentUser.value?.ubigeo,
  () => currentUser.value?.neighborhood,
], async ([id, district, neighborhood], _, onCleanup) => {
  neighbors.value = []
  photos.value = {}
  if (!id || !district || !neighborhood?.trim()) return

  const controller = new AbortController()
  const { signal } = controller
  const urls: string[] = []
  onCleanup(() => {
    controller.abort()
    urls.forEach(url => URL.revokeObjectURL(url))
  })

  const result = await api<{ data: Neighbor[] }>('/neighbors?nearby=1', { signal }).catch(() => null)
  if (!result || signal.aborted) return
  neighbors.value = result.data

  await Promise.all(result.data.filter(person => person.has_community_avatar).map(async person => {
    const photo = await apiImage(`/neighbors/${person.id}/community-avatar`, signal).catch(() => null)
    if (!photo || signal.aborted) return
    const url = URL.createObjectURL(photo)
    urls.push(url)
    photos.value = { ...photos.value, [person.id]: url }
  }))
}, { immediate: true })
</script>

<template>
  <div v-if="neighbors.length" class="map-neighbors" role="group" aria-label="Vecinos de tu barrio">
    <span class="map-neighbors-label"><AppIcon name="users" :size="17" /> Vecinos de tu barrio</span>
    <div class="map-neighbor-links">
      <RouterLink
        v-for="person in neighbors"
        :key="person.id"
        :to="{ name: 'neighbor-profile', params: { neighborId: person.id } }"
        :aria-label="`Ver perfil de ${person.name}`"
        :title="person.name"
      >
        <NeighborAvatar :name="person.name" :avatar="photos[person.id] || person.avatar" :size="34" />
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.map-neighbors {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 2px 12px;
  margin-top: 8px;
  padding-top: 5px;
  border-top: 1px solid var(--line);
}
.map-neighbors-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: var(--lavender);
  font-size: 0.75rem;
  font-weight: 800;
}
.map-neighbor-links { display: flex; flex-wrap: wrap; }
.map-neighbor-links a {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
}
.map-neighbor-links a :deep(.neighbor-avatar) { border: 1px solid var(--line); }
.map-neighbor-links a:hover :deep(.neighbor-avatar) { border-color: var(--coral); }
</style>
