<script setup lang="ts">
import { RouterLink } from 'vue-router'
import FeedPosts from '../components/community/FeedPosts.vue'
import LocalPageHeading from '../components/layout/LocalPageHeading.vue'
import AppIcon from '../components/AppIcon.vue'
import { useCommunity } from '../composables/useCommunity'
import type { Panel } from '../components/community/CommunityPanels.vue'

const { neighborhood } = useCommunity()
const emit = defineEmits<{
  openPanel: [panel: Panel]
  selectActivity: [id: string]
}>()
</script>

<template>
  <section class="local-page" aria-labelledby="feed-title">
    <header class="local-toolbar">
      <LocalPageHeading
        title="Nos ayudamos por aquí"
        title-id="feed-title"
        :neighborhood="neighborhood"
        @change-neighborhood="emit('openPanel', 'neighborhood')"
      >
        <RouterLink class="local-action local-action--quiet" :to="{ name: 'create' }">
          <AppIcon name="plus" :size="17" /> Publicar
        </RouterLink>
      </LocalPageHeading>
    </header>
    <FeedPosts
      @select-activity="emit('selectActivity', $event)"
      @open-activities="emit('openPanel', 'activities')"
    />
    <div class="community-note">
      <AppIcon name="home" :size="17" />
      <span>Un barrio se construye entre todos.</span>
    </div>
  </section>
</template>
