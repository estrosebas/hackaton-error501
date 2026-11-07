<script setup lang="ts">
import { shallowRef, useTemplateRef, nextTick } from 'vue'
import { NAvatar, NButton, NDropdown, NInput, type InputInst } from 'naive-ui'
import { postKinds, type Post } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'

const props = defineProps<{ post: Post; hasMoreComments: boolean }>()
const emit = defineEmits<{
  welcome: [id: string]
  comment: [id: string, text: string, done: (success: boolean) => void]
  loadComments: [id: string]
  moreComments: [id: string]
  resolve: [id: string]
  remove: [id: string]
  save: [id: string]
}>()
const composing = shallowRef(false)
const draft = shallowRef('')
const sending = shallowRef(false)
const input = useTemplateRef<InputInst>('comment-input')

async function openComment() {
  composing.value = !composing.value
  if (composing.value) {
    emit('loadComments', props.post.id)
    await nextTick()
    input.value?.focus()
  }
}

function submitComment() {
  if (!draft.value.trim()) return
  if (sending.value) return
  sending.value = true
  emit('comment', props.post.id, draft.value, (success) => {
    sending.value = false
    if (success) {
      draft.value = ''
      composing.value = false
    }
  })
}
</script>

<template>
  <article class="neighbor-post" :aria-label="`Publicación de ${post.author}`">
    <header class="post-heading">
      <NAvatar
        round
        :size="46"
        :src="post.avatar"
        :img-props="{ alt: '' }"
        class="neighbor-avatar"
      >{{ post.author.charAt(0) }}</NAvatar>
      <div class="post-author">
        <p>
          <strong>{{ post.author }}</strong
          ><span v-if="post.isWelcome"> se unió al barrio</span
          ><span v-else> compartió una publicación</span>
        </p>
        <div class="post-meta">
          <span class="post-location">
            <AppIcon name="pin" :size="13" />{{ post.neighborhood }}
          </span>
          <span class="meta-dot" aria-hidden="true">·</span>
          <span>{{ post.time }}</span>
        </div>
      </div>
      <NDropdown
        trigger="click"
        :options="[
          {
            label: post.saved ? 'Quitar de guardados' : 'Guardar publicación',
            key: 'save',
          },
        ]"
        @select="emit('save', post.id)"
      >
        <NButton
          quaternary
          circle
          class="post-menu"
          :aria-label="`Opciones de la publicación de ${post.author}`"
          ><AppIcon name="more" :size="20"
        /></NButton>
      </NDropdown>
    </header>
    <p style="margin: 12px 16px; color: var(--lavender); font-weight: 800">
      {{ postKinds.find((item) => item.value === post.kind)?.label }}
      <span v-if="post.status === 'resolved'">· Atendido / finalizado</span>
    </p>
    <div class="post-copy">
      <p v-for="(paragraph, index) in post.paragraphs" :key="index">
        {{ paragraph }}
      </p>
    </div>
    <div v-if="post.owned" style="display: flex; gap: 8px; padding: 0 16px">
      <NButton size="small" @click="emit('resolve', post.id)">{{
        post.status === 'open' ? 'Marcar como atendido' : 'Reabrir'
      }}</NButton
      ><NButton size="small" @click="emit('remove', post.id)">Retirar</NButton>
    </div>
    <div class="post-actions">
      <NButton
        round
        class="welcome-button"
        :class="{ 'is-reacted': post.welcomed }"
        :aria-pressed="post.welcomed"
        @click="emit('welcome', post.id)"
      >
        <AppIcon :name="post.welcomed ? 'check' : 'smile'" :size="17" />
        {{ post.isWelcome ? 'Dar la bienvenida' : 'Me gusta' }}
      </NButton>
      <NButton round :aria-expanded="composing" @click="openComment">
        <AppIcon name="chat" :size="16" /> Comentar
      </NButton>
    </div>
    <div v-if="post.reactions > 0" class="reaction-summary">
      <span v-if="post.welcomed && post.reactions === 1">Te gusta esta publicación</span>
      <span v-else
        >{{ post.welcomed ? 'Tú y ' : ''
        }}{{ post.reactions - (post.welcomed ? 1 : 0) }}
        {{ post.welcomed ? 'vecinos más' : 'vecinos' }}
        {{ post.isWelcome ? 'le dieron la bienvenida' : 'reaccionaron' }}</span
      >
    </div>
    <NButton
      v-if="post.comments_count && !post.comments.length"
      quaternary
      @click="emit('loadComments', post.id)"
      >Ver {{ post.comments_count }} comentarios</NButton
    >
    <NButton
      v-if="hasMoreComments"
      quaternary
      @click="emit('moreComments', post.id)"
      >Ver comentarios anteriores</NButton
    >
    <div v-if="post.comments.length" class="comments">
      <div v-for="comment in post.comments" :key="comment.id" class="comment">
        <NAvatar
          round
          :size="34"
          :src="comment.avatar"
          :img-props="{ alt: '' }"
          class="neighbor-avatar"
        >{{ comment.author.charAt(0) }}</NAvatar>
        <div class="comment-bubble">
          <div class="comment-byline">
            <strong>{{ comment.author }}</strong
            ><span>{{ comment.time }}</span>
          </div>
          <p>{{ comment.text }}</p>
        </div>
      </div>
    </div>
    <form v-if="composing" class="comment-form" @submit.prevent="submitComment">
      <NInput
        ref="comment-input"
        v-model:value="draft"
        placeholder="Responde o coordina cómo ayudar…"
        :input-props="{ 'aria-label': 'Tu comentario' }"
        :maxlength="500"
      />
      <NButton
        attr-type="submit"
        circle
        type="primary"
        :disabled="!draft.trim() || sending"
        :loading="sending"
        aria-label="Enviar comentario"
        ><AppIcon name="send" :size="18"
      /></NButton>
    </form>
  </article>
</template>
