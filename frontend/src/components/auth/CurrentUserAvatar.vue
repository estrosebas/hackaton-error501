<script setup lang="ts">
import { computed } from 'vue'
import { NAvatar } from 'naive-ui'
import { currentUser } from '../../composables/useAuth'
import { useProfileAvatar } from '../../composables/useProfileAvatar'

defineProps<{ size: number }>()
const { url } = useProfileAvatar()
const initial = computed(() => currentUser.value?.name.trim().charAt(0) || '')
</script>

<template>
  <NAvatar round :size="size" :src="url" :img-props="{ alt: '' }" class="account-avatar">
    <template v-if="!url" #default>{{ initial }}</template>
    <template #fallback>{{ initial }}</template>
  </NAvatar>
</template>

<style scoped>
.account-avatar {
  color: var(--lavender);
  background: var(--surface-raised);
  font-weight: 900;
  flex-shrink: 0;
}
</style>
