<script setup lang="ts">
import { NButton, NEmpty, useMessage } from 'naive-ui'
import { useRouter } from 'vue-router'
import NeighborPost from '../components/community/NeighborPost.vue'
import ActivityRail from '../components/community/ActivityRail.vue'
import AppIcon from '../components/AppIcon.vue'
import { useCommunity, type Activity } from '../composables/useCommunity'
import type { Panel } from '../components/community/CommunityPanels.vue'

const { posts, visiblePosts, toggleWelcome, addComment } = useCommunity()
const router = useRouter()
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
  <div>
    <div class="feed-heading">
      <h1>La vida por aquí</h1>
      <span><span class="live-dot" />Tu comunidad, más cerca</span>
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
      <NEmpty description="El primer saludo puede ser el tuyo"
        ><template #extra
          ><NButton type="primary" @click="router.push({ name: 'create' })"
            >Saluda a tu barrio</NButton
          ></template
        ></NEmpty
      >
    </div>
    <ActivityRail
      @select="emit('selectActivity', $event)"
      @show-all="emit('openPanel', 'activities')"
    />
    <div class="community-note">
      <AppIcon name="home" :size="17" /><span
        >Un barrio se construye entre todos.</span
      >
    </div>
  </div>
</template>
