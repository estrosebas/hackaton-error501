<script setup lang="ts">
import { computed, useTemplateRef, watch } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../AppIcon.vue'
import NeighborLink from '../neighbors/NeighborLink.vue'
import NeighborAvatar from '../neighbors/NeighborAvatar.vue'
import ChatAvatar from './ChatAvatar.vue'
import { chatDay, chatTime, dayKey } from './chatDates'
import { currentUser } from '../../composables/useAuth'
import type { ChatMessage, ChatRoom } from '../../composables/useChats'

const props = defineProps<{
  room: ChatRoom | null
  selected: number | null
  messages: ChatMessage[]
  opening: boolean
  error: string
  historyError: string
  sendError: string
  historyLoading: boolean
  hasOlder: boolean
  atLatest: boolean
  sending: boolean
}>()
const draft = defineModel<string>('draft', { required: true })
const emit = defineEmits<{
  settings: []
  send: []
  older: []
  latest: []
  scroll: []
  retry: []
  historyElement: [node: HTMLElement | null]
}>()
const history = useTemplateRef<HTMLElement>('history')
watch(history, (node) => emit('historyElement', node))

const peer = computed(() => props.room?.kind === 'direct'
  ? props.room.members.find((member) => member.id !== currentUser.value?.id) : undefined)
const subtitle = computed(() => props.room?.kind === 'group'
  ? `${props.room.members.length} participantes` : peer.value?.neighborhood || 'Chat directo')

interface MessageDay {
  key: string
  at: string
  runs: ChatMessage[][]
}

const days = computed(() => {
  const result: MessageDay[] = []
  for (const message of props.messages) {
    const key = dayKey(new Date(message.at))
    let day = result.at(-1)
    if (!day || day.key !== key) {
      day = { key, at: message.at, runs: [] }
      result.push(day)
    }
    const run = day.runs.at(-1)
    const previous = run?.at(-1)
    if (run && previous?.author.id === message.author.id &&
        Date.parse(message.at) - Date.parse(previous.at) < 5 * 60 * 1000) {
      run.push(message)
    } else {
      day.runs.push([message])
    }
  }
  return result
})

function onEnter(event: KeyboardEvent) {
  if (event.isComposing || !matchMedia('(hover: hover) and (pointer: fine)').matches) return
  event.preventDefault()
  emit('send')
}

function goToLatest() {
  emit('latest')
  history.value?.focus({ preventScroll: true })
}
</script>

