<script setup lang="ts">
import { NSelect } from 'naive-ui'
function hideDecorativeSuffix(element: HTMLElement) {
  // Naive labels the arrow wrapper "loading" even when its spinner is inactive.
  element.querySelectorAll('.n-base-suffix[aria-label="loading"]').forEach(node => {
    node.removeAttribute('aria-label')
    node.setAttribute('aria-hidden', 'true')
  })
}
const vDecorativeSuffix = { mounted: hideDecorativeSuffix, updated: hideDecorativeSuffix }

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
    v-decorative-suffix
    v-model:value="value"
    :options="options"
    :placeholder="placeholder"
    :disabled="disabled"
    size="medium"
    role="group"
    :aria-label="label"
  />
</template>
