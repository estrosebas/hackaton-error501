<script setup lang="ts">
import NeighborEndorsement from '../components/neighbors/NeighborEndorsement.vue'
import { computed, onBeforeUnmount, shallowRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { NButton } from 'naive-ui'
import AppIcon from '../components/AppIcon.vue'
import NeighborAvatar from '../components/neighbors/NeighborAvatar.vue'
import ProfileIdentity from '../components/profile/ProfileIdentity.vue'
import ProfilePage from '../components/profile/ProfilePage.vue'
import ProfilePresentation from '../components/profile/ProfilePresentation.vue'
import { api, ApiError } from '../services/api'
import { currentUser, type NeighborProfile } from '../composables/useAuth'

const route = useRoute()
const router = useRouter()

interface Profile {
  id: number
  name: string
  avatar: string
  neighborhood: string | null
  joined: string
  blocked_by_me: boolean
  can_message: boolean
  profile: Partial<NeighborProfile> | null
}

const neighbor = shallowRef<Profile | null>(null)
const loadError = shallowRef('')
const actionError = shallowRef('')
const busy = shallowRef(false)
const isOtherNeighbor = computed(() => neighbor.value !== null && neighbor.value.id !== currentUser.value?.id)
const month = new Intl.DateTimeFormat('es-PE', { month: 'long', year: 'numeric', timeZone: 'UTC' })
const joinedLabel = computed(() => {
  const value = neighbor.value?.joined
  return value && /^\d{4}-(0[1-9]|1[0-2])$/.test(value)
    ? month.format(new Date(`${value}-01T00:00:00Z`))
    : ''
})

let generation = 0
async function load(keepProfile = false) {
  const version = ++generation
  if (!keepProfile) {
    neighbor.value = null
    loadError.value = ''
  }
  actionError.value = ''
  try {
    const result = await api<{ data: Profile }>(
      `/neighbors/${route.params.neighborId}`,
    )
    if (version === generation) neighbor.value = result.data
  } catch (error) {
    if (version !== generation) return
    if (keepProfile) {
      actionError.value = 'No pudimos actualizar el perfil. Vuelve a cargarlo para comprobar las opciones de chat.'
    } else {
      loadError.value = error instanceof ApiError && error.status === 404
        ? 'Este perfil no está disponible.'
        : 'No pudimos cargar el perfil. Inténtalo de nuevo.'
    }
  }
}

function messageNeighbor() {
  if (neighbor.value?.can_message && !busy.value) {
    void router.push({ name: 'messages', query: { to: neighbor.value.id } })
  }
}

async function block() {
  const selected = neighbor.value
  if (!selected || !isOtherNeighbor.value || busy.value) return
  if (
    !selected.blocked_by_me &&
    !confirm(
      `¿Bloquear a ${selected.name}? No podrán enviarse mensajes ni invitarse a salas.`,
    )
  )
    return
  const version = generation
  busy.value = true
  actionError.value = ''
  try {
    const result = await api<{ blocked: boolean }>(`/neighbors/${selected.id}/block`, {
      method: 'PUT',
      body: JSON.stringify({ blocked: !selected.blocked_by_me }),
    })
    if (version === generation) {
      neighbor.value = { ...selected, blocked_by_me: result.blocked, can_message: false }
      await load(true)
    }
  } catch (error) {
    if (version === generation) {
      actionError.value = error instanceof Error ? error.message : 'No pudimos actualizar el bloqueo.'
    }
  } finally {
    busy.value = false
  }
}
watch(
  () => route.params.neighborId,
  () => {
    void load()
  },
  { immediate: true },
)
onBeforeUnmount(() => { generation++ })
</script>

<template>
  <ProfilePage title="Perfil vecinal" description="Su perfil en la comunidad.">
    <template #heading-action>
      <RouterLink class="profile-back" :to="{ name: 'home' }">
        <AppIcon name="arrow" :size="18" /> Volver al barrio
      </RouterLink>
    </template>

    <ProfileIdentity v-if="neighbor" :name="neighbor.name" :district="neighbor.neighborhood || 'Distrito sin registrar'">
      <template #avatar="{ size }">
        <NeighborAvatar :id="neighbor.id" :name="neighbor.name" :avatar="neighbor.avatar" :size="size" />
      </template>
      <template v-if="isOtherNeighbor" #action>
        <NButton type="primary" secondary size="large" :disabled="busy || !neighbor.can_message" @click="messageNeighbor">
          <template #icon><AppIcon name="chat" :size="19" /></template>
          Escribir mensaje
        </NButton>
      </template>
    </ProfileIdentity>
    <div v-else-if="loadError" class="profile-status">
      <p role="alert">{{ loadError }}</p>
      <NButton secondary size="large" @click="load()">Reintentar</NButton>
    </div>
    <p v-else class="profile-status" role="status">Cargando perfil…</p>

    <template v-if="neighbor" #presentation>
      <NeighborEndorsement v-if="neighbor" :neighbor-id="neighbor.id" :name="neighbor.name"/><ProfilePresentation
        title="Presentación"
        description="Lo que comparte con sus vecinos en su perfil."
        empty-message="Este perfil todavía no tiene una presentación, habilidades ni intereses."
        :profile="neighbor.profile"
      />
    </template>

    <template v-if="neighbor && (joinedLabel || isOtherNeighbor)" #sidebar>
      <section class="neighbor-contact" aria-labelledby="neighbor-contact-heading">
        <h2 id="neighbor-contact-heading">Contacto</h2>
        <div v-if="joinedLabel" class="neighbor-since">
          <span class="since-icon"><AppIcon name="calendar" :size="20" /></span>
          <div>
            <strong>En masi desde</strong>
            <time :datetime="neighbor.joined">{{ joinedLabel }}</time>
          </div>
        </div>
        <template v-if="isOtherNeighbor">
          <p v-if="!neighbor.can_message && !actionError" class="contact-note">
            No se pueden enviar mensajes mientras exista un bloqueo.
          </p>
          <p v-if="actionError" class="contact-error" role="alert">{{ actionError }}</p>
          <div class="contact-actions">
            <NButton secondary size="large" :loading="busy" :disabled="busy" @click="block">
              {{ neighbor.blocked_by_me ? 'Desbloquear vecino' : 'Bloquear vecino' }}
            </NButton>
            <NButton v-if="actionError" quaternary size="large" :disabled="busy" @click="load(true)">
              Recargar perfil
            </NButton>
          </div>
        </template>
      </section>
    </template>
  </ProfilePage>
</template>

<style scoped>
.profile-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  border-radius: 8px;
  color: var(--lavender);
  font-size: 0.875rem;
  font-weight: 800;
  text-decoration: none;
}

.profile-back svg {
  transform: rotate(180deg);
}

.profile-back:hover {
  text-decoration: underline;
  text-underline-offset: 4px;
}

.profile-status,
.neighbor-since {
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 14px;
}

.profile-status {
  margin: 0;
  padding: 16px;
  color: var(--muted);
}

.profile-status p {
  margin: 0 0 12px;
}

.neighbor-contact h2 {
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 850;
}

.neighbor-since {
  display: flex;
  align-items: center;
  gap: 11px;
  min-height: 62px;
  padding: 10px 12px;
}

.since-icon {
  display: grid;
  flex: none;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  color: var(--lavender);
  background: var(--surface-raised);
}

.neighbor-since strong {
  display: block;
  font-size: 0.9375rem;
  font-weight: 800;
}

.neighbor-since time,
.contact-note,
.contact-error {
  color: var(--muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.contact-note,
.contact-error {
  margin: 12px 0 0;
  overflow-wrap: anywhere;
}

.contact-error {
  color: var(--local-accent-text);
}

.contact-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.profile-status .n-button,
.contact-actions .n-button {
  min-height: 44px;
}
</style>
