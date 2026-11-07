import { computed, ref } from 'vue'
import type { BugCase, CaseStatus, NewCasePayload } from '../types'

const seed: BugCase[] = [
  {
    id: 'case-1',
    title: 'El login se cae con correos corporativos',
    area: 'Autenticación',
    severity: 'crítica',
    status: 'en progreso',
    owner: 'Neffmau243',
    updatedAt: 'hace 12 min',
  },
  {
    id: 'case-2',
    title: 'El dashboard no muestra datos en móvil',
    area: 'Frontend',
    severity: 'alta',
    status: 'abierto',
    owner: 'Cristhian036',
    updatedAt: 'hace 38 min',
  },
  {
    id: 'case-3',
    title: 'Respuesta 501 al guardar un reporte',
    area: 'Backend',
    severity: 'crítica',
    status: 'abierto',
    owner: 'estrosebas',
    updatedAt: 'hace 1 h',
  },
  {
    id: 'case-4',
    title: 'Los filtros de la tabla pierden el estado',
    area: 'Frontend',
    severity: 'media',
    status: 'resuelto',
    owner: 'Neffmau243',
    updatedAt: 'hace 3 h',
  },
  {
    id: 'case-5',
    title: 'Ajustar contraste del modo oscuro',
    area: 'Diseño',
    severity: 'baja',
    status: 'en progreso',
    owner: 'Cristhian036',
    updatedAt: 'hace 5 h',
  },
  {
    id: 'case-6',
    title: 'Agregar validación al formulario de casos',
    area: 'Frontend',
    severity: 'media',
    status: 'resuelto',
    owner: 'estrosebas',
    updatedAt: 'ayer',
  },
]

export function useCases() {
  const cases = ref<BugCase[]>(seed.map((item) => ({ ...item })))

  const stats = computed(() => ({
    total: cases.value.length,
    open: cases.value.filter((item) => item.status === 'abierto').length,
    inProgress: cases.value.filter((item) => item.status === 'en progreso').length,
    resolved: cases.value.filter((item) => item.status === 'resuelto').length,
  }))

  const resolutionRate = computed(() => {
    if (stats.value.total === 0) return 0
    return Math.round((stats.value.resolved / stats.value.total) * 100)
  })

  function addCase(payload: NewCasePayload) {
    cases.value.unshift({
      ...payload,
      id: crypto.randomUUID(),
      status: 'abierto',
      updatedAt: 'ahora',
    })
  }

  function removeCase(id: string) {
    cases.value = cases.value.filter((item) => item.id !== id)
  }

  function setStatus(id: string, status: CaseStatus) {
    const target = cases.value.find((item) => item.id === id)
    if (target) target.status = status
  }

  return { cases, stats, resolutionRate, addCase, removeCase, setStatus }
}
