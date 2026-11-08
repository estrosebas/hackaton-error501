import {
  computed, inject, onMounted, onScopeDispose, provide, readonly, shallowRef,
  type InjectionKey,
} from 'vue'
import { api } from '../services/api'

export interface NotificationPerson {
  id: number
  name: string
  avatar: string | null
}

export interface CommunityNotification {
  id: string
  kind: 'activity' | 'need_help' | 'alert'
  summary: string
  published_at: string
  read: boolean
  author: NotificationPerson
}

interface Snapshot {
  data: CommunityNotification[]
  active: NotificationPerson[]
  sharing_presence: boolean
  my_avatar: string | null
  has_district: boolean
}

const key: InjectionKey<ReturnType<typeof createNotifications>> = Symbol('community-notifications')

function createNotifications(onNewAlerts: (count: number) => void) {
  const snapshot = shallowRef<Snapshot>()
  const failed = shallowRef(false)
  const unread = computed(() => snapshot.value?.data.filter((item) => !item.read).length ?? 0)
  const scope = new AbortController()
  let syncing: AbortController | undefined
  let timer: ReturnType<typeof setInterval> | undefined

  async function refresh(replace = false) {
    if (scope.signal.aborted || document.hidden || (syncing && !replace)) return
    syncing?.abort()
    const request = new AbortController()
    syncing = request
    try {
      const result = await api<Snapshot>('/notifications/sync', {
        method: 'POST', cache: 'no-store', signal: request.signal,
      })
      if (!request.signal.aborted) {
        const previous = snapshot.value
        snapshot.value = result
        failed.value = false
        if (previous && !document.hidden) {
          const known = new Set(previous.data.map((item) => item.id))
          const newAlerts = result.data.filter((item) => item.kind === 'alert' && !item.read && !known.has(item.id))
          if (newAlerts.length) onNewAlerts(newAlerts.length)
        }
      }
    } catch {
      if (!request.signal.aborted) failed.value = true
    } finally {
      if (syncing === request) syncing = undefined
    }
  }

  async function read(id: string) {
    await api(`/notifications/${id}/read`, { method: 'PUT', signal: scope.signal })
    if (snapshot.value && !scope.signal.aborted) {
      snapshot.value = {
        ...snapshot.value,
        data: snapshot.value.data.map((item) => item.id === id ? { ...item, read: true } : item),
      }
      void refresh(true)
    }
  }

  async function setPresence(sharing: boolean) {
    await api('/notifications/presence', {
      method: 'PUT', body: JSON.stringify({ sharing }), signal: scope.signal,
    })
    if (snapshot.value && !scope.signal.aborted) {
      snapshot.value = { ...snapshot.value, sharing_presence: sharing }
    }
    void refresh(true)
  }

  async function setAvatar(photo?: File) {
    const body = photo ? new FormData() : undefined
    if (photo) body?.set('photo', photo)
    await api('/profile/community-avatar', {
      method: photo ? 'POST' : 'DELETE', body, signal: scope.signal,
    })
    if (!photo && snapshot.value && !scope.signal.aborted) {
      snapshot.value = { ...snapshot.value, my_avatar: null }
    }
    await refresh(true)
  }

  function onVisibility() {
    if (!document.hidden) void refresh()
  }

  onMounted(() => {
    void refresh()
    timer = setInterval(() => { void refresh() }, 20_000)
    document.addEventListener('visibilitychange', onVisibility)
  })
  onScopeDispose(() => {
    scope.abort()
    syncing?.abort()
    clearInterval(timer)
    document.removeEventListener('visibilitychange', onVisibility)
  })

  return { snapshot: readonly(snapshot), failed: readonly(failed), unread, refresh, read, setPresence, setAvatar }
}

export function provideCommunityNotifications(onNewAlerts: (count: number) => void) {
  const notifications = createNotifications(onNewAlerts)
  provide(key, notifications)
  return notifications
}

export function useCommunityNotifications() {
  const notifications = inject(key)
  if (!notifications) throw new Error('Community notifications provider is missing')
  return notifications
}