<template>
  <section class="chat-thread" aria-label="Conversación">
    <header v-if="selected" class="thread-header">
      <RouterLink class="thread-action thread-back" :to="{ name: 'messages' }" aria-label="Volver a mensajes">
        <AppIcon name="arrow" :size="22" />
      </RouterLink>
      <component
        v-if="room"
        :is="peer ? RouterLink : 'div'"
        v-bind="peer ? { to: { name: 'neighbor-profile', params: { neighborId: peer.id } } } : {}"
        class="thread-identity"
      >
        <ChatAvatar :size="42" :group="room.kind === 'group'" :name="room.name || 'Conversación'" :peer="peer" />
        <div>
          <h2>{{ room.name || 'Conversación' }}</h2>
          <small>{{ subtitle }}</small>
        </div>
      </component>
      <h2 v-else class="thread-placeholder">Conversación</h2>
      <button
        v-if="room"
        type="button"
        class="thread-action thread-settings"
        aria-label="Participantes y bloqueos"
        title="Participantes y bloqueos"
        aria-haspopup="dialog"
        @click="emit('settings')"
      >
        <AppIcon name="more" :size="22" />
      </button>
    </header>

    <template v-if="room">
      <div v-if="error" class="thread-notice" role="alert">
        <span>{{ error }}</span>
        <button type="button" class="thread-link" @click="emit('retry')">Reintentar</button>
      </div>
      <div class="history-frame">
        <div ref="history" class="history" role="log" aria-label="Historial de mensajes" tabindex="0" @scroll.passive="emit('scroll')">
          <div class="history-content">
            <button
              v-if="hasOlder"
              type="button"
              class="thread-link older-messages"
              :disabled="historyLoading"
              @click="emit('older')"
            >{{ historyLoading ? 'Cargando…' : 'Mensajes anteriores' }}</button>
            <p v-if="historyError" class="history-error" role="alert">{{ historyError }}</p>
            <div v-if="!messages.length" class="thread-empty">
              <AppIcon name="chat" :size="28" />
              <h3>Primer mensaje</h3>
              <p>Este chat todavía no tiene mensajes.</p>
            </div>
            <div v-else class="message-days">
              <div v-for="day in days" :key="day.key" class="message-day">
                <div class="day-divider"><span>{{ chatDay(day.at) }}</span></div>
                <article
                  v-for="run in day.runs"
                  :key="run[0]!.id"
                  class="message-run"
                  :class="{ 'is-own': run[0]!.owned }"
                  :aria-label="run[0]!.owned ? 'Tú' : run[0]!.author.name"
                >
                  <NeighborLink
                    v-if="!run[0]!.owned && room.kind === 'group'"
                    :id="run[0]!.author.id"
                    :name="run[0]!.author.name"
                    class="message-author"
                  >
                    <NeighborAvatar :id="run[0]!.author.id" :name="run[0]!.author.name" :avatar="run[0]!.author.avatar" :size="24" />
                    <strong>{{ run[0]!.author.name }}</strong>
                  </NeighborLink>
                  <p v-for="message in run" :key="message.id" class="bubble">{{ message.body }}</p>
                  <time :datetime="run.at(-1)!.at" class="message-time">{{ chatTime(run.at(-1)!.at) }}</time>
                </article>
              </div>
            </div>
          </div>
        </div>
        <button v-if="!atLatest" type="button" class="latest-messages" @click="goToLatest">
          <AppIcon name="chevron" :size="18" /> Ir al final
        </button>
      </div>
      <footer class="thread-footer">
        <div v-if="!room.can_send" class="thread-notice">
          <AppIcon name="lock" :size="18" />
          <span>El envío está pausado por un bloqueo.</span>
          <button type="button" class="thread-link" @click="emit('settings')">Ver participantes</button>
        </div>
        <form v-else @submit.prevent="emit('send')">
          <p v-if="sendError" class="history-error" role="alert">{{ sendError }}</p>
          <div class="compose-row">
            <textarea
              v-model="draft"
              aria-label="Tu mensaje"
              placeholder="Escribe un mensaje"
              maxlength="2000"
              rows="1"
              :readonly="sending"
              @keydown.enter.exact="onEnter"
            />
            <button
              class="send-message"
              type="submit"
              :disabled="!draft.trim() || sending"
              :aria-label="sending ? 'Enviando mensaje' : 'Enviar mensaje'"
            >
              <AppIcon name="send" :size="21" />
            </button>
          </div>
          <div class="compose-meta">
            <span class="compose-keyboard">Enter para enviar · Mayús + Enter para una línea nueva</span>
            <span v-if="draft.length >= 1900" class="compose-count">{{ draft.length }}/2000</span>
          </div>
        </form>
      </footer>
    </template>

    <div v-else class="thread-empty thread-status">
      <template v-if="opening">
        <p role="status">Abriendo conversación…</p>
      </template>
      <template v-else-if="error">
        <AppIcon name="chat" :size="30" />
        <h2>El chat no está disponible</h2>
        <p role="alert">{{ error }}</p>
        <button type="button" class="thread-link" @click="emit('retry')">Reintentar</button>
      </template>
      <template v-else>
        <AppIcon name="chat" :size="34" />
        <h2>Abre una conversación</h2>
        <p>Elige un chat de la lista para ver sus mensajes.</p>
      </template>
    </div>
  </section>
</template>

<style scoped>
.chat-thread {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  background: var(--surface);
}

.thread-header {
  display: flex;
  flex: none;
  align-items: center;
  gap: 10px;
  min-height: 70px;
  padding: 8px 16px;
  border-bottom: 1px solid var(--line);
  background: var(--surface-soft);
}

.thread-identity {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 10px;
  min-width: 0;
  min-height: 46px;
  border-radius: 8px;
  text-decoration: none;
}

.thread-identity > div {
  min-width: 0;
}

.thread-identity h2,
.thread-placeholder {
  margin: 0;
  overflow: hidden;
  color: var(--text);
  font-size: 1.0625rem;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.thread-identity small {
  display: block;
  overflow: hidden;
  color: var(--muted);
  font-size: 0.8125rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.thread-action {
  display: grid;
  flex: none;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--lavender);
}

.thread-back svg {
  transform: rotate(180deg);
}

.thread-settings {
  margin-inline-start: auto;
  background: var(--surface-raised);
}

.history-frame {
  position: relative;
  flex: 1;
  min-height: 0;
}

.history {
  position: absolute;
  inset: 0;
  overflow: auto;
  overscroll-behavior: contain;
  overflow-anchor: none;
  scrollbar-width: thin;
  scrollbar-color: var(--line) transparent;
}

.history:focus-visible {
  outline: 2px solid var(--lavender);
  outline-offset: -2px;
}

.history-content {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 100%;
  max-width: 760px;
  margin-inline: auto;
  padding: 10px 24px 16px;
}

.message-days {
  display: grid;
  gap: 16px;
  margin-top: auto;
}

.message-day {
  display: flex;
  flex-direction: column;
  gap: 9px;
  min-width: 0;
}

.day-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 3px 0;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 800;
}

