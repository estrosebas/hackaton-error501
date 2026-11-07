<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NModal, NInput } from 'naive-ui'
import AppIcon from '../components/AppIcon.vue'
import NeighborLink from '../components/neighbors/NeighborLink.vue'
import NeighborAvatar from '../components/neighbors/NeighborAvatar.vue'
import { api } from '../services/api'
import { currentUser } from '../composables/useAuth'
interface Neighbor {
  id: number
  name: string
  avatar: string
  neighborhood: string
  blocked_by_me?: boolean
}
interface Room {
  id: number
  kind: 'direct' | 'group'
  name: string
  owner_id: number
  members: Neighbor[]
  can_send: boolean
  unread: number
  last_message: { body: string; at: string } | null
}
interface Message {
  id: number
  author: Neighbor
  body: string
  at: string
  owned: boolean
}
const route = useRoute(),
  router = useRouter()
const rooms = ref<Room[]>([]),
  room = ref<Room | null>(null),
  messages = ref<Message[]>([])
const neighbors = ref<Neighbor[]>([]),
  blocked = ref<Neighbor[]>([])
const error = ref(''),
  draft = ref(''),
  sending = ref(false),
  loading = ref(false),
  busy = ref(false),
  listLoading = ref(false)
const showCreate = ref(false),
  showMembers = ref(false),
  showBlocked = ref(false),
  group = ref(false)
const search = ref(''),
  roomName = ref(''),
  chosen = ref<number[]>([]),
  inviteId = ref('')
const older = ref<number | null>(null),
  nextPage = ref<number | null>(null),
  thread = ref<HTMLElement | null>(null)
const matching = computed(() =>
  neighbors.value.filter((n) =>
    n.name.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()),
  ),
)
const invitable = computed(() =>
  neighbors.value.filter(
    (n) => !room.value?.members.some((m) => m.id === n.id),
  ),
)
const owner = computed(() => room.value?.owner_id === currentUser.value?.id)
const directNeighbor = computed(() => room.value?.kind === 'direct' ? peer(room.value) : undefined)
function peer(item: Room) { return item.members.find(member => member.id !== currentUser.value?.id) }
let generation = 0,
  polling = false,
  disposed = false,
  timer: ReturnType<typeof setInterval> | undefined
let searchTimer: ReturnType<typeof setTimeout> | undefined,
  directoryVersion = 0
