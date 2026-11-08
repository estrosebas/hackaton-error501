import {
  computed, inject, onMounted, onScopeDispose, provide, readonly, shallowRef, watch,
  type InjectionKey,
} from 'vue'
import { api } from '../services/api'
import { currentUser } from './useAuth'

export interface NotificationPerson {
  id: number
  name: string
  avatar: string | null
}

export interface CommunityNotification {
  id: string
  kind: 'activity' | 'need_help' | 'alert' | 'emergency'
  summary: string
  published_at: string
  read: boolean
  author: NotificationPerson | null
}

interface Snapshot {
  data: CommunityNotification[]
  active: NotificationPerson[]
  sharing_presence: boolean
  my_avatar: string | null
  has_district: boolean
}

const key: InjectionKey<ReturnType<typeof createNotifications>> = Symbol('community-notifications')

interface NotificationCallbacks {
  onNewAlerts: (count: number) => void
  onNewEmergencies: (count: number) => void
}

function createNotifications({ onNewAlerts, onNewEmergencies }: NotificationCallbacks) {
  const snapshot = shallowRef<Snapshot>()
  const emergencies = shallowRef<CommunityNotification[]>()
  const failed = shallowRef(false)
  const emergenciesFailed = shallowRef(false)
  const emergenciesReady = computed(() => emergencies.value !== undefined)
  const notices = computed(() => [...(emergencies.value ?? []), ...(snapshot.value?.data ?? [])])
  const unread = computed(() => notices.value.filter((item) => !item.read).length)
  const unreadEmergencies = computed(() => (emergencies.value ?? []).filter((item) => !item.read))
  const scope = new AbortController()
  let syncing: AbortController | undefined
  let syncingEmergencies: AbortController | undefined
  let timer: ReturnType<typeof setInterval> | undefined
  let emergencyTimer: ReturnType<typeof setInterval> | undefined

  async function refresh(replace = false) {
    if (scope.signal.aborted || !currentUser.value?.id || document.hidden || (syncing && !replace)) return
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

  async function refreshEmergencies(replace = false) {
    if (scope.signal.aborted || !currentUser.value?.id || document.hidden || (syncingEmergencies && !replace)) return
    syncingEmergencies?.abort()
    const request = new AbortController()
    syncingEmergencies = request
    try {
      const { data } = await api<{ data: CommunityNotification[] }>('/notifications/emergencies', {
        cache: 'no-store', signal: AbortSignal.any([request.signal, AbortSignal.timeout(4500)]),
      })
      if (request.signal.aborted || document.hidden) return
      const known = new Set(emergencies.value?.map((item) => item.id))
      const newCount = data.filter((item) => !item.read && !known.has(item.id)).length
      emergencies.value = data
      emergenciesFailed.value = false
      if (newCount) onNewEmergencies(newCount)
    } catch {
      if (!request.signal.aborted) emergenciesFailed.value = true
    } finally {
      if (syncingEmergencies === request) syncingEmergencies = undefined
    }
  }

  async function read(id: string) {
    await api(`/notifications/${id}/read`, { method: 'PUT', signal: scope.signal })
    if (scope.signal.aborted) return
    const mark = (item: CommunityNotification) => item.id === id ? { ...item, read: true } : item
    if (emergencies.value?.some((item) => item.id === id)) {
      emergencies.value = emergencies.value.map(mark)
      void refreshEmergencies(true)
      return
    }
    if (snapshot.value) {
      snapshot.value = {
        ...snapshot.value,
        data: snapshot.value.data.map(mark),
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
    if (document.hidden) {
      syncing?.abort()
      syncingEmergencies?.abort()
      syncing = undefined
      syncingEmergencies = undefined
      return
    }
    void refresh()
    void refreshEmergencies()
  }
  watch([() => currentUser.value?.id, () => currentUser.value?.ubigeo], () => {
    syncing?.abort()
    syncingEmergencies?.abort()
    snapshot.value = undefined
    emergencies.value = undefined
    failed.value = false
    emergenciesFailed.value = false
    void refresh(true); void refreshEmergencies(true)
  })

  onMounted(() => {
    onVisibility()
    timer = setInterval(() => { void refresh() }, 20_000)
    emergencyTimer = setInterval(() => { void refreshEmergencies() }, 5000)
    document.addEventListener('visibilitychange', onVisibility)
    window.addEventListener('online', onVisibility)
  })
  onScopeDispose(() => {
    scope.abort()
    syncing?.abort()
    syncingEmergencies?.abort()
    clearInterval(timer)
    clearInterval(emergencyTimer)
    document.removeEventListener('visibilitychange', onVisibility)
    window.removeEventListener('online', onVisibility)
  })

  return {
    snapshot: readonly(snapshot), failed: readonly(failed), emergenciesFailed: readonly(emergenciesFailed), emergenciesReady,
    notices, unread, unreadEmergencies, refresh, refreshEmergencies, read, setPresence, setAvatar,
  }
}

export function provideCommunityNotifications(callbacks: NotificationCallbacks) {
  const notifications = createNotifications(callbacks)
  provide(key, notifications)
  return notifications
}

export function useCommunityNotifications() {
  const notifications = inject(key)
  if (!notifications) throw new Error('Community notifications provider is missing')
  return notifications
}
