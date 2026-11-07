<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton } from 'naive-ui'
import NeighborAvatar from '../components/neighbors/NeighborAvatar.vue'
import NeighborProfileDetails from '../components/neighbors/NeighborProfileDetails.vue'
import { api } from '../services/api'
import { currentUser } from '../composables/useAuth'
const route = useRoute(),
  router = useRouter()
interface Profile {
  id: number
  name: string
  avatar: string
  neighborhood: string
  joined: string
  blocked_by_me: boolean
  can_message: boolean
  profile: {
    bio?: string
    skills?: string[]
    interests?: string[]
    availability?: string
    can_help?: boolean
  }
}
const neighbor = ref<Profile | null>(null),
  error = ref(''),
  busy = ref(false)
let generation = 0
async function load() {
  const version = ++generation
  neighbor.value = null
  error.value = ''
  try {
    const result = await api<{ data: Profile }>(
      `/neighbors/${route.params.neighborId}`,
    )
    if (version === generation) neighbor.value = result.data
  } catch (e) {
    if (version === generation)
      error.value =
        e instanceof Error ? e.message : 'No pudimos cargar el perfil.'
  }
}
async function block() {
  if (!neighbor.value || busy.value) return
  const selected = neighbor.value
  if (
    !selected.blocked_by_me &&
    !confirm(
      `¿Bloquear a ${selected.name}? No podrán enviarse mensajes ni invitarse a salas.`,
    )
  )
    return
  busy.value = true
  try {
    await api(`/neighbors/${selected.id}/block`, {
      method: 'PUT',
      body: JSON.stringify({ blocked: !selected.blocked_by_me }),
    })
    await load()
  } catch (e) {
    error.value =
      e instanceof Error ? e.message : 'No pudimos actualizar el bloqueo.'
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
</script>
<template>
  <section class="standard-page neighbor-page">
    <NButton quaternary @click="router.push('/inicio')"
      >← Volver al barrio</NButton
    >
    <p v-if="error" role="alert">{{ error }}</p>
    <article v-if="neighbor" class="neighbor-profile-card">
      <NeighborAvatar :id="neighbor.id" :name="neighbor.name" :avatar="neighbor.avatar" :size="80" />
      <h1>{{ neighbor.name }}</h1>
      <p class="muted">
        {{ neighbor.neighborhood }} · En masi desde {{ neighbor.joined }}
      </p>
      <NeighborProfileDetails
        :bio="neighbor.profile.bio"
        :skills="neighbor.profile.skills"
        :interests="neighbor.profile.interests"
        :availability="neighbor.profile.availability"
        :can-help="neighbor.profile.can_help"
      />
      <div
        v-if="neighbor.id !== currentUser?.id"
        class="profile-contact-actions"
      >
        <NButton
          type="primary"
          round
          :disabled="!neighbor.can_message"
          @click="
            router.push({ path: '/mensajes', query: { to: neighbor.id } })
          "
          >Escribir mensaje</NButton
        >
        <NButton round :loading="busy" @click="block">{{
          neighbor.blocked_by_me ? 'Desbloquear vecino' : 'Bloquear vecino'
        }}</NButton>
      </div>
      <p
        v-if="!neighbor.can_message && neighbor.id !== currentUser?.id"
        class="muted"
      >
        No se pueden enviar mensajes mientras exista un bloqueo.
      </p>
    </article>
    <p v-else-if="!error" role="status">Cargando perfil…</p>
  </section>
</template>
<style scoped>
.neighbor-page {
  max-width: 720px;
  margin: auto;
}
.neighbor-profile-card {
  margin-top: 16px;
  padding: 24px;
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 20px;
}
.neighbor-profile-card h1 {
  font-size: 1.5rem;
  margin-bottom: 6px;
}
.muted {
  color: var(--muted);
  font-size: 0.875rem;
}
.profile-contact-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 24px;
}
</style>
