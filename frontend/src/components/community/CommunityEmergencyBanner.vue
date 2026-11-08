<script setup lang="ts">
import AppIcon from '../AppIcon.vue'

defineProps<{ count: number }>()
defineEmits<{ open: [] }>()
</script>

<template>
  <aside v-if="count" class="emergency-banner" aria-label="Emergencias del distrito">
    <button type="button" @click="$emit('open')">
      <AppIcon name="bell" :size="20" />
      <span>{{ count === 1 ? 'Emergencia en tu distrito' : `${count} emergencias en tu distrito` }}</span>
      <AppIcon name="arrow" :size="18" class="emergency-arrow" />
    </button>
  </aside>
</template>

<style scoped>
.emergency-banner {
  position: sticky;
  z-index: 9;
  top: env(safe-area-inset-top, 0px);
  flex: none;
  border-block-end: 1px solid var(--local-accent-line);
  background: var(--local-accent-fill);
}
.emergency-banner button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 48px;
  padding: 8px 16px;
  border: 0;
  background: transparent;
  color: var(--local-accent-text);
  font-weight: 900;
  text-align: start;
}
.emergency-banner button:focus-visible {
  outline-offset: -3px;
}
.emergency-arrow {
  margin-inline-start: auto;
}
</style>
