<script setup lang="ts">
import { NAvatar, NBadge, NButton, NTag, NTooltip } from 'naive-ui'

defineProps<{
  title: string
  themeMode: 'light' | 'dark'
}>()

const emit = defineEmits<{ toggleTheme: [] }>()
</script>

<template>
  <header class="app-header">
    <div class="header-leading">
      <p class="header-eyebrow">Espacio de trabajo</p>
      <h1 class="header-title">{{ title }}</h1>
    </div>

    <div class="header-actions">
      <NTag size="small" round :bordered="false" type="success">Sprint activo</NTag>

      <NTooltip>
        <template #trigger>
          <NButton quaternary circle size="small" class="theme-button" @click="emit('toggleTheme')">
            <span class="theme-icon">{{ themeMode === 'dark' ? '☀️' : '🌙' }}</span>
          </NButton>
        </template>
        Cambiar a modo {{ themeMode === 'dark' ? 'claro' : 'oscuro' }}
      </NTooltip>

      <NBadge dot type="success" :offset="[-4, 4]">
        <NAvatar round size="small" class="user-avatar">NM</NAvatar>
      </NBadge>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: 72px;
  padding: 0 32px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.16);
}

.header-eyebrow {
  margin: 0;
  font-size: 10.5px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  opacity: 0.45;
}

.header-title {
  margin: 2px 0 0;
  font-size: 18px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.theme-icon {
  font-size: 14px;
  line-height: 1;
}

.user-avatar {
  background: linear-gradient(135deg, #6366f1, #22d3ee);
  font-size: 11px;
  font-weight: 700;
}
</style>
