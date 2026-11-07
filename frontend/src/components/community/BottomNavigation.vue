<script setup lang="ts">
import AppIcon, { type IconName } from '../AppIcon.vue'
import { RouterLink } from 'vue-router'
export type NavSection = 'home' | 'neighbors' | 'create' | 'market' | 'more'
defineProps<{ active?: NavSection }>()
const items: { key: NavSection; label: string; icon: IconName }[] = [
  { key: 'home', label: 'Inicio', icon: 'home' },
  { key: 'neighbors', label: 'Vecinos', icon: 'users' },
  { key: 'create', label: 'Publicar', icon: 'plus' },
  { key: 'market', label: 'Mercadito', icon: 'shop' },
  { key: 'more', label: 'Más', icon: 'more' },
]
</script>

<template>
  <nav class="bottom-navigation" aria-label="Navegación principal">
    <RouterLink
      v-for="item in items"
      :key="item.key"
      :to="{ name: item.key }"
      class="nav-item"
      :class="{
        'is-active': active === item.key,
        'create-item': item.key === 'create',
      }"
      :aria-current="active === item.key ? 'page' : undefined"
    >
      <span class="nav-icon"
        ><AppIcon
          :name="item.icon"
          :size="item.key === 'create' ? 28 : 23" /></span
      ><span>{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>