let loadedPages = 1
function fail(e: unknown) {
  if (!disposed)
    error.value =
      e instanceof Error ? e.message : 'No pudimos completar la solicitud.'
}
function retry() {
  error.value = ''
  if (room.value) void poll()
  else if (route.params.roomId) void openRoom()
  else void list()
}
function openBlocked() {
  showBlocked.value = true
  void loadBlocked()
}
function time(value: string) {
  return new Date(value).toLocaleString('es-PE', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}
async function list(more = false, preserve = false) {
  if (listLoading.value) return
  if (more && !nextPage.value) return
  const page = more ? nextPage.value! : 1
  listLoading.value = true
  try {
    const result = await api<{ data: Room[]; next_page: number | null }>(
      `/rooms?page=${page}`,
    )
    if (disposed) return
    rooms.value = more
      ? [
          ...rooms.value,
          ...result.data.filter(
            (r) => !rooms.value.some((old) => old.id === r.id),
          ),
        ]
      : preserve
        ? [...result.data, ...rooms.value.filter(old => !result.data.some(r => r.id === old.id))]
        : result.data
    if (more) loadedPages = page
    else if (!preserve) loadedPages = 1
    if (!preserve || loadedPages === 1) nextPage.value = result.next_page
  } catch (e) {
    fail(e)
  } finally {
    listLoading.value = false
  }
}
async function directory() {
  const version = ++directoryVersion
  const r = await api<{ data: Neighbor[] }>(
    `/neighbors?q=${encodeURIComponent(search.value)}`,
  )
  if (version === directoryVersion && !disposed) neighbors.value = r.data
}
async function scrollBottom() {
  await nextTick()
  if (thread.value) thread.value.scrollTop = thread.value.scrollHeight
}
async function markRead(id: number, items: Message[]) {
  const last = items.at(-1)
  if (last)
    await api(`/rooms/${id}/read`, {
      method: 'PUT',
      body: JSON.stringify({ message_id: last.id }),
    })
}
async function openRoom() {
  const version = ++generation,
    id = Number(route.params.roomId)
  room.value = null
  messages.value = []
  older.value = null
  draft.value = ''
  error.value = ''
  showMembers.value = false
  if (!id) return
  loading.value = true
  try {
    const [info, data] = await Promise.all([
      api<{ data: Room }>(`/rooms/${id}`),
      api<{ data: Message[]; older_cursor: number | null }>(
        `/rooms/${id}/messages`,
      ),
    ])
    if (version !== generation || disposed) return
    room.value = info.data
    messages.value = data.data
    older.value = data.older_cursor
    await scrollBottom()
    await markRead(id, data.data)
    const existing = rooms.value.find(item => item.id === id)
    if (existing) existing.unread = 0
    void list(false, true)
  } catch (e) {
    if (version === generation) fail(e)
  } finally {
    if (version === generation) loading.value = false
  }
}
async function openDirect(id: number) {
  busy.value = true
  error.value = ''
  try {
    const result = await api<{ data: Room }>(`/rooms/direct/${id}`, {
      method: 'POST',
    })
    showCreate.value = false
    await list()
    await router.replace(`/mensajes/${result.data.id}`)
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}
async function create() {
  if (!group.value) {
    if (chosen.value[0]) await openDirect(chosen.value[0])
    return
  }
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    const result = await api<{ data: Room }>('/rooms', {
      method: 'POST',
      body: JSON.stringify({ name: roomName.value, members: chosen.value }),
    })
    showCreate.value = false
    chosen.value = []
    roomName.value = ''
    await list()
    await router.push(`/mensajes/${result.data.id}`)
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}
async function send() {
  const active = room.value,
    text = draft.value.trim()
  if (!active || !text || sending.value || !active.can_send) return
  const version = generation
  sending.value = true
  error.value = ''
  try {
    const r = await api<{ data: Message }>(`/rooms/${active.id}/messages`, {
      method: 'POST',
      body: JSON.stringify({ body: text }),
    })
    if (version !== generation) return
    if (!messages.value.some((m) => m.id === r.data.id))
      messages.value.push(r.data)
    messages.value.sort((a, b) => a.id - b.id)
    draft.value = ''
    await scrollBottom()
    void list(false, true)
  } catch (e) {
    if (version === generation) {
      fail(e)
      void poll()
    }
  } finally {
    sending.value = false
  }
}
async function moreMessages() {
  if (!room.value || !older.value || loading.value) return
  const id = room.value.id,
    version = generation,
    height = thread.value?.scrollHeight || 0
  loading.value = true
  try {
    const r = await api<{ data: Message[]; older_cursor: number | null }>(
      `/rooms/${id}/messages?before_id=${older.value}`,
    )
    if (version !== generation) return
    messages.value = [
      ...r.data.filter((m) => !messages.value.some((old) => old.id === m.id)),
      ...messages.value,
    ]
    older.value = r.older_cursor
    await nextTick()
    if (thread.value)
      thread.value.scrollTop = thread.value.scrollHeight - height
  } catch (e) {
    fail(e)
  } finally {
    loading.value = false
  }
}
async function poll() {
  if (polling || disposed || document.hidden || loading.value) return
  polling = true
  const id = room.value?.id,
    version = generation
  try {
    if (id) {
      const last = messages.value.at(-1)?.id || 0
      const atBottom =
        !thread.value ||
        thread.value.scrollHeight -
          thread.value.scrollTop -
          thread.value.clientHeight <
          90
      const [info, data] = await Promise.all([
        api<{ data: Room }>(`/rooms/${id}`),
        api<{ data: Message[] }>(`/rooms/${id}/messages?after_id=${last}`),
      ])
      if (version !== generation || disposed) return
      room.value = info.data
      const incoming = data.data.filter(
        (m) => !messages.value.some((old) => old.id === m.id),
      )
      messages.value.push(...incoming)
      messages.value.sort((a, b) => a.id - b.id)
      if (incoming.length) {
        if (atBottom) await scrollBottom()
        await markRead(id, messages.value)
      }
    }
    await list(false, true)
  } catch (e) {
    if (version === generation) fail(e)
  } finally {
    polling = false
  }
}
async function newConversation() {
  error.value = ''
  chosen.value = []
  search.value = ''
  group.value = false
  showCreate.value = true
  try {
    await directory()
  } catch (e) {
    fail(e)
  }
}
async function settings() {
  showMembers.value = true
  search.value = ''
  try {
    await directory()
  } catch (e) {
    fail(e)
  }
}
async function invite() {
  if (!room.value || !inviteId.value || busy.value) return
  const id = room.value.id
  busy.value = true
  try {
    const r = await api<{ data: Room }>(`/rooms/${id}/members`, {
      method: 'POST',
      body: JSON.stringify({ user_id: Number(inviteId.value) }),
    })
    if (room.value?.id === id) room.value = r.data
    inviteId.value = ''
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}
async function block(neighbor: Neighbor) {
  if (busy.value) return
  if (
    !neighbor.blocked_by_me &&
    !confirm(
      `¿Bloquear a ${neighbor.name}? No podrán enviarse mensajes ni invitarse a salas.`,
    )
  )
    return
  busy.value = true
  try {
    await api(`/neighbors/${neighbor.id}/block`, {
      method: 'PUT',
      body: JSON.stringify({ blocked: !neighbor.blocked_by_me }),
    })
    if (room.value) {
      const r = await api<{ data: Room }>(`/rooms/${room.value.id}`)
      room.value = r.data
    }
    await loadBlocked()
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}
async function loadBlocked() {
  try {
    const r = await api<{ data: Neighbor[] }>('/neighbors/blocked')
    blocked.value = r.data.map((n) => ({ ...n, blocked_by_me: true }))
  } catch (e) {
    fail(e)
  }
}
async function leave() {
  if (
    !room.value ||
    !confirm('¿Salir de esta sala? Dejarás de recibir sus mensajes.')
  )
    return
  busy.value = true
  try {
    await api(`/rooms/${room.value.id}/membership`, { method: 'DELETE' })
    showMembers.value = false
    await router.push('/mensajes')
    await list()
  } catch (e) {
    fail(e)
  } finally {
    busy.value = false
  }
}
watch(
  () => route.params.roomId,
  () => {
    void openRoom()
  },
  { immediate: true },
)
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void directory().catch(fail)
  }, 250)
})
watch(
  () => route.query.to,
  (value) => {
    if (value && /^\d+$/.test(String(value))) void openDirect(Number(value))
  },
  { immediate: true },
)
onMounted(() => {
  void list()
  timer = setInterval(() => {
    void poll()
  }, 3000)
})
onBeforeUnmount(() => {
  disposed = true
  generation++
  directoryVersion++
  clearInterval(timer)
  clearTimeout(searchTimer)
})
</script>

