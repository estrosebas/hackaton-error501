<script setup lang="ts">
import { computed, onMounted, onScopeDispose, shallowRef } from 'vue'
import { NInput, NSwitch } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import AppSelect from '../AppSelect.vue'
import ProfileTagChoices from './ProfileTagChoices.vue'
import type { NeighborProfile } from '../../composables/useAuth'
import { api } from '../../services/api'

defineProps<{ disabled: boolean }>()
const profile = defineModel<NeighborProfile>({ required: true })
const catalog = shallowRef<{ skills: string[]; interests: string[]; availability: string[] }>()
const failed = shallowRef(false)
const request = new AbortController()
const availabilityOptions = computed(() => [
  { label: 'Sin indicar', value: '' },
  ...(catalog.value?.availability ?? []).map(value => ({ label: value, value })),
])

function update<K extends keyof NeighborProfile>(key: K, value: NeighborProfile[K]) {
  profile.value = { ...profile.value, [key]: value }
}

async function loadCatalog() {
  failed.value = false
  try {
    const result = await api<{ data: NonNullable<typeof catalog.value> }>('/profile/options', { signal: request.signal })
    catalog.value = result.data
  } catch {
    if (!request.signal.aborted) failed.value = true
  }
}

onMounted(loadCatalog)
onScopeDispose(() => request.abort())
</script>

<template>
  <div class="profile-personalization">
    <header class="personalize-heading">
      <h2>Personaliza tu cuenta</h2>
      <p>Cuéntanos qué te gusta y cómo quieres participar en el barrio. Todo es opcional.</p>
    </header>

    <div class="personalize-field">
      <label for="profile-bio">Un poquito sobre mí</label>
      <NInput
        :value="profile.bio ?? ''"
        :input-props="{ id: 'profile-bio' }"
        type="textarea"
        placeholder="Me gusta cuidar plantas y conocer a las personas que viven cerquita."
        :autosize="{ minRows: 3, maxRows: 6 }"
        :maxlength="280"
        :disabled="disabled"
        show-count
        @update:value="update('bio', $event)"
      />
    </div>

    <template v-if="catalog">
      <ProfileTagChoices
        :model-value="profile.skills"
        label="Puedo echarte una mano con"
        icon="hand"
        :options="catalog.skills"
        :disabled="disabled"
        @update:model-value="update('skills', $event)"
      />
      <ProfileTagChoices
        :model-value="profile.interests"
        label="Me apunto a"
        icon="calendar"
        :options="catalog.interests"
        :disabled="disabled"
        @update:model-value="update('interests', $event)"
      />
      <div class="personalize-field">
        <span class="personalize-label"><AppIcon name="clock" :size="16" /> Disponibilidad</span>
        <AppSelect
          :value="profile.availability ?? ''"
          :options="availabilityOptions"
          label="Disponibilidad"
          :disabled="disabled"
          @update:value="update('availability', $event || null)"
        />
      </div>
    </template>
    <div v-else-if="failed" class="personalize-state">
      <p role="alert">No pudimos cargar las etiquetas y horarios.</p>
      <button type="button" class="text-link" :disabled="disabled" @click="loadCatalog">Reintentar</button>
    </div>
    <p v-else class="personalize-state" role="status">Cargando opciones del barrio…</p>

    <div class="personalize-help">
      <div>
        <label for="profile-can-help">Puedo ayudar</label>
        <p>Muéstralo en tu perfil cuando tengas tiempo para echar una mano.</p>
      </div>
      <NSwitch
        id="profile-can-help"
        :value="profile.can_help"
        :disabled="disabled"
        aria-label="Puedo ayudar"
        @update:value="update('can_help', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.profile-personalization {
  display: grid;
  gap: 19px;
  min-width: 0;
}
.personalize-heading h2 {
  margin: 0 0 6px;
  font-size: 1.375rem;
  font-weight: 900;
  line-height: 1.15;
}
.personalize-heading p,
.personalize-help p,
.personalize-state {
  margin: 0;
  color: var(--muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}
.personalize-field {
  display: grid;
  gap: 7px;
  min-width: 0;
}
.personalize-field label,
.personalize-label,
.personalize-help label {
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 900;
}
.personalize-label {
  display: flex;
  align-items: center;
  gap: 6px;
}
.personalize-label svg {
  color: var(--coral);
}
.personalize-help {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-soft);
}
.personalize-help p {
  margin-top: 3px;
}
.personalize-state p {
  margin: 0;
}
.personalize-state button {
  min-height: 44px;
}
</style>
