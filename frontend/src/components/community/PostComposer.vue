<script setup lang="ts">
import { shallowRef } from 'vue'
import { postKinds } from '../../composables/useCommunity'
import { currentUser } from '../../composables/useAuth'
import { NAvatar, NButton, NInput } from 'naive-ui'
defineProps<{ neighborhood: string; saving: boolean; error: string }>()
const emit = defineEmits<{ publish: [text: string, kind: string] }>()
const draft = shallowRef('')
const kind = shallowRef('need_help')
function submit() {
  if (!draft.value.trim()) return
  emit('publish', draft.value, kind.value)
}
</script>

<template>
  <form class="compose-form" @submit.prevent="submit">
    <div class="compose-author">
      <NAvatar round :size="42" :img-props="{ alt: '' }">{{ currentUser?.name.charAt(0) || 'T' }}</NAvatar>
      <div>
        <strong>{{ currentUser?.name || 'Tú' }}</strong>
        <p>Para vecinos de tu distrito · {{ neighborhood }}</p>
      </div>
    </div>
    <label
      >¿Qué quieres compartir?<select
        v-model="kind"
        :disabled="saving"
        style="
          display: block;
          width: 100%;
          padding: 12px;
          background: var(--surface);
          color: var(--text);
          border-radius: 10px;
        "
      >
        <option v-for="item in postKinds" :key="item.value" :value="item.value">
          {{ item.label }}
        </option>
      </select></label
    >
    <NInput
      v-model:value="draft"
      type="textarea"
      placeholder="Cuéntanos qué necesitas o cómo puedes ayudar. Incluye lugar, horario y detalles útiles."
      :input-props="{ 'aria-label': 'Tu publicación' }"
      :maxlength="2000"
      show-count
      :autosize="{ minRows: 7, maxRows: 14 }"
    />
    <p class="sheet-hint">
      Pide u ofrece ayuda, organiza una colaboración o propone una actividad.
      Puedes incluir hasta cinco hashtags. Evita publicar datos personales
      sensibles.
    </p>
    <p v-if="error" role="alert" style="color: #ffb0c0">{{ error }}</p>
    <NButton
      type="primary"
      size="large"
      block
      attr-type="submit"
      :disabled="!draft.trim() || saving"
      :loading="saving"
      >Publicar en mi barrio</NButton
    >
  </form>
</template>
