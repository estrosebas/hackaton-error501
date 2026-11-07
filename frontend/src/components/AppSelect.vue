<script setup lang="ts">
import { NSelect } from 'naive-ui'

defineProps<{
  options: { label: string; value: string }[]
  label: string
  placeholder?: string
  disabled?: boolean
}>()

const value = defineModel<string | null>('value', { required: true })
</script>

<template>
  <NSelect
    v-model:value="value"
    :options="options"
    :placeholder="placeholder"
    :disabled="disabled"
    size="medium"
    role="group"
    :aria-label="label"
  />
</template>
