<script setup lang="ts">
import { shallowRef, useTemplateRef, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '../../services/api'
import type { Post } from '../../composables/useCommunity'
import { useCommunityNotifications, type CommunityNotification } from '../../composables/useCommunityNotifications'
import AppIcon from '../AppIcon.vue'
import CommunityNotificationAvatar from './CommunityNotificationAvatar.vue'
import PostCaption from './PostCaption.vue'
import PostPhotoGallery from './PostPhotoGallery.vue'

const props = defineProps<{ notice: CommunityNotification }>()
defineEmits<{ back: []; close: [] }>()
const { read } = useCommunityNotifications()
const backButton = useTemplateRef<HTMLButtonElement>('back')
const post = shallowRef<Post>()
const error = shallowRef('')
const readError = shallowRef(false)
const attempt = shallowRef(0)

async function markRead(id: string) {
  readError.value = false
  try {
    await read(id)
  } catch {
    readError.value = true
  }
}

watch([() => props.notice.id, attempt], async ([id], _, onCleanup) => {
  const request = new AbortController()
  onCleanup(() => request.abort())
  post.value = undefined
  error.value = ''
  if (!props.notice.author) {
    if (!props.notice.read) void markRead(id)
    return
  }
  try {
    const result = await api<{ data: Post }>(`/feed/${id}`, { signal: request.signal })
    if (request.signal.aborted) return
    post.value = result.data
    if (!props.notice.read) void markRead(id)
  } catch (reason) {
    if (request.signal.aborted) return
    error.value = reason instanceof ApiError && reason.status === 404
      ? 'Esta publicación ya no está disponible.'
      : 'No pudimos cargar la publicación.'
  }
}, { immediate: true })

watch(backButton, (button) => button?.focus(), { flush: 'post' })
</script>

<template>
  <section aria-label="Publicación del aviso">
    <button ref="back" type="button" class="text-link notice-back" @click="$emit('back')">
      <AppIcon name="arrow" :size="16" /> Volver a los avisos
    </button>
    <div v-if="error" role="alert">
      <p>{{ error }}</p>
      <button type="button" class="text-link notice-back" @click="attempt++">Reintentar</button>
    </div>
    <article v-else-if="!notice.author">
      <h3>Emergencia</h3>
      <p>{{ notice.summary }}</p>
    </article>
    <p v-else-if="!post" role="status">Cargando publicación…</p>
    <article v-else>
      <RouterLink
        class="notice-author"
        :to="{ name: 'neighbor-profile', params: { neighborId: notice.author.id } }"
        @click="$emit('close')"
      >
        <CommunityNotificationAvatar :person="notice.author" :size="42" />
        <strong>{{ post.author }}</strong>
      </RouterLink>
      <PostCaption :post="post" />
      <PostPhotoGallery v-if="post.has_photo" :post-id="post.id" :count="post.photo_count ?? 1" />
    </article>
    <div v-if="readError" role="alert">
      <p>No pudimos guardar la lectura.</p>
      <button type="button" class="text-link notice-back" @click="markRead(notice.id)">Marcar como leída</button>
    </div>
  </section>
</template>

<style scoped>
.notice-author, .notice-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
}
.notice-author {
  margin-block: 8px 12px;
  color: var(--text);
}
.notice-back svg {
  transform: rotate(180deg);
}
</style>
