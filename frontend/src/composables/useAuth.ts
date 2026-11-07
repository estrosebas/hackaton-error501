import { shallowRef } from 'vue'
import { api, ApiError, clearSession, saveSession } from '../services/api'

export interface AuthUser { id: number; name: string; neighborhood: string | null; email: string }
export const currentUser = shallowRef<AuthUser | null>(null)
export const sessionError = shallowRef('')
let pending: { token: string; request: Promise<boolean> } | null = null
let expiration: ReturnType<typeof setTimeout> | undefined
function ended() {
  currentUser.value = null
  clearTimeout(expiration)
}
window.addEventListener('masi:session-ended', ended)
if (import.meta.hot) import.meta.hot.dispose(() => { clearTimeout(expiration); window.removeEventListener('masi:session-ended', ended) })

export async function validateSession(): Promise<boolean> {
  const token = sessionStorage.getItem('masi.api.token')
  if (!token) { currentUser.value = null; return false }
  if (pending?.token === token) return pending.request
  const request = (async () => {
    try {
      const result = await api<{ user: AuthUser; expires_at: string | null }>('/profile', { cache: 'no-store', signal: AbortSignal.timeout(8000) })
      if (sessionStorage.getItem('masi.api.token') !== token) return false
      currentUser.value = result.user
      sessionStorage.setItem('masi.api.user', JSON.stringify(result.user))
      sessionError.value = ''
      clearTimeout(expiration)
      if (result.expires_at) {
        const remaining = Date.parse(result.expires_at) - Date.now()
        if (remaining <= 0) { clearSession(); return false }
        expiration = setTimeout(() => {
          // Long-lived tokens can exceed the browser's maximum timeout.
          if (sessionStorage.getItem('masi.api.token') === token) void validateSession()
        }, Math.min(remaining + 50, 2_147_000_000))
      }
      return true
    } catch (error) {
      if (sessionStorage.getItem('masi.api.token') === token || (!sessionStorage.getItem('masi.api.token') && error instanceof ApiError && error.status === 401)) {
        currentUser.value = null
        sessionError.value = error instanceof ApiError && error.status === 401
          ? 'Tu sesión venció. Inicia sesión nuevamente.'
          : 'No pudimos verificar tu sesión. Revisa tu conexión e intenta nuevamente.'
      }
      return false
    }
  })()
  pending = { token, request }
  try { return await request } finally { if (pending?.request === request) pending = null }
}

export async function login(credentials: { dni: string; password: string }) {
  const result = await api<{ token: string; user: AuthUser }>('/auth/login', { method: 'POST', body: JSON.stringify(credentials) })
  saveSession(result.token, result.user)
  currentUser.value = result.user
  sessionError.value = ''
}

export async function logout() {
  try {
    if (sessionStorage.getItem('masi.api.token')) await api('/auth/logout', { method: 'POST' })
  } catch (error) {
    // An expired/revoked token is already logged out. Other failures must remain
    // visible so the user can retry server revocation instead of a false success.
    if (!(error instanceof ApiError) || error.status !== 401) throw error
  }
  sessionError.value = ''
  clearSession()
}
