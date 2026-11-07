<script setup lang="ts">
import { NButton, NInput, NModal } from 'naive-ui'
import { computed, shallowRef, watch } from 'vue'
import AppIcon from '../AppIcon.vue'
import NeighborAvatar from '../neighbors/NeighborAvatar.vue'
import NeighborLink from '../neighbors/NeighborLink.vue'
import { currentUser } from '../../composables/useAuth'
import type { ChatDialog, ChatNeighbor, ChatRoom } from '../../composables/useChats'

const props = defineProps<{
  active: ChatDialog | null
  room: ChatRoom | null
  neighbors: ChatNeighbor[]
  blocked: ChatNeighbor[]
  busy: boolean
  error: string
  directoryLoading: boolean
  directoryError: string
  blockedLoading: boolean
  canInvite: boolean
}>()
const search = defineModel<string>('search', { required: true })
const emit = defineEmits<{
  close: []
  direct: [id: number]
  group: [name: string, members: number[]]
  invite: [id: number]
  block: [neighbor: ChatNeighbor]
  leave: []
  retryBlocked: []
  retryDirectory: []
}>()
const group = shallowRef(false)
const name = shallowRef('')
const chosen = shallowRef<ChatNeighbor[]>([])
const inviteId = shallowRef('')
const title = computed(() => ({
  create: 'Nuevo chat',
  members: 'Detalles del chat',
  blocked: 'Vecinos bloqueados',
})[props.active ?? 'create'])
const selectedIds = computed(() => new Set(chosen.value.map((neighbor) => neighbor.id)))
const invitable = computed(() => props.neighbors.filter((neighbor) =>
  !props.room?.members.some((member) => member.id === neighbor.id),
))
const canCreate = computed(() => !props.busy && chosen.value.length > 0 && (!group.value || name.value.trim()))

function changeKind(value: boolean) {
  if (value === group.value) return
  group.value = value
  chosen.value = []
}

function choose(neighbor: ChatNeighbor) {
  if (!group.value) chosen.value = [neighbor]
  else if (selectedIds.value.has(neighbor.id)) chosen.value = chosen.value.filter((item) => item.id !== neighbor.id)
  else if (chosen.value.length < 29) chosen.value = [...chosen.value, neighbor]
}

function submit() {
  if (!canCreate.value) return
  if (group.value) emit('group', name.value.trim(), [...selectedIds.value])
  else emit('direct', chosen.value[0]!.id)
}

watch(() => props.active, () => {
  group.value = false
  name.value = ''
  chosen.value = []
  inviteId.value = ''
})
watch(search, () => { inviteId.value = '' })
watch(invitable, (options) => {
  if (!options.some((neighbor) => neighbor.id === Number(inviteId.value))) inviteId.value = ''
})
</script>

