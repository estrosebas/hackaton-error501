import { computed, nextTick, onBeforeUnmount, onMounted, reactive, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import { currentUser } from './useAuth'

export interface ChatNeighbor {
  id: number
  name: string
  avatar: string
  neighborhood: string
  blocked_by_me?: boolean
}

export interface ChatRoom {
  id: number
  kind: 'direct' | 'group'
  name: string | null
  owner_id: number
  members: ChatNeighbor[]
  can_send: boolean
  unread: number
  last_message: { body: string; at: string } | null
}

export interface ChatMessage {
  id: number
  author: ChatNeighbor
  body: string
  at: string
  owned: boolean
}

export type ChatDialog = 'create' | 'members' | 'blocked'

function errorText(error: unknown) {
  return error instanceof Error ? error.message : 'No pudimos completar la solicitud. Intenta de nuevo.'
}

function mergeMessages(current: ChatMessage[], incoming: ChatMessage[]) {
  const byId = new Map(current.map((message) => [message.id, message]))
  for (const message of incoming) byId.set(message.id, message)
  return [...byId.values()].sort((first, second) => first.id - second.id)
}

export function useChats() {
  const route = useRoute()
  const router = useRouter()
  const roomId = computed(() => Number(route.params.roomId) || null)
  const rooms = shallowRef<ChatRoom[]>([])
  const room = shallowRef<ChatRoom | null>(null)
  const messages = shallowRef<ChatMessage[]>([])
  const thread = shallowRef<HTMLElement | null>(null)
  const listLoading = shallowRef(false)
  const listReady = shallowRef(false)
  const listError = shallowRef('')
  const nextPage = shallowRef<number | null>(null)
  const opening = shallowRef(false)
  const threadError = shallowRef('')
  const historyLoading = shallowRef(false)
  const historyError = shallowRef('')
  const older = shallowRef<number | null>(null)
  const atLatest = shallowRef(true)
  const drafts = reactive<Record<number, string>>({})
  const sendErrors = reactive<Record<number, string>>({})
  const pendingSends = reactive(new Set<number>())
  const draft = computed({
    get: () => (roomId.value ? drafts[roomId.value] ?? '' : ''),
    set: (value: string) => { if (roomId.value) drafts[roomId.value] = value },
  })
  const sendError = computed(() => roomId.value ? sendErrors[roomId.value] ?? '' : '')
  const sending = computed(() => roomId.value !== null && pendingSends.has(roomId.value))
  const dialog = shallowRef<ChatDialog | null>(null)
  const dialogBusy = shallowRef(false)
  const dialogError = shallowRef('')
  const search = shallowRef('')
  const neighbors = shallowRef<ChatNeighbor[]>([])
  const directoryLoading = shallowRef(false)
  const directoryError = shallowRef('')
  const directoryAttempt = shallowRef(0)
  const blocked = shallowRef<ChatNeighbor[]>([])
  const blockedLoading = shallowRef(false)
  const canInvite = computed(() => room.value?.kind === 'group' && room.value.owner_id === currentUser.value?.id)

  let generation = 0
  let disposed = false
  let polling = false
  let loadedPages = 1
  let timer: ReturnType<typeof setInterval> | undefined
  const readIds = new Map<number, number>()
  const readingRooms = new Set<number>()

  async function list(mode: 'reset' | 'refresh' | 'more' = 'reset') {
    if (listLoading.value || (mode === 'more' && !nextPage.value)) return
    const page = mode === 'more' ? nextPage.value! : 1
    listLoading.value = true
    try {
      const result = await api<{ data: ChatRoom[]; next_page: number | null }>(`/rooms?page=${page}`)
      if (disposed) return
      const ids = new Set(result.data.map((item) => item.id))
      const previous = rooms.value.filter((item) => !ids.has(item.id))
      rooms.value = mode === 'more'
        ? [...rooms.value, ...result.data.filter((item) => !rooms.value.some((old) => old.id === item.id))]
        : mode === 'refresh' && loadedPages > 1 ? [...result.data, ...previous] : result.data
      if (mode !== 'refresh') loadedPages = page
      if (mode !== 'refresh' || loadedPages === 1) nextPage.value = result.next_page
      listReady.value = true
      listError.value = ''
    } catch (error) {
      if (!disposed) listError.value = errorText(error)
    } finally {
      listLoading.value = false
    }
  }

  async function markRead() {
    const id = room.value?.id
    const latest = messages.value.at(-1)?.id
    if (!id || !latest || !atLatest.value || document.hidden ||
        latest <= (readIds.get(id) ?? 0) || readingRooms.has(id)) return

    readingRooms.add(id)
    try {
      await api(`/rooms/${id}/read`, {
        method: 'PUT',
        body: JSON.stringify({ message_id: latest }),
      })
      readIds.set(id, latest)
      if (!disposed && messages.value.at(-1)?.id === latest && room.value?.id === id) {
        rooms.value = rooms.value.map((item) => item.id === id ? { ...item, unread: 0 } : item)
      }
    } catch {
      // The next refresh or return to the end retries the read marker.
    } finally {
      readingRooms.delete(id)
    }
  }

  function trackPosition() {
    const element = thread.value
    if (!element) return
    atLatest.value = element.scrollHeight - element.scrollTop - element.clientHeight < 72
    if (atLatest.value) void markRead()
  }

  async function scrollLatest() {
    await nextTick()
    const element = thread.value
    if (!element) return
    element.scrollTop = element.scrollHeight
    trackPosition()
  }

  async function openRoom() {
    const version = ++generation
    const id = roomId.value
    room.value = null
    messages.value = []
    older.value = null
    opening.value = Boolean(id)
    historyLoading.value = false
    atLatest.value = true
    threadError.value = ''
    historyError.value = ''
    if (dialog.value === 'members') dialog.value = null
    if (!id) return

    try {
      const [info, data] = await Promise.all([
        api<{ data: ChatRoom }>(`/rooms/${id}`),
        api<{ data: ChatMessage[]; older_cursor: number | null }>(`/rooms/${id}/messages`),
      ])
      if (version !== generation || disposed) return
      room.value = info.data
      messages.value = data.data
      older.value = data.older_cursor
      opening.value = false
      await scrollLatest()
    } catch (error) {
      if (version === generation) threadError.value = errorText(error)
    } finally {
      if (version === generation) opening.value = false
    }
  }

  async function moreMessages() {
    if (!room.value || !older.value || historyLoading.value) return
    const id = room.value.id
    const version = generation
    historyLoading.value = true
    historyError.value = ''
    try {
      const result = await api<{ data: ChatMessage[]; older_cursor: number | null }>(
        `/rooms/${id}/messages?before_id=${older.value}`,
      )
      if (version !== generation) return
      const element = thread.value
      const previousHeight = element?.scrollHeight ?? 0
      const previousTop = element?.scrollTop ?? 0
      messages.value = mergeMessages(messages.value, result.data)
      older.value = result.older_cursor
      await nextTick()
      if (version === generation && element) {
        element.scrollTop = previousTop + element.scrollHeight - previousHeight
        trackPosition()
      }
    } catch (error) {
      if (version === generation) historyError.value = errorText(error)
    } finally {
      if (version === generation) historyLoading.value = false
    }
  }

  async function poll() {
    if (polling || disposed || document.hidden || opening.value) return
    polling = true
    const snapshot = room.value
    const id = snapshot?.id
    const version = generation
    try {
      if (id) {
        const last = messages.value.at(-1)?.id ?? 0
        const [info, data] = await Promise.all([
          api<{ data: ChatRoom }>(`/rooms/${id}`),
          api<{ data: ChatMessage[] }>(`/rooms/${id}/messages?after_id=${last}`),
        ])
        if (version !== generation || disposed) return
        if (room.value === snapshot) room.value = info.data
        threadError.value = ''
        const follow = atLatest.value
        if (data.data.length) messages.value = mergeMessages(messages.value, data.data)
        if (follow && data.data.length) await scrollLatest()
        else if (follow) void markRead()
      }
      await list('refresh')
    } catch (error) {
      if (version === generation) threadError.value = errorText(error)
    } finally {
      polling = false
    }
  }

  async function send() {
    const active = room.value
    const text = draft.value.trim()
    if (!active?.can_send || !text || pendingSends.has(active.id)) return
    const id = active.id
    pendingSends.add(id)
    delete sendErrors[id]
    try {
      const result = await api<{ data: ChatMessage }>(`/rooms/${id}/messages`, {
        method: 'POST',
        body: JSON.stringify({ body: text }),
      })
      if (disposed) return
      delete drafts[id]
      if (room.value?.id === id) {
        messages.value = mergeMessages(messages.value, [result.data])
        await scrollLatest()
      }
      void list('refresh')
    } catch (error) {
      if (!disposed) sendErrors[id] = errorText(error)
    } finally {
      pendingSends.delete(id)
    }
  }

  async function loadBlocked() {
    blockedLoading.value = true
    dialogError.value = ''
    try {
      const result = await api<{ data: ChatNeighbor[] }>('/neighbors/blocked')
      if (!disposed) blocked.value = result.data.map((item) => ({ ...item, blocked_by_me: true }))
    } catch (error) {
      if (!disposed) dialogError.value = errorText(error)
    } finally {
      blockedLoading.value = false
    }
  }

  function openDialog(value: ChatDialog) {
    dialogError.value = ''
    search.value = ''
    dialog.value = value
    if (value === 'blocked') void loadBlocked()
  }

  async function openDirect(id: number, fromLink = false) {
    if (dialogBusy.value) return
    dialogBusy.value = true
    dialogError.value = ''
    try {
      const result = await api<{ data: ChatRoom }>(`/rooms/direct/${id}`, { method: 'POST' })
      if (disposed) return
      dialog.value = null
      const destination = { name: 'messages', params: { roomId: result.data.id } }
      if (fromLink) await router.replace(destination)
      else await router.push(destination)
      void list()
    } catch (error) {
      if (disposed) return
      if (fromLink) {
        openDialog('create')
        await router.replace({ name: 'messages' })
      }
      dialogError.value = errorText(error)
    } finally {
      dialogBusy.value = false
    }
  }

  async function createGroup(name: string, members: number[]) {
    if (dialogBusy.value) return
    dialogBusy.value = true
    dialogError.value = ''
    try {
      const result = await api<{ data: ChatRoom }>('/rooms', {
        method: 'POST',
        body: JSON.stringify({ name, members }),
      })
      if (disposed) return
      dialog.value = null
      await router.push({ name: 'messages', params: { roomId: result.data.id } })
      void list()
    } catch (error) {
      if (!disposed) dialogError.value = errorText(error)
    } finally {
      dialogBusy.value = false
    }
  }

  async function invite(id: number) {
    if (!room.value || dialogBusy.value) return
    const activeId = room.value.id
    dialogBusy.value = true
    dialogError.value = ''
    try {
      const result = await api<{ data: ChatRoom }>(`/rooms/${activeId}/members`, {
        method: 'POST',
        body: JSON.stringify({ user_id: id }),
      })
      if (room.value?.id === activeId && !disposed) room.value = result.data
      void list('refresh')
    } catch (error) {
      if (!disposed) dialogError.value = errorText(error)
    } finally {
      dialogBusy.value = false
    }
  }

  async function block(neighbor: ChatNeighbor) {
    if (dialogBusy.value) return
    if (!neighbor.blocked_by_me && !confirm(`¿Bloquear a ${neighbor.name}? No podrán enviarse mensajes ni invitarse a grupos.`)) return
    dialogBusy.value = true
    dialogError.value = ''
    try {
      await api(`/neighbors/${neighbor.id}/block`, {
        method: 'PUT',
        body: JSON.stringify({ blocked: !neighbor.blocked_by_me }),
      })
      if (disposed) return
      if (dialog.value === 'blocked') await loadBlocked()
      const id = room.value?.id
      if (id) {
        const result = await api<{ data: ChatRoom }>(`/rooms/${id}`)
        if (room.value?.id === id && !disposed) room.value = result.data
      }
      void list('refresh')
    } catch (error) {
      if (!disposed) dialogError.value = errorText(error)
    } finally {
      dialogBusy.value = false
    }
  }

  async function leave() {
    if (!room.value || dialogBusy.value || !confirm('¿Salir de este grupo? Dejarás de recibir sus mensajes.')) return
    const id = room.value.id
    dialogBusy.value = true
    dialogError.value = ''
    try {
      await api(`/rooms/${id}/membership`, { method: 'DELETE' })
      if (disposed) return
      rooms.value = rooms.value.filter((item) => item.id !== id)
      delete drafts[id]
      dialog.value = null
      await router.push({ name: 'messages' })
      void list()
    } catch (error) {
      if (!disposed) dialogError.value = errorText(error)
    } finally {
      dialogBusy.value = false
    }
  }

  watch(roomId, openRoom, { immediate: true })
  function retryDirectory() {
    directoryAttempt.value++
  }

  watch([dialog, search, canInvite, directoryAttempt], ([active, query, inviting], _, onCleanup) => {
    if (active !== 'create' && (active !== 'members' || !inviting)) {
      directoryLoading.value = false
      return
    }
    const controller = new AbortController()
    directoryLoading.value = true
    directoryError.value = ''
    const timeout = setTimeout(async () => {
      try {
        const result = await api<{ data: ChatNeighbor[] }>(
          `/neighbors?q=${encodeURIComponent(query.trim())}`,
          { signal: controller.signal },
        )
        if (!controller.signal.aborted) neighbors.value = result.data
      } catch (error) {
        if (!controller.signal.aborted) directoryError.value = errorText(error)
      } finally {
        if (!controller.signal.aborted) directoryLoading.value = false
      }
    }, query ? 250 : 0)
    onCleanup(() => { clearTimeout(timeout); controller.abort() })
  })
  watch(() => route.query.to, (value) => {
    if (typeof value === 'string' && /^\d+$/.test(value)) void openDirect(Number(value), true)
  }, { immediate: true })

  onMounted(() => {
    void list()
    timer = setInterval(() => { void poll() }, 3000)
  })

  onBeforeUnmount(() => {
    disposed = true
    generation++
    clearInterval(timer)
  })

  return {
    roomId, rooms, room, messages, thread, draft, sending, sendError, atLatest,
    listLoading, listReady, listError, nextPage, opening, threadError, historyLoading,
    historyError, older, dialog, dialogBusy, dialogError, search, neighbors,
    directoryLoading, directoryError, blocked, blockedLoading, canInvite,
    list, openRoom, poll, trackPosition, scrollLatest, moreMessages, send, openDialog,
    loadBlocked, retryDirectory, openDirect, createGroup, invite, block, leave,
  }
}
