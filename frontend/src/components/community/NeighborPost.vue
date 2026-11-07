<script setup lang="ts">
import { shallowRef, useTemplateRef, nextTick } from 'vue'
import { NAvatar, NButton, NDropdown, NInput, type InputInst } from 'naive-ui'
import type { Post } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'

const props = defineProps<{ post: Post }>()
const emit = defineEmits<{
  welcome: [id: string]
  comment: [id: string, text: string]
  save: [id: string]
}>()
const composing = shallowRef(false)
const draft = shallowRef('')
const input = useTemplateRef<InputInst>('comment-input')

async function openComment() {
  composing.value = !composing.value
  if (composing.value) {
    await nextTick()
    input.value?.focus()
  }
}

function submitComment() {
  if (!draft.value.trim()) return
  emit('comment', props.post.id, draft.value)
  draft.value = ''
  composing.value = false
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
      />
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
    <div class="post-copy">
      <p v-for="(paragraph, index) in post.paragraphs" :key="index">
        {{ paragraph }}
      </p>
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
      <div class="reaction-faces">
        <img src="/images/lucia.jpg" alt="" width="22" height="22" /><img
          src="/images/ana.jpg"
          alt=""
          width="22"
          height="22"
        /><span><AppIcon name="smile" :size="13" /></span>
      </div>
      <span
        >{{ post.welcomed ? 'Tú y ' : ''
        }}{{ post.reactions - (post.welcomed ? 1 : 0) }}
        {{ post.welcomed ? 'vecinos más' : 'vecinos' }}
        {{ post.isWelcome ? 'le dieron la bienvenida' : 'reaccionaron' }}</span
      >
    </div>
    <div v-if="post.comments.length" class="comments">
      <div v-for="comment in post.comments" :key="comment.id" class="comment">
        <NAvatar
          round
          :size="34"
          :src="comment.avatar"
          :img-props="{ alt: '' }"
          class="neighbor-avatar"
        />
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
        placeholder="Escribe algo bonito…"
        :input-props="{ 'aria-label': 'Tu comentario' }"
        :maxlength="500"
      />
      <NButton
        attr-type="submit"
        circle
        type="primary"
        :disabled="!draft.trim()"
        aria-label="Enviar comentario"
        ><AppIcon name="send" :size="18"
      /></NButton>
    </form>
  </article>
</template>
