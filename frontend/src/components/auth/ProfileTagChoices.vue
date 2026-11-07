<script setup lang="ts">
import AppIcon, { type IconName } from '../AppIcon.vue'

defineProps<{ label: string; icon: IconName; options: string[]; disabled: boolean }>()
const selected = defineModel<string[]>({ required: true })
const limit = 5

function toggle(option: string) {
  if (selected.value.includes(option)) {
    selected.value = selected.value.filter(value => value !== option)
  } else if (selected.value.length < limit) {
    selected.value = [...selected.value, option]
  }
}
</script>

<template>
  <fieldset class="tag-choices" :disabled="disabled">
    <legend><AppIcon :name="icon" :size="16" /> {{ label }}</legend>
    <div class="tag-choices-list">
      <button
        v-for="option in options"
        :key="option"
        type="button"
        class="profile-tag"
        :aria-pressed="selected.includes(option)"
        :disabled="selected.length >= limit && !selected.includes(option)"
        @click="toggle(option)"
      >
        <AppIcon :name="selected.includes(option) ? 'check' : 'plus'" :size="14" />
        {{ option }}
      </button>
    </div>
    <small v-if="selected.length" role="status">{{ selected.length }} de {{ limit }} elegidas</small>
  </fieldset>
</template>

<style scoped>
.tag-choices {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.tag-choices legend {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 9px;
  padding: 0;
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 900;
}
.tag-choices legend svg {
  color: var(--coral);
}
.tag-choices-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.profile-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 44px;
  padding: 5px 10px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--surface-soft);
  color: var(--lavender);
  font-size: 0.8125rem;
  font-weight: 800;
  text-align: start;
}
.profile-tag:hover:not(:disabled) {
  border-color: var(--lavender);
}
.profile-tag[aria-pressed="true"] {
  border-color: #a75e78;
  background: #3a2339;
  color: #ffadc4;
}
.profile-tag:disabled {
  opacity: 0.55;
  cursor: default;
}
.tag-choices small {
  display: block;
  margin-top: 6px;
  color: var(--muted);
  font-size: 0.75rem;
}
</style>