<template>
  <NModal
    :show="active !== null"
    preset="card"
    :title="title"
    :aria-label="title"
    class="chat-dialog"
    :closable="!busy"
    :mask-closable="!busy"
    :close-on-esc="!busy"
    @update:show="(show) => { if (!show) emit('close') }"
  >
    <p v-if="error" class="dialog-error" role="alert">{{ error }}</p>

    <form v-if="active === 'create'" class="dialog-form" @submit.prevent="submit">
      <div class="chat-kind" role="group" aria-label="Tipo de conversación">
        <button type="button" :aria-pressed="!group" :disabled="busy" @click="changeKind(false)">
          <AppIcon name="user" :size="17" /> Directo
        </button>
        <button type="button" :aria-pressed="group" :disabled="busy" @click="changeKind(true)">
          <AppIcon name="users" :size="18" /> Grupo
        </button>
      </div>
      <label v-if="group" class="dialog-field">
        <span>Nombre del grupo</span>
        <NInput v-model:value="name" :maxlength="80" :disabled="busy" size="large" placeholder="Ej. Huerto de la cuadra" />
      </label>
      <label class="dialog-field">
        <span>Buscar vecinos</span>
        <NInput v-model:value="search" :disabled="busy" size="large" placeholder="Escribe un nombre" />
      </label>
      <p v-if="chosen.length" class="dialog-hint selection-summary">
        {{ group ? `${chosen.length} de 29 vecinos seleccionados` : `Para ${chosen[0]!.name}` }}
      </p>
      <p v-if="directoryLoading" class="dialog-hint" role="status">Buscando vecinos…</p>
      <div v-else-if="directoryError" class="directory-error" role="alert">
        <p class="dialog-error">{{ directoryError }}</p>
        <button type="button" @click="emit('retryDirectory')">Reintentar</button>
      </div>
      <p v-else-if="!neighbors.length" class="dialog-hint">No encontramos vecinos con ese nombre.</p>
      <div v-else class="neighbor-choices" aria-label="Resultados">
        <label v-for="neighbor in neighbors" :key="neighbor.id" :class="{ 'is-chosen': selectedIds.has(neighbor.id) }">
          <NeighborAvatar :size="34" :id="neighbor.id" :name="neighbor.name" :avatar="neighbor.avatar" aria-hidden="true" />
          <span>{{ neighbor.name }}</span>
          <input
            :type="group ? 'checkbox' : 'radio'"
            name="recipients"
            :checked="selectedIds.has(neighbor.id)"
            :disabled="busy || (group && chosen.length >= 29 && !selectedIds.has(neighbor.id))"
            @change="choose(neighbor)"
          />
        </label>
      </div>
      <NButton class="dialog-submit" attr-type="submit" type="primary" block :loading="busy" :disabled="!canCreate">
        {{ group ? 'Crear grupo' : 'Abrir conversación' }}
      </NButton>
    </form>

    <template v-else-if="active === 'members' && room">
      <p class="dialog-hint">{{ room.name || 'Conversación' }} · {{ room.members.length }} participantes</p>
      <ul class="member-list">
        <li v-for="neighbor in room.members" :key="neighbor.id" class="member-row">
          <NeighborLink :id="neighbor.id" :name="neighbor.name">
            <NeighborAvatar :size="34" :id="neighbor.id" :name="neighbor.name" :avatar="neighbor.avatar" />
            <span>{{ neighbor.name }} <small v-if="neighbor.id === currentUser?.id">(tú)</small></span>
          </NeighborLink>
          <NButton v-if="neighbor.id !== currentUser?.id" size="small" :disabled="busy" @click="emit('block', neighbor)">
            {{ neighbor.blocked_by_me ? 'Desbloquear' : 'Bloquear' }}
          </NButton>
        </li>
      </ul>
      <form v-if="canInvite" class="dialog-form invite-form" @submit.prevent="inviteId && emit('invite', Number(inviteId))">
        <h3>Añadir a un vecino</h3>
        <label class="dialog-field">
          <span>Buscar por nombre</span>
          <NInput v-model:value="search" size="large" :disabled="busy" placeholder="Escribe un nombre" />
        </label>
        <p v-if="directoryLoading" class="dialog-hint" role="status">Buscando vecinos…</p>
        <div v-else-if="directoryError" class="directory-error" role="alert">
          <p class="dialog-error">{{ directoryError }}</p>
          <button type="button" @click="emit('retryDirectory')">Reintentar</button>
        </div>
        <label v-else class="dialog-field">
          <span>Vecino a invitar</span>
          <select v-model="inviteId" :disabled="busy || !invitable.length">
            <option value="">{{ invitable.length ? 'Elige un vecino' : 'No hay vecinos disponibles' }}</option>
            <option v-for="neighbor in invitable" :key="neighbor.id" :value="String(neighbor.id)">{{ neighbor.name }}</option>
          </select>
        </label>
        <NButton attr-type="submit" secondary :disabled="!inviteId || busy || directoryLoading">Añadir al grupo</NButton>
      </form>
      <NButton v-if="room.kind === 'group'" class="leave-group" type="error" secondary :disabled="busy" @click="emit('leave')">
        <template #icon><AppIcon name="log-out" :size="18" /></template>
        Salir del grupo
      </NButton>
    </template>

    <template v-else-if="active === 'blocked'">
      <p v-if="blockedLoading" class="dialog-hint" role="status">Cargando bloqueos…</p>
      <NButton v-else-if="error" @click="emit('retryBlocked')">Reintentar</NButton>
      <p v-else-if="!blocked.length" class="dialog-hint">No has bloqueado a ningún vecino.</p>
      <ul v-else class="member-list">
        <li v-for="neighbor in blocked" :key="neighbor.id" class="member-row">
          <span class="blocked-person">
            <NeighborAvatar :size="34" :id="neighbor.id" :name="neighbor.name" :avatar="neighbor.avatar" aria-hidden="true" />
            {{ neighbor.name }}
          </span>
          <NButton size="small" :disabled="busy" @click="emit('block', neighbor)">Desbloquear</NButton>
        </li>
      </ul>
    </template>
  </NModal>
