<script setup lang="ts">
import NeighborEndorsement from '../neighbors/NeighborEndorsement.vue'
import { computed, nextTick, shallowRef, useTemplateRef } from 'vue'
import { NAvatar, NDropdown } from 'naive-ui'
import type { Post } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'
import PostPhotoGallery from './PostPhotoGallery.vue'
import PostCaption from './PostCaption.vue'
import PostComments from './PostComments.vue'
import NeighborLink from '../neighbors/NeighborLink.vue'

const props = defineProps<{ post: Post; hasMoreComments: boolean }>()
const emit = defineEmits<{
  welcome: [id: string]
  comment: [id: string, text: string, done: (success: boolean) => void]
  loadComments: [id: string]
  moreComments: [id: string]
  resolve: [id: string]
  remove: [id: string]
  save: [id: string]
  commentLike: [postId: string, commentId: string]
}>()

const commentsOpen = shallowRef(false)
const commentsVisited = shallowRef(false)
const comments = useTemplateRef<InstanceType<typeof PostComments>>('comments')
const ownerOptions = computed(() => [
  ...(props.post.kind === 'moment'
    ? []
    : [
        {
          label:
            props.post.status === 'resolved'
              ? 'Reabrir'
              : props.post.kind === 'activity'
                ? 'Marcar como finalizada'
                : 'Marcar como atendida',
          key: 'resolve',
        },
      ]),
  { label: 'Retirar publicación', key: 'remove' },
])
const reactionLabel = computed(() =>
  props.post.isWelcome ? 'Dar la bienvenida' : 'Me gusta',
)
const reactionCount = computed(() =>
  props.post.isWelcome
    ? `${props.post.reactions} ${props.post.reactions === 1 ? 'bienvenida' : 'bienvenidas'}`
    : `${props.post.reactions} Me gusta`,
)

function selectOwnerAction(action: string) {
  if (action === 'resolve') emit('resolve', props.post.id)
  if (action === 'remove') emit('remove', props.post.id)
}

async function toggleComments(focus: boolean) {
  commentsOpen.value = !commentsOpen.value
  if (!commentsOpen.value) return
  commentsVisited.value = true
  if (props.post.comments_count && !props.post.comments.length) {
    emit('loadComments', props.post.id)
  }
  if (focus) {
    await nextTick()
    comments.value?.focus()
  }
}
</script>

<template>
  <article class="neighbor-post" :class="{'neighbor-post--emergency':post.kind==='emergency'}" :aria-label="`${post.kind==='emergency'?'Emergencia':'Publicación'} de ${post.author}`">
    <small v-if="post.is_demo" class="post-text" style="color:var(--muted)">Comunidad de demostración · perfil sintético</small>
    <div v-if="post.kind==='emergency'" class="post-emergency-banner"><span aria-hidden="true">🚨</span>{{post.status==='resolved'?'Emergencia atendida':'Emergencia vecinal'}}</div>
    <header class="post-heading">
      <NeighborLink :id="post.author_id" :name="post.author">
        <CurrentUserAvatar
          v-if="post.owned"
          :size="38"
          class="neighbor-avatar"
        />
        <NAvatar
          v-else
          round
          :size="38"
          :src="post.avatar || undefined"
          :img-props="{ alt: '' }"
          class="neighbor-avatar"
        >
          <template v-if="!post.avatar" #default>{{
            post.author.charAt(0)
          }}</template>
          <template #fallback>{{ post.author.charAt(0) }}</template>
        </NAvatar>
      </NeighborLink>
      <div class="post-byline">
        <NeighborLink :id="post.author_id" :name="post.author"
          ><strong>{{ post.author }}</strong></NeighborLink
        >
        <span v-if="post.isWelcome">Se unió al barrio</span>
        <span v-else-if="post.neighborhood">{{ post.neighborhood }}</span>
      </div>
      <NDropdown
        v-if="post.owned"
        trigger="click"
        :options="ownerOptions"
        @select="selectOwnerAction"
      >
        <button
          type="button"
          class="post-control post-menu"
          aria-label="Opciones de tu publicación"
        >
          <AppIcon name="more" :size="20" />
        </button>
      </NDropdown>
    </header>

    <NeighborEndorsement :neighbor-id="post.author_id" :name="post.author" compact class="post-text"/><PostPhotoGallery
      v-if="post.has_photo"
      :post-id="post.id"
      :count="post.photo_count ?? 1"
      edge
    />
    <PostCaption v-else class="post-text" :post="post" />

    <div class="post-footer">
      <div
        class="post-actions"
        role="group"
        aria-label="Acciones de la publicación"
      >
        <button
          type="button"
          class="post-control post-like"
          :aria-label="reactionLabel"
          :title="reactionLabel"
          :aria-pressed="post.welcomed"
          @click="emit('welcome', post.id)"
        >
          <AppIcon :name="post.isWelcome ? 'smile' : 'heart'" :size="25" />
        </button>
        <button
          type="button"
          class="post-control"
          aria-label="Comentar"
          title="Comentar"
          :aria-expanded="commentsOpen"
          @click="toggleComments(true)"
        >
          <AppIcon name="chat" :size="24" />
        </button>
        <button
          type="button"
          class="post-control post-bookmark"
          aria-label="Guardar publicación"
          :title="post.saved ? 'Quitar de guardados' : 'Guardar publicación'"
          :aria-pressed="post.saved"
          @click="emit('save', post.id)"
        >
          <AppIcon name="bookmark" :size="24" />
        </button>
      </div>
      <p v-if="post.reactions > 0" class="reaction-summary">
        {{ reactionCount }}
      </p>
      <PostCaption v-if="post.has_photo" :post="post" />
      <button
        v-if="post.comments_count"
        type="button"
        class="comment-history"
        :aria-expanded="commentsOpen"
        @click="toggleComments(false)"
      >
        {{
          commentsOpen
            ? 'Ocultar comentarios'
            : `Ver ${post.comments_count} ${post.comments_count === 1 ? 'comentario' : 'comentarios'}`
        }}
      </button>
      <span class="post-time">{{ post.time }}</span>
      <PostComments
        v-if="commentsVisited"
        v-show="commentsOpen"
        ref="comments"
        :comments="post.comments"
        :has-more="hasMoreComments"
        @more="emit('moreComments', post.id)"
        @like="emit('commentLike', post.id, $event)"
        @submit="(text, done) => emit('comment', post.id, text, done)"
      />
    </div>
  </article>
