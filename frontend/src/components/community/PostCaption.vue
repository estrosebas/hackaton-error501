<script setup lang="ts">
import { computed } from 'vue'
import { postKinds, type Post } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'
import ActivityDetails from './ActivityDetails.vue'
import { currentUser } from '../../composables/useAuth'

const props = defineProps<{ post: Post }>()
const kind = computed(() => postKinds.find(item => item.value === props.post.kind)?.label)
</script>

<template>
  <div class="post-caption">
    <div v-if="kind || post.status === 'resolved'" class="post-labels">
      <span v-if="post.recommended" class="post-kind">♡ Para ti</span>
      <span v-if="post.trending" class="post-kind">↗ En tendencia en {{currentUser?.district || 'tu distrito'}}</span>
      <span v-if="kind" class="post-kind" :class="{'post-kind--emergency':post.kind==='emergency'}">{{ kind }}</span>
      <span v-if="post.status === 'resolved'" class="post-resolved">
        <AppIcon name="check" :size="14" />
        {{ post.kind === 'activity' ? 'Finalizada' : 'Atendida' }}
      </span>
    </div>
    <ActivityDetails v-if="post.activity" :details="post.activity" />
    <div v-if="post.categories?.length" class="post-categories" aria-label="Categorías">
      <span v-for="category in post.categories" :key="category">{{ category }}</span>
    </div>
    <p v-for="(paragraph, index) in post.paragraphs" :key="index">{{ paragraph }}</p>
  </div>
</template>

<style scoped>
.post-categories {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
.post-categories span {
  padding: 3px 8px;
  border: 1px solid var(--line);
  border-radius: 12px;
  color: var(--muted);
  font-size: 0.7rem;
}
.post-caption {
  display: grid;
  gap: 5px;
  overflow-wrap: anywhere;
}
.post-labels {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 10px;
}
.post-kind,
.post-resolved {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--lavender);
  font-size: 0.75rem;
  font-weight: 800;
}
.post-resolved {
  color: var(--muted);
}
.post-kind--emergency {
  color: #ff9cb0;
}
.post-caption p {
  margin: 0;
  font-size: 0.9375rem;
  line-height: 1.55;
  white-space: pre-wrap;
}
.post-caption p:empty {
  min-height: 0.5em;
}
</style>
