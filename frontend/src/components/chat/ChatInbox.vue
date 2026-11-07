<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../AppIcon.vue'
import ChatAvatar from './ChatAvatar.vue'
import { chatDay } from './chatDates'
import { currentUser } from '../../composables/useAuth'
import type { ChatRoom } from '../../composables/useChats'

const props = defineProps<{
  rooms: ChatRoom[]
  selected: number | null
  loading: boolean
  ready: boolean
  error: string
  hasMore: boolean
}>()
const emit = defineEmits<{ create: []; blocked: []; more: []; retry: [] }>()
const query = shallowRef('')
const unreadOnly = shallowRef(false)

function normalize(value: string) {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es-PE').trim()
}

const visibleRooms = computed(() => {
  const term = normalize(query.value)
  return props.rooms.filter((room) =>
    (!unreadOnly.value || room.unread > 0) && normalize(room.name || 'Conversación').includes(term),
  )
})
</script>

<template>
  <aside class="chat-inbox" aria-labelledby="inbox-title">
    <header class="inbox-header">
      <div class="inbox-title-row">
        <h1 id="inbox-title" tabindex="-1">Mensajes</h1>
        <button class="new-chat" type="button" aria-haspopup="dialog" @click="emit('create')">
          <AppIcon name="plus" :size="19" /> Nuevo chat
        </button>
      </div>
      <div class="inbox-search">
        <AppIcon name="search" :size="18" />
        <input v-model="query" type="search" aria-label="Buscar conversaciones" placeholder="Buscar conversaciones" />
        <button v-if="query" type="button" aria-label="Limpiar búsqueda" @click="query = ''">
          <AppIcon name="close" :size="17" />
        </button>
      </div>
      <div class="inbox-filters" role="group" aria-label="Filtrar conversaciones">
        <button type="button" :aria-pressed="!unreadOnly" @click="unreadOnly = false">Todas</button>
        <button type="button" :aria-pressed="unreadOnly" @click="unreadOnly = true">Sin leer</button>
      </div>
    </header>

    <div class="inbox-scroll">
      <div v-if="error" class="inbox-notice" role="alert">
        <p>{{ error }}</p>
        <button type="button" class="quiet-action" :disabled="loading" @click="emit('retry')">Reintentar</button>
      </div>
      <div v-if="!ready && !error" class="inbox-skeleton" role="status" aria-label="Cargando conversaciones">
        <span v-for="index in 4" :key="index" aria-hidden="true"><i></i><i></i></span>
      </div>
      <ul v-if="visibleRooms.length" class="conversation-list">
        <li v-for="item in visibleRooms" :key="item.id">
          <RouterLink
            :to="{ name: 'messages', params: { roomId: item.id } }"
            class="conversation"
            :class="{ 'is-selected': item.id === selected, 'is-unread': item.unread > 0 }"
          >
            <ChatAvatar
              :size="44"
              :name="item.name || 'Conversación'"
              :group="item.kind === 'group'"
              :peer="item.kind === 'direct' ? item.members.find((member) => member.id !== currentUser?.id) : undefined"
            />
            <span class="conversation-copy">
              <strong>{{ item.name || 'Conversación' }}</strong>
              <span>{{ item.last_message?.body || 'Aún no hay mensajes' }}</span>
            </span>
            <span v-if="item.last_message || item.unread" class="conversation-meta">
              <time v-if="item.last_message" :datetime="item.last_message.at">{{ chatDay(item.last_message.at, true) }}</time>
              <span v-if="item.unread" class="unread" :aria-label="`${item.unread} sin leer`">{{ item.unread > 99 ? '99+' : item.unread }}</span>
            </span>
          </RouterLink>
        </li>
      </ul>
      <div v-else-if="ready && !error" class="inbox-empty">
        <AppIcon :name="query ? 'search' : 'chat'" :size="25" />
        <strong>{{ query ? 'No encontramos coincidencias' : unreadOnly ? 'Estás al día' : 'Aún no tienes conversaciones' }}</strong>
        <p v-if="hasMore && (query || unreadOnly)">Carga más conversaciones para seguir buscando.</p>
        <p v-else-if="query">Prueba con otro nombre.</p>
        <p v-else-if="unreadOnly">Los mensajes pendientes aparecerán aquí.</p>
        <button v-else type="button" class="quiet-action" @click="emit('create')">Escribir a un vecino</button>
      </div>
      <button
        v-if="hasMore"
        type="button"
        class="quiet-action load-rooms"
        :disabled="loading"
        @click="emit('more')"
      >{{ loading ? 'Cargando…' : 'Cargar más conversaciones' }}</button>
    </div>

    <footer class="inbox-footer">
      <button type="button" class="blocked-link" aria-haspopup="dialog" @click="emit('blocked')">
        <AppIcon name="lock" :size="16" /> Vecinos bloqueados
      </button>
    </footer>
  </aside>