<template>
  <section class="standard-page chats-page">
    <header class="chats-heading">
      <div>
        <h1>Tus conversaciones</h1>
        <p>Un mensaje acerca a los vecinos.</p>
      </div>
      <NButton
        type="primary"
        round
        aria-label="Nueva conversación"
        @click="newConversation"
        ><AppIcon name="plus" :size="18"
      /></NButton>
    </header>
    <p v-if="error" role="alert" class="chat-error">
      {{ error }}
      <button
        class="text-link"
        @click="retry"
      >
        Reintentar
      </button>
    </p>
    <div class="chats-layout" :class="{ 'has-room': !!route.params.roomId }">
      <aside class="room-list" aria-label="Conversaciones">
        <button
          class="blocked-link"
          @click="openBlocked"
        >
          Vecinos bloqueados
        </button>
        <p v-if="listLoading && !rooms.length" role="status">
          Cargando conversaciones…
        </p>
        <p v-if="!listLoading && !rooms.length" class="empty-chat">
          Todavía no hay conversaciones. Escribe a un vecino o crea una sala.
        </p>
        <button
          v-for="item in rooms"
          :key="item.id"
          class="room-row"
          :class="{ active: item.id === room?.id }"
          @click="router.push(`/mensajes/${item.id}`)"
        >
          <NeighborAvatar
            :size="42"
            :id="item.kind === 'direct' ? peer(item)?.id : undefined"
            :name="item.kind === 'group' ? '#' : item.name"
            :avatar="item.kind === 'direct' ? peer(item)?.avatar : undefined"
          />
          <span class="room-summary"
            ><strong>{{ item.name }}</strong
            ><small>{{
              item.last_message?.body || 'Inicia la conversación'
            }}</small></span
          ><b v-if="item.unread" class="unread-count">{{ item.unread }}</b>
        </button>
        <NButton
          v-if="nextPage"
          secondary
          block
          :loading="listLoading"
          @click="list(true)"
          >Más conversaciones</NButton
        >
      </aside>
      <section class="room-thread" aria-label="Sala de conversación">
        <div v-if="loading && !room" role="status" class="empty-chat">
          Abriendo conversación…
        </div>
        <template v-if="room">
          <header class="thread-heading">
            <button
              aria-label="Volver a conversaciones"
              @click="router.push('/mensajes')"
            >
              ←
            </button>
            <NeighborLink v-if="directNeighbor" :id="directNeighbor.id" :name="directNeighbor.name" class="thread-avatar">
              <NeighborAvatar :id="directNeighbor.id" :name="directNeighbor.name" :avatar="directNeighbor.avatar" :size="38" />
            </NeighborLink>
            <NeighborAvatar v-else :size="38" name="#" />
            <div>
              <h2>{{ room.name }}</h2>
              <small>{{
                room.kind === 'group'
                  ? `${room.members.length} vecinos en esta sala`
                  : 'Conversación entre vecinos'
              }}</small>
            </div>
            <NButton
              quaternary
              circle
              aria-label="Participantes y bloqueos"
              @click="settings"
              ><AppIcon name="users" :size="20"
            /></NButton>
          </header>
          <div
            ref="thread"
            class="message-history"
            role="log"
            aria-label="Mensajes"
            aria-live="polite"
            aria-relevant="additions"
          >
            <NButton
              v-if="older"
              quaternary
              block
              :loading="loading"
              @click="moreMessages"
              >Mensajes anteriores</NButton
            >
            <p v-if="!messages.length" class="empty-chat">
              Saluda y comienza a conversar. No necesitas agregar una amistad.
            </p>
            <article
              v-for="message in messages"
              :key="message.id"
              class="message"
              :class="{ 'message-own': message.owned }"
            >
              <NeighborLink
                v-if="!message.owned"
                :id="message.author.id"
                :name="message.author.name"
                class="message-author"
                ><NeighborAvatar :id="message.author.id" :name="message.author.name" :avatar="message.author.avatar" :size="24" /><strong>{{ message.author.name }}</strong></NeighborLink
              >
              <p>{{ message.body }}</p>
              <time :datetime="message.at">{{ time(message.at) }}</time>
            </article>
          </div>
          <p v-if="!room.can_send" class="blocked-notice">
            El envío está desactivado porque existe un bloqueo con un
            participante. Puedes gestionar tus bloqueos o salir de la sala.
          </p>
          <form class="message-compose" @submit.prevent="send">
            <textarea
              v-model="draft"
              aria-label="Tu mensaje"
              placeholder="Escribe un mensaje…"
              maxlength="2000"
              rows="2"
              :disabled="!room.can_send || sending"
              @keydown.enter.exact.prevent="send"
            /><NButton
              type="primary"
              circle
              :loading="sending"
              :disabled="!draft.trim() || !room.can_send"
              attr-type="submit"
              aria-label="Enviar mensaje"
              ><AppIcon name="send" :size="21"
            /></NButton>
          </form>
        </template>
        <p v-else-if="!loading" class="empty-chat">
          Elige una conversación o crea una sala para organizarse entre vecinos.
        </p>
      </section>
    </div>
    <NModal
      v-model:show="showCreate"
      preset="card"
      title="Una nueva conversación"
      class="chat-modal"
    >
      <p class="chat-error" v-if="error" role="alert">{{ error }}</p>
      <form @submit.prevent="create">
        <label class="chat-toggle"
          ><input v-model="group" type="checkbox" @change="chosen = []" /> Crear
          una sala grupal</label
        >
        <label v-if="group" class="chat-field"
          >Nombre de la sala<NInput
            v-model:value="roomName"
            maxlength="80"
            placeholder="Ej. Talleres del barrio"
        /></label>
        <label class="chat-field"
          >Buscar vecinos<NInput
            v-model:value="search"
            placeholder="Escribe su nombre"
        /></label>
        <div class="neighbor-choices">
          <label v-for="n in matching" :key="n.id"
            ><input
              v-if="group"
              v-model="chosen"
              :value="n.id"
              type="checkbox"
              :disabled="chosen.length >= 29 && !chosen.includes(n.id)"
            /><input
              v-else
              type="radio"
              name="recipient"
              :checked="chosen[0] === n.id"
              @change="chosen = [n.id]"
            /><NeighborAvatar :id="n.id" :name="n.name" :avatar="n.avatar" :size="30" /><span>{{ n.name }}</span></label
          >
          <p v-if="!matching.length">No encontramos vecinos con ese nombre.</p>
        </div>
        <NButton
          attr-type="submit"
          type="primary"
          round
          block
          :loading="busy"
          :disabled="!chosen.length || (group && !roomName.trim())"
          >{{ group ? 'Crear sala' : 'Escribir mensaje' }}</NButton
        >
      </form>
    </NModal>
    <NModal
      v-model:show="showMembers"
      preset="card"
      title="Vecinos en esta conversación"
      class="chat-modal"
    >
      <p class="chat-error" v-if="error" role="alert">{{ error }}</p>
      <div v-for="n in room?.members" :key="n.id" class="member-row">
        <NeighborLink :id="n.id" :name="n.name"
          ><NeighborAvatar :id="n.id" :name="n.name" :avatar="n.avatar" :size="32" /><span>{{ n.name }}</span></NeighborLink
        ><NButton
          v-if="n.id !== currentUser?.id"
          size="small"
          :loading="busy"
          @click="block(n)"
          >{{ n.blocked_by_me ? 'Desbloquear' : 'Bloquear' }}</NButton
        ><small v-else>Tú</small>
      </div>
      <form
        v-if="room?.kind === 'group' && owner"
        class="invite-form"
        @submit.prevent="invite"
      >
        <label class="chat-field"
          >Buscar vecino para invitar<NInput
            v-model:value="search"
            placeholder="Escribe su nombre" /></label
        ><label class="chat-field"
          >Invitar a un vecino<select
            v-model="inviteId"
            aria-label="Vecino a invitar"
          >
            <option value="">Elige un vecino</option>
            <option v-for="n in invitable" :key="n.id" :value="n.id">
              {{ n.name }}
            </option>
          </select></label
        ><NButton attr-type="submit" round :disabled="!inviteId" :loading="busy"
          >Añadir a la sala</NButton
        >
      </form>
      <NButton
        v-if="room?.kind === 'group'"
        block
        round
        secondary
        :loading="busy"
        @click="leave"
        >Salir de la sala</NButton
      >
    </NModal>
    <NModal
      v-model:show="showBlocked"
      preset="card"
      title="Vecinos bloqueados"
      class="chat-modal"
    >
      <p class="chat-error" v-if="error" role="alert">{{ error }}</p>
      <p v-if="!blocked.length" class="empty-chat">
        No has bloqueado a ningún vecino.
      </p>
      <div v-for="n in blocked" :key="n.id" class="member-row">
        <span>{{ n.name }}</span
        ><NButton size="small" :loading="busy" @click="block(n)"
          >Desbloquear</NButton
        >
      </div>
    </NModal>
  </section>
