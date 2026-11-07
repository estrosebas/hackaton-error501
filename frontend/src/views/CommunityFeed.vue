<script setup lang="ts">
import { NEmpty, useMessage } from 'naive-ui'
import { RouterLink } from 'vue-router'
import NeighborPost from '../components/community/NeighborPost.vue'
import ActivityRail from '../components/community/ActivityRail.vue'
import LocalPageHeading from '../components/layout/LocalPageHeading.vue'
import AppIcon from '../components/AppIcon.vue'
import { useCommunity, type Activity } from '../composables/useCommunity'
import type { Panel } from '../components/community/CommunityPanels.vue'

const { neighborhood, posts, visiblePosts, toggleWelcome, addComment } = useCommunity()
const message = useMessage()
const emit = defineEmits<{
  openPanel: [panel: Panel]
  selectActivity: [activity: Activity]
}>()
function savePost(id: string) {
  const post = posts.value.find((item) => item.id === id)
  if (!post) return
  post.saved = !post.saved
  message.success(
    post.saved ? 'Publicación guardada' : 'Publicación retirada de guardados',
  )
}
</script>

<template>
  <section class="local-page" aria-labelledby="feed-title">
    <header class="local-toolbar">
      <LocalPageHeading
        title="La vida por aquí"
        title-id="feed-title"
        :neighborhood="neighborhood"
        @change-neighborhood="emit('openPanel', 'neighborhood')"
      >
        <RouterLink class="local-action local-action--quiet" :to="{ name: 'create' }">
          <AppIcon name="plus" :size="17" /> Publicar
        </RouterLink>
      </LocalPageHeading>
    </header>
    <section class="feed-posts" aria-labelledby="feed-posts-title">
      <div class="local-section-heading">
        <h2 id="feed-posts-title">Publicaciones</h2>
        <span class="local-status"><span class="live-dot" />Tu comunidad, más cerca</span>
      </div>
      <NeighborPost
        v-for="post in visiblePosts"
        :key="post.id"
        :post="post"
        @welcome="toggleWelcome"
        @comment="addComment"
        @save="savePost"
      />
      <div v-if="!visiblePosts.length" class="empty-feed">
        <NEmpty description="El primer saludo puede ser el tuyo">
          <template #extra>
            <RouterLink class="local-action" :to="{ name: 'create' }">
              Saluda a tu barrio
            </RouterLink>
          </template>
        </NEmpty>
      </div>
    </section>
    <ActivityRail
      @select="emit('selectActivity', $event)"
      @show-all="emit('openPanel', 'activities')"
    />
    <div class="community-note">
      <AppIcon name="home" :size="17" /><span
        >Un barrio se construye entre todos.</span
      >
    </div>
  </section>
</template>
