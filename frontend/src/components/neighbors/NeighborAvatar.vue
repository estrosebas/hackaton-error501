<script setup lang="ts">
import { NAvatar } from 'naive-ui'
import { computed } from 'vue'
import { currentUser } from '../../composables/useAuth'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'

const props = defineProps<{ id?: number; name?: string | null; avatar?: string; size: number }>()
const initial = computed(() => props.name?.trim().charAt(0) || '?')
</script>

<template>
  <CurrentUserAvatar v-if="id !== undefined && id === currentUser?.id" :size="size" />
  <NAvatar v-else round :size="size" :src="avatar || undefined" :img-props="{ alt: '' }" class="neighbor-avatar">
    <template v-if="!avatar" #default>{{ initial }}</template>
    <template #fallback>{{ initial }}</template>
  </NAvatar>
</template>

<style scoped>
.neighbor-avatar { flex-shrink: 0; color: var(--lavender); background: var(--surface-raised); font-weight: 900; }
</style>
