<script setup lang="ts">
import { shallowRef, watch } from 'vue'
import { apiImage } from '../../services/api'
import type { NotificationPerson } from '../../composables/useCommunityNotifications'
import NeighborAvatar from '../neighbors/NeighborAvatar.vue'

const props = defineProps<{ person: NotificationPerson; size: number }>()
const url = shallowRef('')

watch([() => props.person.id, () => props.person.avatar], async ([id, version], _, onCleanup) => {
  if (!version) return
  const request = new AbortController()
  onCleanup(() => {
    request.abort()
    if (url.value) URL.revokeObjectURL(url.value)
    url.value = ''
  })
  try {
    const image = await apiImage(`/neighbors/${id}/community-avatar`, request.signal)
    if (!request.signal.aborted) url.value = URL.createObjectURL(image)
  } catch {
    // The avatar can be removed or become inaccessible between refreshes.
  }
}, { immediate: true })
</script>

<template>
  <NeighborAvatar :name="person.name" :avatar="url" :size="size" />
</template>
