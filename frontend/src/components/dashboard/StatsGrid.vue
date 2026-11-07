<script setup lang="ts">
import { NGi, NGrid } from 'naive-ui'
import type { StatItem } from '../../types'
import StatCard from './StatCard.vue'

defineProps<{ items: StatItem[] }>()
</script>

<template>
  <NGrid responsive="screen" cols="1 s:2 l:4" :x-gap="16" :y-gap="16">
    <NGi v-for="item in items" :key="item.id">
      <StatCard :label="item.label" :value="item.value" :hint="item.hint" :accent="item.accent" />
    </NGi>
  </NGrid>
</template>
