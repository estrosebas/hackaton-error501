<script setup lang="ts">
import { RouterLink } from 'vue-router'
import AppIcon from '../AppIcon.vue'
import { navigationItems, type NavSection } from './navigation'

defineProps<{ active?: NavSection }>()
</script>

<template>
  <nav class="bottom-navigation" aria-label="Navegación principal">
    <RouterLink
      v-for="item in navigationItems"
      :key="item.key"
      :to="{ name: item.key }"
      :data-tour="item.key"
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
