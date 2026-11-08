import { inject, onScopeDispose, provide, readonly, shallowRef, watch, type InjectionKey } from 'vue'
import { clearSession } from '../services/api'
import { currentUser } from './useAuth'

const avatarKey: InjectionKey<ReturnType<typeof createProfileAvatar>> = Symbol('profile-avatar')
const apiBase = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '')

function createProfileAvatar() {
  const url = shallowRef<string>()
  let request: AbortController | undefined

  function replaceUrl(next?: string) {
    if (url.value) URL.revokeObjectURL(url.value)
    url.value = next
  }

  function reset() {
    request?.abort()
    replaceUrl()
  }

  async function refresh() {
    const token = sessionStorage.getItem('masi.api.token')
    if (!token || !currentUser.value) return
    request?.abort()
    const controller = new AbortController()
    request = controller
    try {
      const response = await fetch(`${apiBase}/profile/avatar`, {
        headers: { Authorization: `Bearer ${token}`, Accept: 'image/*' },
        cache: 'no-store',
        signal: AbortSignal.any([controller.signal, AbortSignal.timeout(8000)]),
      })
      if (controller.signal.aborted || sessionStorage.getItem('masi.api.token') !== token) return
      if (response.status === 401) {
        clearSession()
      } else if (response.status === 404) {
        replaceUrl()
      } else if (response.ok) {
        const image = await response.blob()
        if (!controller.signal.aborted && sessionStorage.getItem('masi.api.token') === token) {
          replaceUrl(URL.createObjectURL(image))
        }
      }
    } catch {
      // Keep the last photo on transient connection errors.
    }
  }

  watch(() => currentUser.value?.id, () => {
    reset()
    void refresh()
  }, { immediate: true, flush: 'sync' })
  onScopeDispose(reset)

  return { url: readonly(url), refresh }
}

export function provideProfileAvatar() {
  provide(avatarKey, createProfileAvatar())
}

export function useProfileAvatar() {
  const avatar = inject(avatarKey)
  if (!avatar) throw new Error('Profile avatar provider is missing')
  return avatar
}
