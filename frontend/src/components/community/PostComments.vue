<script setup lang="ts">
import NeighborEndorsement from '../neighbors/NeighborEndorsement.vue'
import { shallowRef, useTemplateRef } from 'vue'
import { NAvatar, NButton, NInput, type InputInst } from 'naive-ui'
import type { Comment } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'
import NeighborLink from '../neighbors/NeighborLink.vue'

defineProps<{ comments: Comment[]; hasMore: boolean }>()
const emit = defineEmits<{
  submit: [text: string, done: (success: boolean) => void]
  more: []
  like: [id: string]
}>()
const draft = shallowRef('')
const sending = shallowRef(false)
const input = useTemplateRef<InputInst>('comment-input')

function submit() {
  const text = draft.value.trim()
  if (!text || sending.value) return
  sending.value = true
  emit('submit', text, (success) => {
    sending.value = false
    if (success) draft.value = ''
  })
}

defineExpose({ focus: () => input.value?.focus() })
</script>

<template>
  <section class="post-comments" aria-label="Comentarios de la publicación">
    <button
      v-if="hasMore"
      class="comments-more"
      type="button"
      @click="emit('more')"
    >
      Ver comentarios anteriores
    </button>
    <ul v-if="comments.length" class="comments-list" role="list">
      <li v-for="comment in comments" :key="comment.id" class="comment">
        <NeighborLink :id="comment.author_id" :name="comment.author">
          <NAvatar
            round
            :size="28"
            :src="comment.avatar || undefined"
            :img-props="{ alt: '' }"
            class="comment-avatar"
          >
            <template v-if="!comment.avatar" #default>{{
              comment.author.charAt(0)
            }}</template>
            <template #fallback>{{ comment.author.charAt(0) }}</template>
          </NAvatar>
        </NeighborLink>
        <div class="comment-text">
          <NeighborLink :id="comment.author_id" :name="comment.author"
            ><strong>{{ comment.author }}</strong></NeighborLink
          >
          <p>{{ comment.text }}</p>
          <NeighborEndorsement :neighbor-id="comment.author_id" :name="comment.author" compact/>
          <div class="comment-meta">
            <span>{{ comment.time }}</span
            ><button
              type="button"
              class="comment-like"
              :aria-label="`Me gusta el comentario de ${comment.author}`"
              :aria-pressed="comment.liked"
              @click="emit('like', comment.id)"
            >
              <AppIcon name="heart" :size="13" />{{ comment.likes || '' }}
              {{ comment.liked ? 'Te gusta' : 'Me gusta' }}
            </button>
          </div>
        </div>
      </li>
    </ul>
    <form class="comment-form" @submit.prevent="submit">
      <CurrentUserAvatar :size="28" />
      <NInput
        ref="comment-input"
        v-model:value="draft"
        placeholder="Añade un comentario…"
        :input-props="{ 'aria-label': 'Tu comentario' }"
        :maxlength="500"
        :disabled="sending"
      />
      <NButton
        attr-type="submit"
        circle
        quaternary
        type="primary"
        :disabled="!draft.trim() || sending"
        :loading="sending"
        aria-label="Enviar comentario"
      >
        <AppIcon name="send" :size="19" />
      </NButton>
    </form>
  </section>
</template>

<style scoped>
.comment-meta {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.comment-like {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 32px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font: inherit;
  font-size: 0.6875rem;
  cursor: pointer;
}
.comment-like[aria-pressed='true'] {
  color: var(--local-accent-text);
}
.comment-like[aria-pressed='true'] :deep(svg) {
  fill: currentColor;
}
.post-comments {
  display: grid;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--line);
}
.comments-list {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.comment {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}
.comment-avatar {
  flex-shrink: 0;
}
.comment-text {
  display: grid;
  gap: 4px;
  min-width: 0;
  overflow-wrap: anywhere;
}
.comment-text p {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.45;
  white-space: pre-wrap;
}
.comment-text strong {
  margin-inline-end: 3px;
  font-weight: 900;
}
.comment-text span {
  color: var(--muted);
  font-size: 0.6875rem;
}
.comments-more {
  justify-self: start;
  min-height: 44px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--lavender);
  font-size: 0.8125rem;
}
.comment-form {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.comment-form .n-input {
  flex: 1;
  min-width: 0;
}
.comment-form .n-button {
  width: 44px;
  height: 44px;
}
</style>
