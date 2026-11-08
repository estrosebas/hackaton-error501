<script setup lang="ts">
import AppIcon from '../AppIcon.vue'

defineProps<{ title: string; titleId: string; neighborhood: string }>()
const emit = defineEmits<{ changeNeighborhood: [] }>()
</script>

<template>
  <div class="local-heading">
    <div>
      <h1 :id="titleId">{{ title }}</h1>
      <button
        class="local-area"
        type="button"
        :aria-label="`Cambiar barrio. Barrio actual: ${neighborhood}`"
        @click="emit('changeNeighborhood')"
      >
        <AppIcon name="pin" :size="14" />
        {{ neighborhood }}
        <AppIcon name="chevron" :size="14" />
      </button>
    </div>
    <slot />
  </div>
</template>

<style scoped>
.local-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.local-heading h1 {
  margin: 0;
  color: var(--text);
  font-size: 1.4375rem;
  font-weight: 900;
  letter-spacing: -0.025em;
  line-height: 1.2;
  text-wrap: balance;
}
.local-area {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 44px;
  padding: 0;
  border: 0;
  color: var(--lavender);
  background: transparent;
  font-size: 0.8125rem;
  font-weight: 800;
  text-align: start;
}
.local-area > svg:first-child {
  color: var(--coral);
}
.local-area:hover {
  color: var(--text);
}
</style>