</template>

<style scoped>
.neighbor-post {
  min-width: 0;
  padding-bottom: 15px;
  border-top: 1px solid var(--line);
  background: var(--surface);
}
.neighbor-post--emergency {
  margin-block: 10px;
  border: 2px solid #ff617e;
  border-left-width: 5px;
  border-radius: 16px;
  background: linear-gradient(135deg, #ff617e0d, transparent 65%), var(--surface);
  box-shadow: 0 0 0 3px #ff617e12;
  overflow: hidden;
}
.post-emergency-banner {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 9px var(--local-gutter);
  border-bottom: 1px solid #ff617e55;
  color: #ff9cb0;
  background: #ff617e1a;
  font-size: 0.75rem;
  font-weight: 900;
}
.post-heading,
.post-footer,
.post-text {
  padding-inline: var(--local-gutter);
}
.post-heading {
  display: flex;
  align-items: center;
  gap: 9px;
  min-height: 61px;
  padding-block: 8px;
}
.neighbor-avatar {
  flex-shrink: 0;
  border: 1px solid var(--line);
}
.post-byline {
  display: grid;
  flex: 1;
  min-width: 0;
  gap: 2px;
  overflow-wrap: anywhere;
}
.post-byline strong {
  font-size: 0.875rem;
  font-weight: 900;
}
.post-byline span {
  color: var(--muted);
  font-size: 0.75rem;
}
.post-text {
  padding-bottom: 5px;
}
.post-footer {
  display: grid;
  gap: 5px;
}
.post-actions {
  display: flex;
  align-items: center;
  margin-inline: -8px;
}
.post-control {
  display: grid;
  flex: none;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--text);
}
.post-control:hover {
  background: var(--surface-soft);
}
.post-control:focus-visible {
  outline-offset: -2px;
}
.post-control svg {
  transition: transform var(--press-duration) ease-out;
}
.post-control:active svg {
  transform: scale(0.88);
}
.post-menu {
  margin-inline-end: -8px;
  color: var(--muted);
}
.post-like[aria-pressed='true'] {
  color: var(--coral);
}
.post-bookmark {
  margin-inline-start: auto;
}
.post-bookmark[aria-pressed='true'] {
  color: var(--lavender);
}
.post-like[aria-pressed='true'] svg,
.post-bookmark[aria-pressed='true'] svg {
  fill: currentColor;
}
.reaction-summary {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 900;
}
.comment-history {
  justify-self: start;
  min-height: 44px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 0.8125rem;
  text-align: start;
}
.comment-history:hover {
  color: var(--text);
}
.post-time {
  color: var(--muted);
  font-size: 0.6875rem;
}
</style>