</template>

<style scoped>
.chats-page {
  max-width: 1040px;
  margin: auto;
}
.chats-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}
.chats-heading h1 {
  margin: 0;
  font-size: 1.5rem;
}
.chats-heading p {
  margin: 5px 0 0;
  color: var(--muted);
  font-size: 0.875rem;
}
.chats-layout {
  display: grid;
  grid-template-columns: minmax(220px, 0.8fr) minmax(0, 1.6fr);
  border: 1px solid var(--line);
  border-radius: 20px;
  overflow: hidden;
  background: var(--surface);
}
.room-list {
  border-right: 1px solid var(--line);
  min-width: 0;
  padding: 10px;
}
.blocked-link {
  border: 0;
  background: transparent;
  color: var(--muted);
  padding: 12px;
  font: inherit;
  font-size: 0.75rem;
}
.room-row {
  display: flex;
  width: 100%;
  gap: 10px;
  align-items: center;
  padding: 14px 10px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: var(--text);
  text-align: left;
  cursor: pointer;
}
.room-row:hover,
.room-row.active {
  background: var(--surface-soft);
}
.room-row > .room-summary {
  flex: 1;
  min-width: 0;
}
.room-row strong,
.room-row small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.room-row small {
  margin-top: 4px;
  color: var(--muted);
  font-size: 0.75rem;
}
.unread-count {
  background: var(--local-accent-text);
  color: var(--surface);
  padding: 3px 7px;
  border-radius: 12px;
}
.room-thread {
  min-width: 0;
}
.thread-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
  border-bottom: 1px solid var(--line);
}
.thread-heading :deep(.thread-avatar) { flex-shrink: 0; }
.message :deep(.message-author) { gap: 6px; }
.thread-heading > div {
  flex: 1;
  min-width: 0;
}
.thread-heading h2 {
  margin: 0;
  font-size: 1rem;
  overflow-wrap: anywhere;
}
.thread-heading small {
  color: var(--muted);
}
.thread-heading > button {
  background: transparent;
  color: var(--lavender);
  border: 0;
  min-height: 44px;
  min-width: 32px;
  cursor: pointer;
}
.message-history {
  height: 55dvh;
  min-height: 240px;
  max-height: 640px;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.message {
  align-self: flex-start;
  max-width: 88%;
  border: 1px solid var(--line);
  background: var(--surface-soft);
  border-radius: 16px 16px 16px 4px;
  padding: 10px 13px;
  overflow-wrap: anywhere;
}
.message-own {
  align-self: flex-end;
  background: var(--local-accent-fill);
  border-color: var(--local-accent-line);
  border-radius: 16px 16px 4px 16px;
}
.message strong {
  font-size: 0.75rem;
  color: var(--lavender);
}
.message p {
  white-space: pre-wrap;
  font-size: 0.875rem;
  line-height: 1.5;
  margin: 4px 0;
}
.message time {
  display: block;
  color: var(--muted);
  font-size: 0.625rem;
  text-align: right;
}
.message-compose {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 12px;
  border-top: 1px solid var(--line);
}
.message-compose textarea {
  flex: 1;
  min-width: 0;
  resize: vertical;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 12px;
  background: var(--surface-soft);
  color: var(--text);
  font: inherit;
  font-size: 0.875rem;
}
.empty-chat {
  padding: 20px;
  color: var(--muted);
  font-size: 0.875rem;
  line-height: 1.6;
}
.blocked-notice,
.chat-error {
  color: var(--local-accent-text);
  font-size: 0.8125rem;
  padding: 10px;
  overflow-wrap: anywhere;
}
.chat-field {
  display: grid;
  gap: 8px;
  margin: 14px 0;
  color: var(--lavender);
  font-size: 0.875rem;
}
.chat-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
}
.neighbor-choices {
  max-height: 280px;
  overflow: auto;
  margin: 12px 0;
}
.neighbor-choices label {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 10px;
  border-bottom: 1px solid var(--line);
}
.member-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 12px 0;
  border-bottom: 1px solid var(--line);
}
.member-row :deep(.neighbor-link) {
  gap: 8px;
}
.invite-form {
  padding: 12px 0;
}
.chat-field select {
  background: var(--surface-soft);
  color: var(--text);
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 12px;
}
input {
  accent-color: var(--local-accent-text);
}
:global(.chat-modal) {
  width: min(92vw, 520px) !important;
  max-height: 85dvh;
  overflow-y: auto;
  background: var(--surface) !important;
  border: 1px solid var(--line) !important;
  border-radius: 20px !important;
}
@media (max-width: 700px) {
  .chats-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .room-thread {
    display: none;
  }
  .has-room .room-list {
    display: none;
  }
  .has-room .room-thread {
    display: block;
  }
  .room-list {
    border-right: 0;
  }
  .message-history {
    height: 48dvh;
  }
  .chats-page {
    padding-inline: 12px;
  }
}
</style>
