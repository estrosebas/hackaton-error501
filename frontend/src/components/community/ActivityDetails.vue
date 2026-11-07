<script setup lang="ts">
import { formatActivityDate, type ActivityDetails } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'

defineProps<{ details: ActivityDetails }>()
</script>

<template>
  <div class="activity-details">
    <h3>{{ details.title }}</h3>
    <div class="activity-detail-line">
      <AppIcon name="calendar" :size="16" />
      <time :datetime="details.starts_at">{{ formatActivityDate(details.starts_at) }}</time>
    </div>
    <div class="activity-detail-line">
      <AppIcon name="pin" :size="16" />
      <span>{{ details.location }}</span>
    </div>
  </div>
</template>

<style scoped>
.activity-details {
  display: grid;
  gap: 6px;
  margin-top: 12px;
  overflow-wrap: anywhere;
}
.activity-details h3 {
  margin: 0;
  color: var(--text);
  font-size: 1.0625rem;
  font-weight: 900;
}
.activity-detail-line {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--lavender);
  font-size: 0.8125rem;
}
</style>
