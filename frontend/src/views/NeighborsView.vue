<script setup lang="ts">
import NeighborsExplorer from '../components/neighbors/NeighborsExplorer.vue'
import type { Panel } from '../components/community/CommunityPanels.vue'
const emit = defineEmits<{ openPanel: [panel: Panel] }>()
</script>

<template>
  <NeighborsExplorer @open-panel="emit('openPanel', $event)" />
</template>
