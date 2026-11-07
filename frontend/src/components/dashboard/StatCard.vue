<script setup lang="ts">
import { NCard } from 'naive-ui'

defineProps<{
  label: string
  value: number
  hint: string
  accent: string
}>()
</script>

<template>
  <NCard size="small" class="stat-card">
    <div class="stat">
      <div class="stat-head">
        <span class="stat-dot" :style="{ backgroundColor: accent }" />
        <span class="stat-label">{{ label }}</span>
      </div>
      <p class="stat-value">{{ value }}</p>
      <p class="stat-hint">{{ hint }}</p>
    </div>
  </NCard>
</template>

<style scoped>
.stat-card {
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16);
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-head {
  display: flex;
  align-items: center;
  gap: 7px;
}

.stat-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
}

.stat-label {
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.6;
}

.stat-value {
  margin: 2px 0 0;
  font-size: 32px;
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.03em;
}

.stat-hint {
  margin: 0;
  font-size: 12px;
  opacity: 0.55;
}
</style>
