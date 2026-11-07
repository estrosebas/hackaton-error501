<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import { NButton } from 'naive-ui'
import AppIcon from './AppIcon.vue'

const props = withDefaults(defineProps<{
  previews: string[]
  inputLabel: string
  previewAlt: string
  maxFiles?: number
  disabled?: boolean
  loading?: boolean
  describedBy?: string
  invalid?: boolean
}>(), { maxFiles: 1 })
const emit = defineEmits<{ select: [files: File[]]; remove: [index: number] }>()
const input = useTemplateRef<HTMLInputElement>('photo-input')
const multiple = computed(() => props.maxFiles > 1)
const atLimit = computed(() => multiple.value && props.previews.length >= props.maxFiles)

function select(event: Event) {
  const field = event.currentTarget as HTMLInputElement
  const files = Array.from(field.files ?? [])
  field.value = ''
  if (files.length && !props.disabled && !props.loading && !atLimit.value) emit('select', files)
}
</script>

<template>
  <div class="photo-picker">
    <div v-if="previews.length" class="photo-picker-previews" :class="{ 'photo-picker-grid': previews.length > 1 }">
      <div v-for="(preview, index) in previews" :key="preview" class="photo-picker-preview">
        <img :src="preview" :alt="previews.length > 1 ? `${previewAlt} ${index + 1}` : previewAlt" />
        <button
          type="button"
          class="photo-picker-remove"
          :aria-label="previews.length > 1 ? `Quitar foto ${index + 1}` : 'Quitar foto'"
          :disabled="disabled || loading"
          @click="emit('remove', index)"
        >
          <AppIcon name="close" :size="18" />
        </button>
      </div>
    </div>
    <div class="photo-picker-actions">
      <input
        ref="photo-input"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        :multiple="multiple"
        :aria-label="inputLabel"
        :disabled="disabled || loading || atLimit"
        hidden
        @change="select"
      />
      <NButton
        class="photo-picker-trigger"
        attr-type="button"
        size="medium"
        secondary
        round
        :disabled="disabled || loading || atLimit"
        :loading="loading"
        :aria-describedby="describedBy"
        :aria-invalid="invalid"
        @click="input?.click()"
      >
        <template #icon><AppIcon name="camera" :size="19" /></template>
        {{ !multiple && previews.length ? 'Cambiar foto' : 'Agregar foto' }}
      </NButton>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.photo-picker {
  display: grid;
  gap: 8px;
  min-width: 0;
}
.photo-picker-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 10px;
  color: var(--muted);
  font-size: 0.75rem;
}
.photo-picker-trigger {
  min-height: 44px;
}
.photo-picker-previews {
  display: grid;
  gap: 8px;
}
.photo-picker-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.photo-picker-grid .photo-picker-preview {
  aspect-ratio: 1;
}
.photo-picker-grid img {
  height: 100%;
  object-fit: cover;
}
.photo-picker-preview {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-soft);
}
.photo-picker-preview img {
  display: block;
  width: 100%;
  max-height: 280px;
  object-fit: contain;
}
.photo-picker-remove {
  position: absolute;
  top: 8px;
  right: 8px;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--surface);
  color: var(--text);
}
</style>
