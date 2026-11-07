<script setup lang="ts">
import { NInput } from 'naive-ui'

defineProps<{ disabled: boolean; invalidDate: boolean }>()
const title = defineModel<string>('title', { required: true })
const starts = defineModel<string>('starts', { required: true })
const location = defineModel<string>('location', { required: true })
</script>

<template>
  <div class="activity-fields">
    <div class="activity-field">
      <label for="activity-title-input">Nombre de la actividad</label>
      <NInput
        v-model:value="title"
        :input-props="{ id: 'activity-title-input' }"
        :disabled="disabled"
        :maxlength="100"
        placeholder="Ej. Caminata vecinal"
      />
    </div>
    <div class="activity-field">
      <label for="activity-start-input">Fecha y hora</label>
      <input
        id="activity-start-input"
        v-model="starts"
        type="datetime-local"
        :disabled="disabled"
        :aria-invalid="invalidDate"
        aria-describedby="activity-time-hint"
      />
      <p id="activity-time-hint" :class="{ 'activity-field-error': invalidDate }">
        {{ invalidDate ? 'Elige una fecha y hora futuras.' : 'En la hora local de tu dispositivo.' }}
      </p>
    </div>
    <div class="activity-field">
      <label for="activity-location-input">Punto de encuentro</label>
      <NInput
        v-model:value="location"
        :input-props="{ id: 'activity-location-input' }"
        :disabled="disabled"
        :maxlength="160"
        placeholder="Ej. Entrada principal del parque"
      />
    </div>
  </div>
</template>

<style scoped>
.activity-fields,
.activity-field {
  display: grid;
  min-width: 0;
}
.activity-fields {
  gap: 14px;
}
.activity-field {
  gap: 6px;
}
.activity-field label {
  font-size: 0.875rem;
  font-weight: 800;
}
.activity-field input[type="datetime-local"] {
  width: 100%;
  min-width: 0;
  min-height: 44px;
  padding-inline: 11px;
  border: 1px solid var(--line);
  border-radius: 14px;
  color: var(--text);
  background: var(--surface-soft);
  font: 0.875rem var(--font);
}
.activity-field input[type="datetime-local"]:focus-visible {
  outline: 2px solid var(--coral);
  outline-offset: 2px;
}
.activity-field p {
  margin: 0;
  color: var(--muted);
  font-size: 0.75rem;
}
.activity-field .activity-field-error {
  color: var(--local-accent-text, #ff9cb7);
}
</style>