</template>

<style scoped>
.chat-inbox {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  min-width: 0;
  min-height: 0;
  background: var(--surface-soft);
}

.inbox-header {
  display: grid;
  gap: 12px;
  padding: 18px 16px 8px;
}

.inbox-title-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 6px 12px;
}

h1 {
  margin: 0;
  font-size: 1.625rem;
  font-weight: 900;
  letter-spacing: -0.03em;
  line-height: 1.2;
}

.new-chat {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 44px;
  padding: 0 10px;
  border: 1px solid var(--local-accent-line);
  border-radius: 10px;
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
  font-size: 0.875rem;
  font-weight: 850;
}

.inbox-search {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding-inline: 10px 0;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--surface);
  color: var(--muted);
}

.inbox-search:focus-within {
  border-color: var(--lavender);
}

.inbox-search input {
  flex: 1;
  min-width: 0;
  min-height: 42px;
  padding: 0 8px;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--text);
  font-size: 1rem;
}

.inbox-search input::placeholder {
  color: var(--muted);
}

.inbox-search input::-webkit-search-cancel-button {
  appearance: none;
}

.inbox-search button,
.inbox-filters button,
.quiet-action,
.blocked-link {
  min-height: 44px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--muted);
  font-size: 0.8125rem;
  font-weight: 800;
}

.inbox-search button {
  display: grid;
  place-items: center;
  min-width: 44px;
}

.inbox-filters {
  display: flex;
  gap: 5px;
}

.inbox-filters button {
  padding: 0 12px;
}

.inbox-filters button[aria-pressed='true'] {
  background: var(--surface-raised);
  color: var(--text);
}

.inbox-scroll {
  min-height: 0;
  overflow: auto;
  scrollbar-width: thin;
  scrollbar-color: var(--line) transparent;
  overscroll-behavior: contain;
}

.conversation-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.conversation-list li + li {
  border-top: 1px solid var(--line);
}

.conversation {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 70px;
  padding: 9px 16px;
  color: var(--text);
  text-decoration: none;
}

.conversation.is-selected {
  background: #27243b;
  box-shadow: inset 1px 0 var(--coral);
}

.conversation:focus-visible {
  position: relative;
  outline-offset: -3px;
}

.conversation-copy {
  flex: 1;
  display: grid;
  gap: 3px;
  min-width: 0;
}

.conversation-copy > * {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conversation-copy strong {
  font-size: 0.9375rem;
  font-weight: 780;
}

.conversation-copy > span {
  color: var(--muted);
  font-size: 0.8125rem;
}

.is-unread .conversation-copy strong,
.is-unread .conversation-copy > span {
  color: var(--text);
  font-weight: 850;
}

.conversation-meta {
  display: grid;
  justify-items: end;
  gap: 4px;
  align-self: center;
  color: var(--muted);
  font-size: 0.6875rem;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.unread {
  display: inline-grid;
  place-items: center;
  min-width: 20px;
  min-height: 20px;
  padding-inline: 4px;
  border-radius: 10px;
  background: var(--coral);
  color: var(--surface);
  font-size: 0.6875rem;
  font-weight: 900;
}

.inbox-empty,
.inbox-notice {
  display: grid;
  justify-items: start;
  gap: 8px;
  padding: 24px 16px;
  color: var(--muted);
  font-size: 0.875rem;
}

.inbox-empty strong {
  color: var(--text);
  font-size: 0.9375rem;
}

.inbox-empty p,
.inbox-notice p {
  margin: 0;
  overflow-wrap: anywhere;
}

.inbox-notice {
  color: var(--local-accent-text);
}

.quiet-action {
  padding-inline: 10px;
  color: var(--local-accent-text);
}

.load-rooms {
  width: 100%;
}

.inbox-footer {
  padding: 0 8px;
  border-top: 1px solid var(--line);
}

.blocked-link {
  display: flex;
  align-items: center;
  gap: 7px;
  padding-inline: 8px;
}

.inbox-skeleton > span {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 70px;
  padding-inline: 16px;
  border-bottom: 1px solid var(--line);
}

.inbox-skeleton i {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--surface-raised);
}

.inbox-skeleton i + i {
  width: 52%;
  height: 14px;
  border-radius: 5px;
}

@media (hover: hover) {
  .conversation:hover:not(.is-selected),
  .inbox-search button:hover,
  .inbox-filters button:hover,
  .quiet-action:hover:not(:disabled),
  .blocked-link:hover {
    background: var(--surface-raised);
    color: var(--text);
  }

  .new-chat:hover {
    border-color: var(--local-accent-text);
  }
}

@media (max-width: 359px) {
  .inbox-header,
  .conversation {
    padding-inline: 12px;
  }

  h1 {
    font-size: 1.375rem;
  }
}
</style>
