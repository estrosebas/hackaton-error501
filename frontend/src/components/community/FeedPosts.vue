<script setup lang="ts">
import { computed, ref, watch, nextTick, onBeforeUnmount } from 'vue'
import AppIcon from '../AppIcon.vue'
import AppSelect from '../AppSelect.vue'
import { currentUser } from '../../composables/useAuth'
import NeighborPost from './NeighborPost.vue'
import MapEventRail from '../events/MapEventRail.vue'
import { NModal, NCard, NButton, useMessage } from 'naive-ui'
import {
  postKinds,
  useCommunity,
  type Post,
} from '../../composables/useCommunity'

const emit = defineEmits<{ selectActivity: [id: string]; openActivities: [] }>()
const {
  posts,
  toggleWelcome,
  addComment,
  toggleSave,
  loading,
  hasLoaded,
  error,
  kind,
  savedOnly, searchText, categoryFilter,
  nextCursor,
  loadPosts,
  loadComments,
  moreComments,
  commentCursors,
  resolve,
  remove,
  loadActivities,
  toggleCommentLike,
  retryMore,
} = useCommunity()
const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | undefined
function loadVisible() {
  if (
    nextCursor.value &&
    !loading.value &&
    !error.value &&
    sentinel.value &&
    sentinel.value.getBoundingClientRect().top < innerHeight + 400
  )
    void loadPosts(true)
}
watch(
  sentinel,
  (el) => {
    observer?.disconnect()
    if (el && typeof IntersectionObserver !== 'undefined') {
      observer = new IntersectionObserver(
        (entries) => {
          if (entries.some((e) => e.isIntersecting)) loadVisible()
        },
        { rootMargin: '400px' },
      )
      observer.observe(el)
    }
  },
  { flush: 'post' },
)
watch([loading, nextCursor], async () => {
  await nextTick()
  loadVisible()
})
onBeforeUnmount(() => observer?.disconnect())
const filtered = computed(() => Boolean(kind.value || savedOnly.value || searchText.value.trim() || categoryFilter.value.trim()))
const draftSearch=ref(searchText.value),draftCategory=ref(categoryFilter.value)
const categorySuggestions=computed(()=>[...new Set(posts.value.flatMap(post=>post.categories||[]))])
function searchFeed(){if(loading.value)return;searchText.value=draftSearch.value;categoryFilter.value=draftCategory.value;refresh()}
const kindOptions = [{ label: 'Todas', value: '' }, ...postKinds]
const timeline = computed(() => {
  const items: { key: string; post?: Post }[] = posts.value
    .map((post) => ({ key: post.id, post }))
  return items
})

function refresh() {
  if (loading.value) return
  void loadPosts()
  if (!filtered.value) void loadActivities()
}

function changeKind(value: string | null) {
  kind.value = value ?? ''
  void loadPosts()
}

function toggleSaved() {
  savedOnly.value = !savedOnly.value
  void loadPosts()
}

function clearFilters() {
  kind.value = ''
  savedOnly.value = false
  searchText.value='';categoryFilter.value='';draftSearch.value='';draftCategory.value=''
  void loadPosts()
}

const message = useMessage()
const deletingId = ref<string | null>(null)
const deleting = ref(false)
function deletePost(id: string) { if (!deleting.value) deletingId.value = id }
async function confirmDelete() {
  if (!deletingId.value || deleting.value) return
  deleting.value = true
  try {
    if (await remove(deletingId.value)) {
      deletingId.value = null
      message.success('Publicación retirada.')
    } else message.error(error.value || 'No pudimos retirar la publicación. Intenta nuevamente.')
  } finally { deleting.value = false }
}
</script>

