const clock = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
const shortDate = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'short' })
const longDate = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'long' })
const fullDate = new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'long', year: 'numeric' })

export function dayKey(date: Date) {
  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`
}

export function chatTime(value: string) {
  return clock.format(new Date(value))
}

export function chatDay(value: string, compact = false) {
  const date = new Date(value)
  const today = new Date()
  if (dayKey(date) === dayKey(today)) return compact ? clock.format(date) : 'Hoy'
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  if (dayKey(date) === dayKey(yesterday)) return 'Ayer'
  if (compact) return shortDate.format(date)
  return (date.getFullYear() === today.getFullYear() ? longDate : fullDate).format(date)
}
