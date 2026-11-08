<script setup lang="ts">
import AppIcon from '../AppIcon.vue'
import NeighborAvatar from '../neighbors/NeighborAvatar.vue'
import type { ChatNeighbor } from '../../composables/useChats'

defineProps<{ peer?: ChatNeighbor; name: string; group: boolean; size: number }>()
</script>

<template>
  <span v-if="group" class="group-avatar" :style="{ '--avatar-size': `${size}px` }" aria-hidden="true">
    <AppIcon name="users" :size="Math.round(size * 0.47)" />
  </span>
  <NeighborAvatar
    v-else
    class="chat-person-avatar"
    :size="size"
    :id="peer?.id"
    :name="peer?.name || name"
    :avatar="peer?.avatar"
    aria-hidden="true"
  />
</template>

<style scoped>
.group-avatar {
  display: grid;
  flex: none;
  place-items: center;
  width: var(--avatar-size);
  height: var(--avatar-size);
  border-radius: 36%;
  background: #2c2946;
  color: var(--lavender);
}

.chat-person-avatar {
  font-size: 1rem;
  background: #272c48;
}
</style>
