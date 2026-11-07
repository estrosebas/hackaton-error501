<script setup lang="ts">
import { NButton, NCard, NEmpty, NTag } from 'naive-ui'

defineProps<{ moduleName: string }>()

const emit = defineEmits<{ back: [] }>()
</script>

<template>
  <NCard class="coming-soon">
    <NEmpty :description="`El módulo “${moduleName}” todavía no tiene contenido`">
      <template #extra>
        <NTag round :bordered="false" type="info">Próximamente</NTag>
      </template>
    </NEmpty>
    <div class="hint">
      <p class="hint-text">Mientras tanto, el dashboard ya está operativo con datos de ejemplo.</p>
      <NButton text type="primary" @click="emit('back')">Volver al dashboard</NButton>
    </div>
  </NCard>
</template>

<style scoped>
.coming-soon {
  max-width: 1240px;
  margin: 0 auto;
}

.hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding-top: 8px;
}

.hint-text {
  margin: 0;
  font-size: 12.5px;
  opacity: 0.6;
}
</style>
