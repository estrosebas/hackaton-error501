<script setup lang="ts">
import { NEmpty, NButton } from 'naive-ui'
import { RouterLink } from 'vue-router'
import NeighborPost from '../components/community/NeighborPost.vue'
import ActivityRail from '../components/community/ActivityRail.vue'
import LocalPageHeading from '../components/layout/LocalPageHeading.vue'
import AppIcon from '../components/AppIcon.vue'
import {
  useCommunity,
  postKinds,
  type Activity,
} from '../composables/useCommunity'
import type { Panel } from '../components/community/CommunityPanels.vue'

const {
  neighborhood,
  visiblePosts,
  toggleWelcome,
  addComment,
  toggleSave,
  loading,
  error,
  kind,
  savedOnly,
  nextCursor,
  loadPosts,
  loadComments,
  moreComments,
  commentCursors,
  resolve,
  remove,
} = useCommunity()
const emit = defineEmits<{
  openPanel: [panel: Panel]
  selectActivity: [activity: Activity]
}>()
async function deletePost(id: string) {
  if (window.confirm('¿Retirar esta publicación de la comunidad?'))
    await remove(id)
}
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
        <RouterLink
          class="local-action local-action--quiet"
          :to="{ name: 'create' }"
        >
          <AppIcon name="plus" :size="17" /> Publicar
        </RouterLink>
      </LocalPageHeading>
    </header>
    <section class="feed-posts" aria-labelledby="feed-posts-title">
      <div class="local-section-heading">
        <h2 id="feed-posts-title">Ayuda y comunidad</h2>
        <span class="local-status"
          ><span class="live-dot" />Vecinos de tu distrito</span
        >
      </div>
      <div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 12px 0">
        <select
          v-model="kind"
          aria-label="Tipo de publicación"
          style="
            padding: 10px;
            background: var(--surface);
            color: var(--text);
            border-radius: 10px;
          "
          @change="loadPosts()"
        >
          <option value="">Toda la comunidad</option>
          <option
            v-for="item in postKinds"
            :key="item.value"
            :value="item.value"
          >
            {{ item.label }}
          </option></select
        ><label
          ><input v-model="savedOnly" type="checkbox" @change="loadPosts()" />
          Mis guardados</label
        ><NButton :loading="loading" @click="loadPosts()">Actualizar</NButton>
      </div>
      <p v-if="error" role="alert" style="color: #ffb0c0">{{ error }}</p>
      <p v-if="loading" role="status">Cargando comunidad…</p>
      <NeighborPost
        v-for="post in visiblePosts"
        :key="post.id"
        :post="post"
        @welcome="toggleWelcome"
        @comment="addComment"
        @save="toggleSave"
        @load-comments="loadComments"
        @more-comments="moreComments"
        :has-more-comments="!!commentCursors[post.id]"
        @resolve="resolve"
        @remove="deletePost"
      />
      <div v-if="!visiblePosts.length && !loading && !error" class="empty-feed">
        <NEmpty description="Todavía no hay publicaciones para este filtro">
          <template #extra>
            <RouterLink class="local-action" :to="{ name: 'create' }">
              Pide u ofrece ayuda
            </RouterLink>
          </template>
        </NEmpty>
      </div>
    </section>
    <NButton v-if="nextCursor" :loading="loading" block @click="loadPosts(true)"
      >Ver más publicaciones</NButton
    >
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
