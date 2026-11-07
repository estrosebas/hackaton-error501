export type Severity = 'crítica' | 'alta' | 'media' | 'baja'

export type CaseStatus = 'abierto' | 'en progreso' | 'resuelto'

export interface BugCase {
  id: string
  title: string
  area: string
  severity: Severity
  status: CaseStatus
  owner: string
  updatedAt: string
}

export type NewCasePayload = Pick<BugCase, 'title' | 'area' | 'severity' | 'owner'>

export interface StatItem {
  id: string
  label: string
  value: number
  hint: string
  accent: string
}
