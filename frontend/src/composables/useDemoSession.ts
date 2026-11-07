let active = false

export function hasDemoSession() {
  return active || !!sessionStorage.getItem('masi.api.token')
}

export function startDemoSession() {
  active = true
}

export function endDemoSession() {
  active = false
  sessionStorage.removeItem('masi.api.token')
  sessionStorage.removeItem('masi.api.user')
}
