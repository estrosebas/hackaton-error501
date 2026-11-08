<script setup lang="ts">
import AppIcon from '../AppIcon.vue'
import type { EventFrame } from '../../composables/useMapEvents'

defineProps<{ frames: EventFrame[]; points: number; glyph?: string }>()
const selected = defineModel<string>({ required: true })
</script>

<template>
  <details class="map-frame-picker">
    <summary>
      <span><AppIcon name="heart" :size="19" /> Marco del evento <small>Opcional</small></span>
      <AppIcon name="chevron" :size="18" />
    </summary>
    <p>Tienes {{ points }} puntos de empatía. Cada 10 puntos desbloqueas un marco.</p>
    <div class="map-frame-options" role="group" aria-label="Marcos del evento">
      <button type="button" :aria-pressed="selected === ''" @click="selected = ''">
        <span class="map-frame-sample"><AppIcon name="minus" :size="18" /></span>
        Sin marco
      </button>
      <button v-for="frame in frames" :key="frame.id" type="button" :disabled="!frame.unlocked" :aria-pressed="selected === frame.id" @click="selected = frame.id">
        <span class="map-frame-sample" :style="{ borderColor: frame.color, borderStyle: frame.style }">{{ glyph }}</span>
        {{ frame.label }}
        <small v-if="!frame.unlocked"><AppIcon name="lock" :size="12" /> {{ frame.points }} puntos</small>
      </button>
    </div>
  </details>
</template>

<style scoped>
.map-frame-picker summary, .map-frame-picker summary > span { display: flex; align-items: center; gap: 8px; }
.map-frame-picker summary { justify-content: space-between; min-height: 44px; cursor: pointer; font-weight: 900; }
.map-frame-picker summary::-webkit-details-marker { display: none; }
.map-frame-picker summary svg { flex-shrink: 0; color: var(--coral); }
.map-frame-picker[open] summary > svg { transform: rotate(180deg); }
.map-frame-picker p, .map-frame-picker small { color: var(--muted); font-size: 0.75rem; line-height: 1.5; }
.map-frame-picker p { margin: 4px 0 12px; }
.map-frame-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 105px), 1fr)); gap: 8px; }
.map-frame-options button {
  display: grid;
  justify-items: center;
  align-content: start;
  gap: 7px;
  padding: 10px 6px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-soft);
  color: var(--text);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
}
.map-frame-options button[aria-pressed='true'], .map-frame-options button:hover:not(:disabled) { border-color: var(--coral); }
.map-frame-options button:disabled { color: var(--muted); cursor: default; }
.map-frame-sample { display: grid; place-items: center; width: 40px; height: 40px; border: 3px solid var(--line); border-radius: 50%; font-size: 1.125rem; }
.map-frame-options small { display: inline-flex; align-items: center; gap: 3px; }
</style>