</template>

<style scoped>
:global(.chat-dialog) {
  width: min(480px, calc(100vw - 24px));
  max-height: calc(100dvh - 32px);
  overflow: auto;
  border: 1px solid var(--line);
}

.dialog-form {
  display: grid;
  gap: 12px;
}

.chat-kind {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px;
  padding: 4px;
  border-radius: 10px;
  background: var(--surface);
}

.chat-kind button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--muted);
  font-weight: 800;
}

.chat-kind button[aria-pressed='true'] {
  background: var(--surface-raised);
  color: var(--text);
}

.dialog-field {
  display: grid;
  min-width: 0;
  gap: 5px;
  color: var(--text);
  font-size: 0.8125rem;
  font-weight: 800;
}

.dialog-field select {
  width: 100%;
  min-height: 44px;
  padding-inline: 9px;
  border: 1px solid var(--line);
  border-radius: 9px;
  background: var(--surface);
  color: var(--text);
  font-size: 1rem;
}

.dialog-form :deep(.n-input__input-el) {
  font-size: 1rem;
}

.dialog-hint,
.dialog-error {
  margin: 0;
  color: var(--muted);
  font-size: 0.875rem;
  overflow-wrap: anywhere;
}

.dialog-error {
  margin-bottom: 8px;
  color: var(--local-accent-text);
}

.directory-error button {
  min-height: 44px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--surface);
  color: var(--text);
}

.selection-summary {
  font-weight: 750;
}

.neighbor-choices {
  max-height: min(235px, 30dvh);
  overflow: auto;
  border: 1px solid var(--line);
  border-radius: 10px;
}

.neighbor-choices label,
.member-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 54px;
  padding: 6px 8px;
  color: var(--text);
  font-size: 0.875rem;
  overflow-wrap: anywhere;
}

.neighbor-choices label {
  cursor: pointer;
}

.neighbor-choices label + label,
.member-row + .member-row {
  border-top: 1px solid var(--line);
}

.neighbor-choices label.is-chosen {
  background: var(--local-accent-fill);
}

.neighbor-choices input {
  flex: none;
  margin-inline-start: auto;
  accent-color: var(--coral);
}

.member-list {
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
}

.member-row {
  justify-content: space-between;
  padding-inline: 0;
}

.member-row :deep(.neighbor-link),
.blocked-person {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  min-height: 44px;
}

.member-row small {
  color: var(--muted);
}

.invite-form {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--line);
}

.invite-form h3 {
  margin: 0;
  font-size: 0.9375rem;
}

.leave-group {
  margin-top: 12px;
}

.dialog-submit.n-button {
  color: var(--surface);
}

:deep(.n-button) {
  min-height: 44px;
  font-weight: 800;
}

@media (hover: hover) {
  .neighbor-choices label:hover:not(.is-chosen),
  .chat-kind button:hover:not(:disabled) {
    background: var(--surface-raised);
  }
}
</style>
