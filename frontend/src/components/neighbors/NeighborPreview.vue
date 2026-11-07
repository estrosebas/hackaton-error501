<script setup lang="ts">
import { NAvatar, NButton } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import type { NearbyNeighbor } from '../../composables/useNearbyNeighbors'
defineProps<{ neighbor: NearbyNeighbor }>()
const emit = defineEmits<{ profile: [] }>()
</script>

<template>
  <article
    class="neighbor-preview"
    :aria-label="`Vecino seleccionado, ${neighbor.name}`"
    aria-live="polite"
  >
    <div class="neighbor-preview-top">
      <NAvatar
        round
        :size="49"
        :src="neighbor.avatar"
        :img-props="{ alt: '' }"
      />
      <div>
        <h2>{{ neighbor.name }}</h2>
        <p>
          <AppIcon name="pin" :size="12" />A {{ neighbor.distance }} m ·
          {{ neighbor.neighborhood }}
        </p>
      </div>
      <span class="neighbor-help-state" :class="{ available: neighbor.canHelp }"
        ><AppIcon :name="neighbor.canHelp ? 'hand' : 'users'" :size="18"
      /></span>
    </div>
    <p class="neighbor-preview-bio">{{ neighbor.bio }}</p>
    <div class="neighbor-preview-bottom">
      <span class="neighbor-skill-label">{{ neighbor.skills[0] }}</span
      ><NButton type="primary" size="small" round @click="emit('profile')"
        >Conocer a {{ neighbor.name }}<AppIcon name="arrow" :size="15"
      /></NButton>
    </div>
  </article>
</template>
