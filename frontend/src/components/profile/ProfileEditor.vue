<script setup lang="ts">
import { NButton } from 'naive-ui'
import { computed, nextTick, shallowRef, useTemplateRef } from 'vue'
import ProfilePersonalization from '../auth/ProfilePersonalization.vue'
import ProfilePresentation from './ProfilePresentation.vue'
import { currentUser, updateNeighborProfile, type NeighborProfile } from '../../composables/useAuth'

const draft = shallowRef<NeighborProfile | null>(null)
const original = shallowRef<NeighborProfile | null>(null)
const saving = shallowRef(false)
const error = shallowRef('')
const saved = shallowRef(false)
const editButton = useTemplateRef<HTMLButtonElement>('editButton')
const form = useTemplateRef<HTMLFormElement>('form')
const fields = ['bio', 'skills', 'interests', 'availability', 'can_help'] as const

const changes = computed<Partial<NeighborProfile>>(() => {
  if (!draft.value || !original.value) return {}
  const before = original.value
  const after = { ...draft.value, bio: draft.value.bio?.trim() || null }
  const changed = fields.filter((field) => JSON.stringify(after[field]) !== JSON.stringify(before[field]))
  return Object.fromEntries(changed.map((field) => [field, after[field]]))
})
const hasChanges = computed(() => Object.keys(changes.value).length > 0)

async function edit() {
  const profile = currentUser.value?.neighbor_profile
  original.value = {
    bio: profile?.bio?.trim() || null,
    skills: [...(profile?.skills ?? [])],
    interests: [...(profile?.interests ?? [])],
    availability: profile?.availability ?? null,
    can_help: Number(profile?.can_help) === 1,
  }
  draft.value = { ...original.value }
  saved.value = false
  error.value = ''
  await nextTick()
  form.value?.focus()
}

async function close() {
  draft.value = null
  original.value = null
  error.value = ''
  await nextTick()
  editButton.value?.focus()
}

async function save() {
  if (saving.value || !hasChanges.value) return
  saving.value = true
  error.value = ''
  try {
    if (await updateNeighborProfile(changes.value)) {
      saved.value = true
      await close()
    }
  } catch (failure) {
    error.value = failure instanceof Error ? failure.message : 'No pudimos guardar los cambios. Inténtalo de nuevo.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <ProfilePresentation
    title="Tu presentación"
    description="Lo que compartes con tus vecinos en tu perfil."
    empty-message="Tu perfil todavía no tiene una presentación, habilidades ni intereses."
    :profile="currentUser?.neighbor_profile"
  >
    <template v-if="!draft" #action>
      <button ref="editButton" type="button" class="edit-profile" @click="edit">Editar perfil</button>
    </template>
    <template v-if="draft" #default>
      <form ref="form" class="profile-editor" tabindex="-1" aria-label="Editar presentación" @submit.prevent="save">
        <ProfilePersonalization v-model="draft" :disabled="saving" hide-heading />
        <p v-if="error" class="editor-error" role="alert">{{ error }}</p>
        <div class="editor-actions">
          <NButton size="large" secondary :disabled="saving" @click="close">Cancelar</NButton>
          <NButton size="large" type="primary" attr-type="submit" :loading="saving" :disabled="saving || !hasChanges">
            Guardar cambios
          </NButton>
        </div>
      </form>
    </template>
  </ProfilePresentation>
  <p v-if="saved" class="editor-status" role="status">Cambios guardados.</p>
</template>

<style scoped>
.edit-profile {
  min-height: 44px;
  padding-inline: 10px;
  border: 1px solid var(--local-accent-line);
  border-radius: 10px;
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
  font-size: 0.875rem;
  font-weight: 800;
}

.edit-profile:hover {
  border-color: var(--local-accent-text);
}

.profile-editor {
  display: grid;
  gap: 16px;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.editor-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.editor-actions .n-button {
  min-height: 44px;
  font-weight: 800;
}

.editor-actions .n-button--primary-type {
  color: var(--surface);
}

.editor-error,
.editor-status {
  margin: 0;
  color: var(--local-accent-text);
  font-size: 0.875rem;
  overflow-wrap: anywhere;
}

.editor-status {
  margin-top: 12px;
  color: var(--text);
}
</style>
