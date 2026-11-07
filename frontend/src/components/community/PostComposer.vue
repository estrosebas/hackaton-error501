<script setup lang="ts">
import { shallowRef } from 'vue'
import { NAvatar, NButton, NInput } from 'naive-ui'
defineProps<{ neighborhood: string }>()
const emit = defineEmits<{ publish: [text: string] }>()
const draft = shallowRef('')
function submit() {
  if (!draft.value.trim()) return
  emit('publish', draft.value)
}
</script>

<template>
  <form class="compose-form" @submit.prevent="submit">
    <div class="compose-author">
      <NAvatar
        round
        :size="42"
        src="/images/ana.jpg"
        :img-props="{ alt: '' }"
      />
      <div>
        <strong>Ana · Tú</strong>
        <p>Para tus vecinos de {{ neighborhood }}</p>
      </div>
    </div>
    <NInput
      v-model:value="draft"
      type="textarea"
      placeholder="¿Qué te gustaría compartir con tu barrio?"
      :input-props="{ 'aria-label': 'Tu publicación' }"
      :maxlength="2000"
      show-count
      :autosize="{ minRows: 7, maxRows: 14 }"
    />
    <p class="sheet-hint">
      Un saludo, una pregunta o una buena noticia. Este es tu lugar.
    </p>
    <NButton
      type="primary"
      size="large"
      block
      attr-type="submit"
      :disabled="!draft.trim()"
      >Publicar en mi barrio</NButton
    >
  </form>
</template>
