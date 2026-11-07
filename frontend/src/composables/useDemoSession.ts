let active = false

export function hasDemoSession() {
  return active
}

export function startDemoSession() {
  active = true
}

export function endDemoSession() {
  active = false
}