<template>
  <section class="feed-posts" aria-labelledby="feed-posts-title">
    <MapEventRail v-if="!filtered" @select-activity="emit('selectActivity', $event)" @open-agenda="emit('openActivities')" />
    <NModal :show="deletingId !== null" :mask-closable="!deleting" @update:show="value => { if (!value && !deleting) deletingId = null }">
      <NCard title="¿Retirar esta publicación?" role="dialog" aria-modal="true" aria-label="Retirar publicación" style="width:min(92vw,420px)">
        Dejará de aparecer en el feed de tus vecinos.
        <template #footer><div style="display:flex;justify-content:flex-end;gap:12px"><NButton :disabled="deleting" @click="deletingId = null">Cancelar</NButton><NButton type="error" :loading="deleting" @click="confirmDelete">Retirar publicación</NButton></div></template>
      </NCard>
    </NModal>
    <header class="feed-heading">
      <div class="feed-heading-icon"><AppIcon name="chat" :size="20" /></div>
      <div>
        <h2 id="feed-posts-title">Ayuda y comunidad</h2>
        <p>{{currentUser?.feed_interests?.length ? 'Para ti · Gustos y publicaciones recientes de tu distrito' : 'Publicaciones de tu distrito'}}</p>
      </div>
    </header>
    <form class="feed-search" @submit.prevent="searchFeed"><label>Palabras<input v-model="draftSearch" maxlength="100" placeholder="Ej. reparar una llanta" /></label><label>Categoría<input v-model="draftCategory" list="feed-categories" maxlength="100" placeholder="Ej. Mecánica, Música" /></label><datalist id="feed-categories"><option v-for="item in categorySuggestions" :key="item" :value="item"/></datalist><button type="submit" :disabled="loading">Buscar</button><p v-if="categoryFilter.trim()">Primero los vecinos con más vistos buenos. El respaldo es al vecino, no una certificación del servicio.</p></form><div class="feed-filters">
      <AppSelect
        class="feed-kind"
        :value="kind"
        :options="kindOptions"
        label="Tipo de publicación"
        @update:value="changeKind"
      />
      <button
        type="button"
        class="feed-saved"
        :aria-pressed="savedOnly"
        @click="toggleSaved"
      >
        <AppIcon name="bookmark" :size="16" /> Guardados
      </button>
      <button
        type="button"
        class="feed-refresh"
        aria-label="Actualizar publicaciones"
        title="Actualizar publicaciones"
        :aria-disabled="loading"
        @click="refresh"
      >
        <AppIcon name="refresh" :size="17" />
      </button>
    </div>

    <div v-if="error" class="feed-state feed-state--error">
      <span role="alert">{{ error }}</span>
      <button class="text-link" type="button" @click="loadPosts(retryMore)">
        Reintentar
      </button>
    </div>
    <p v-if="(!hasLoaded || loading) && !posts.length" class="feed-state" role="status">
      Cargando publicaciones…
    </p>
    <div v-else-if="hasLoaded && !posts.length && !error" class="feed-state">
      <strong>{{
        filtered
          ? 'Sin publicaciones para este filtro'
          : 'El muro está esperando tu primera publicación'
      }}</strong>
      <p v-if="!filtered">
        Pide ayuda, comparte una idea o invita a tus vecinos a un plan.
      </p>
      <button v-else class="text-link" type="button" @click="clearFilters">
        Limpiar filtros
      </button>
    </div>

    <div v-if="timeline.length" class="feed-list" :aria-busy="loading">
      <template v-for="item in timeline" :key="item.key">
        <NeighborPost
          v-if="item.post"
          :post="item.post"
          :has-more-comments="!!commentCursors[item.post.id]"
          @welcome="toggleWelcome"
          @comment="addComment"
          @save="toggleSave"
          @load-comments="loadComments"
          @more-comments="moreComments"
          @resolve="resolve"
          @remove="deletePost"
          @comment-like="toggleCommentLike"
        />
      </template>
      <div ref="sentinel" class="feed-scroll-state" role="status">
        <span v-if="loading">Cargando publicaciones…</span
        ><span v-else-if="!nextCursor && posts.length"
          >Ya estás al día con tu comunidad</span
        >
      </div>
    </div>
  </section>
</template>

<style scoped>.feed-search{display:flex;flex-wrap:wrap;gap:10px;padding:12px var(--local-gutter)}.feed-search label{display:grid;gap:5px;flex:1;min-width:120px;font-size:11px;color:var(--muted)}.feed-search input{min-width:0;width:100%;padding:10px;border:1px solid var(--line);border-radius:12px;background:var(--surface-soft);color:var(--text)}.feed-search button{align-self:end;padding:10px;border:1px solid var(--line);border-radius:12px;background:var(--surface-raised);color:var(--lavender)}.feed-search p{width:100%;font-size:11px;color:var(--muted);margin:0}
.feed-scroll-state {
  min-height: 65px;
  padding: 16px;
  text-align: center;
  color: var(--muted);
  font-size: 0.75rem;
}
.feed-posts {
  display: grid;
  gap: 12px;
  padding: 20px var(--local-gutter) 22px;
}
.feed-heading {
  display: flex;
  align-items: center;
  gap: 10px;
}
.feed-heading-icon {
  display: grid;
  flex: 0 0 38px;
  place-items: center;
  height: 38px;
  border-radius: 12px;
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
}
.feed-heading h2 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 900;
  line-height: 1.2;
}
.feed-heading p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 0.75rem;
}
.feed-filters {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto 44px;
  align-items: center;
  gap: 6px;
}
.feed-kind {
  min-width: 0;
}
.feed-saved,
.feed-refresh {
  min-height: 44px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-soft);
  color: var(--lavender);
  font: 800 0.8125rem var(--font);
}
.feed-saved,
.feed-refresh {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding-inline: 9px;
}
.feed-filters button:hover {
  border-color: var(--lavender);
}
.feed-saved[aria-pressed='true'] {
  border-color: var(--local-accent-line);
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
}
.feed-refresh[aria-disabled='true'] {
  color: var(--muted);
  cursor: wait;
}
.feed-list {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-width: 0;
  margin-inline: calc(-1 * var(--local-gutter));
}
.feed-more {
  margin: 12px var(--local-gutter) 0;
}
.feed-state {
  margin: 0;
  padding: 18px 14px;
  border-block: 1px solid var(--line);
  color: var(--muted);
  font-size: 0.875rem;
}
.feed-state strong {
  color: var(--text);
}
.feed-state p {
  margin: 5px 0 0;
}
.feed-state--error {
  color: var(--local-accent-text);
}
.feed-state .text-link {
  display: flex;
  min-height: 44px;
  font-size: 0.8125rem;
}
@media (max-width: 379px) {
  .feed-filters {
    grid-template-columns: minmax(0, 1fr) 44px;
  }
  .feed-kind {
    grid-column: 1 / -1;
  }
}
</style>
