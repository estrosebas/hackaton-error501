<script setup lang="ts">
import { ref,computed } from 'vue'
import { NButton } from 'naive-ui'
import FeedInterestsDialog from './FeedInterestsDialog.vue'
import { currentUser } from '../../composables/useAuth'
import { useFeedTopics } from '../../composables/useFeedTopics'
const show=ref(false),{topics}=useFeedTopics()
const chosen=computed(()=>topics.value.filter(topic=>currentUser.value?.feed_interests?.includes(topic.id)))
</script>
<template>
  <section class="feed-interests-card"><h2>Mis gustos del feed</h2><p>Primero, lo que va contigo. Siempre dentro de tu distrito.</p><div class="chosen-topics"><span v-for="topic in chosen" :key="topic.id">{{topic.emoji}} {{topic.label}}</span><span v-if="!chosen.length">Todavía no elegiste favoritos.</span></div><NButton secondary round @click="show=true">Cambiar mis gustos</NButton><FeedInterestsDialog v-model:show="show" /></section>
</template>
<style scoped>
.feed-interests-card{padding:20px;border:1px solid var(--line);border-radius:18px;background:var(--surface-soft);margin-top:24px}.feed-interests-card h2{font-size:1.125rem;margin:0}.feed-interests-card p{font-size:.8125rem;line-height:1.6;color:var(--muted)}.chosen-topics{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 18px}.chosen-topics span{font-size:.8125rem;padding:7px 10px;border-radius:16px;background:var(--surface);color:var(--lavender)}
</style>