.day-divider::before,
.day-divider::after {
  flex: 1;
  height: 1px;
  background: var(--line);
  content: '';
}

.message-run {
  display: flex;
  flex-direction: column;
  align-items: start;
  align-self: start;
  gap: 3px;
  min-width: 0;
  max-width: min(85%, 54ch);
}

.message-run.is-own {
  align-items: end;
  align-self: end;
}

.message-run :deep(.message-author) {
  gap: 6px;
  min-height: 32px;
  margin-bottom: 1px;
  color: var(--lavender);
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
}

.bubble {
  max-width: 100%;
  margin: 0;
  padding: 8px 12px;
  border-radius: 12px 12px 12px 4px;
  background: var(--surface-raised);
  color: var(--text);
  font-size: 0.9375rem;
  line-height: 1.48;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.bubble + .bubble {
  border-top-left-radius: 4px;
}

.is-own .bubble {
  border-radius: 12px 12px 4px 12px;
  background: #3b2943;
}

.is-own .bubble + .bubble {
  border-top-right-radius: 4px;
}

.message-time {
  margin-inline: 3px;
  color: var(--muted);
  font-size: 0.6875rem;
  font-variant-numeric: tabular-nums;
}

.thread-empty {
  display: grid;
  align-content: center;
  justify-items: center;
  flex: 1;
  gap: 8px;
  padding: 18px;
  color: var(--muted);
  text-align: center;
}

.thread-empty svg {
  margin-bottom: 4px;
  color: var(--lavender);
}

.thread-empty h2,
.thread-empty h3 {
  margin: 0;
  color: var(--text);
  font-size: 1.125rem;
  font-weight: 900;
}

.thread-empty p {
  margin: 0;
  font-size: 0.875rem;
  overflow-wrap: anywhere;
}

.latest-messages,
.thread-link {
  min-height: 44px;
  padding-inline: 10px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: var(--local-accent-text);
  font-size: 0.8125rem;
  font-weight: 850;
}

.older-messages {
  align-self: center;
}

.latest-messages {
  position: absolute;
  inset: auto 12px 10px auto;
  display: flex;
  align-items: center;
  gap: 4px;
  border: 1px solid var(--local-accent-line);
  background: var(--surface-soft);
}

.thread-footer {
  flex: none;
  padding: 10px 16px;
  border-top: 1px solid var(--line);
  background: var(--surface-soft);
}

.compose-row {
  display: flex;
  align-items: end;
  gap: 8px;
}

.compose-row textarea {
  flex: 1;
  field-sizing: content;
  min-width: 0;
  min-height: 46px;
  max-height: 108px;
  resize: none;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--surface);
  color: var(--text);
  font-size: 1rem;
  line-height: 1.5;
}

.compose-row textarea::placeholder {
  color: var(--muted);
}

.compose-row textarea:focus-visible {
  outline: 2px solid var(--lavender);
  outline-offset: -1px;
}

.send-message {
  display: grid;
  flex: none;
  place-items: center;
  width: 46px;
  height: 46px;
  padding: 0;
  border: 0;
  border-radius: 10px;
  background: var(--coral);
  color: var(--surface);
}

.send-message:disabled {
  background: var(--surface-raised);
  color: var(--muted);
  cursor: default;
}

.compose-meta {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  color: var(--muted);
  font-size: 0.6875rem;
  line-height: 1.4;
}

.compose-meta span {
  margin-top: 5px;
}

.compose-count {
  margin-inline-start: auto;
}

.compose-keyboard {
  display: none;
}

.thread-notice {
  display: flex;
  flex: none;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px 8px;
  padding-inline: 12px;
  color: var(--local-accent-text);
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
}

.history-error {
  margin: 0 0 6px;
  color: var(--local-accent-text);
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
}

@media (hover: hover) and (pointer: fine) {
  .compose-keyboard {
    display: block;
  }

  .thread-action:hover,
  .thread-link:hover:not(:disabled) {
    background: var(--surface-raised);
  }

  a.thread-identity:hover h2 {
    color: var(--lavender);
  }

  .send-message:hover:not(:disabled) {
    background: var(--local-accent-text);
  }
}

@media (min-width: 760px) {
  .thread-back {
    display: none;
  }
}

@media (max-width: 759px) {
  .thread-header {
    min-height: calc(62px + env(safe-area-inset-top, 0px));
    padding: calc(7px + env(safe-area-inset-top, 0px)) 10px 7px;
    gap: 6px;
  }

  .history-content {
    padding-inline: 14px;
  }

  .thread-footer {
    padding: 8px 10px max(8px, env(safe-area-inset-bottom, 0px));
  }
}
</style>
