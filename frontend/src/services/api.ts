const base = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '')
export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  constructor(message: string, status: number, errors: Record<string, string[]> = {}) { super(message); this.status = status; this.errors = errors }
}
async function request(path: string, options: RequestInit, accept: string): Promise<Response> {
  const headers = new Headers(options.headers)
  headers.set('Accept', accept)
  if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
  const token = sessionStorage.getItem('masi.api.token')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const timeout = AbortSignal.timeout(25000)
  let response: Response
  try {
    response = await fetch(`${base}${path}`, { ...options, headers, signal: options.signal ? AbortSignal.any([options.signal, timeout]) : timeout })
  } catch (error) {
    if (options.signal?.aborted) throw error
    throw new ApiError('No pudimos conectar con el servidor. Revisa tu conexión y vuelve a intentar.', 0)
  }
  if (!response.ok) {
    const data = await response.json().catch(() => ({}))
    if (response.status === 401 && token && sessionStorage.getItem('masi.api.token') === token) clearSession()
    const errors = data.errors || {}
    throw new ApiError(Object.values(errors).flat().join(' ') || data.message || 'No pudimos completar la solicitud. Intenta nuevamente.', response.status, errors)
  }
  return response
}
export async function api<T = any>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await request(path, options, 'application/json')
  return response.json().catch(() => ({})) as Promise<T>
}
export async function apiImage(path: string, signal: AbortSignal): Promise<Blob> {
  const response = await request(path, { signal, cache: 'no-store' }, 'image/*')
  return response.blob()
}
export function saveSession(token: string, user: unknown) {
  sessionStorage.setItem('masi.api.token', token)
  sessionStorage.setItem('masi.api.user', JSON.stringify(user))
}
export function clearSession() {
  sessionStorage.removeItem('masi.api.token')
  sessionStorage.removeItem('masi.api.user')
  window.dispatchEvent(new Event('masi:session-ended'))
}
