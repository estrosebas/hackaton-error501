<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { NButton, NCard, NInput, NSelect, useMessage } from 'naive-ui'
import CasesTable from '../components/dashboard/CasesTable.vue'
import NewCaseModal from '../components/dashboard/NewCaseModal.vue'
import StatsGrid from '../components/dashboard/StatsGrid.vue'
import { useCases } from '../composables/useCases'
import type { NewCasePayload, Severity, StatItem } from '../types'

const message = useMessage()
const { cases, stats, resolutionRate, addCase, removeCase } = useCases()

const search = shallowRef('')
const severityFilter = shallowRef<'todas' | Severity>('todas')
const showModal = shallowRef(false)

const severityOptions: { label: string; value: 'todas' | Severity }[] = [
  { label: 'Todas las severidades', value: 'todas' },
  { label: 'Crítica', value: 'crítica' },
  { label: 'Alta', value: 'alta' },
  { label: 'Media', value: 'media' },
  { label: 'Baja', value: 'baja' },
]

const filteredCases = computed(() => {
  const term = search.value.trim().toLowerCase()

  return cases.value.filter((item) => {
    const matchesSeverity = severityFilter.value === 'todas' || item.severity === severityFilter.value
    const matchesTerm =
      term.length === 0 ||
      item.title.toLowerCase().includes(term) ||
      item.area.toLowerCase().includes(term) ||
      item.owner.toLowerCase().includes(term)

    return matchesSeverity && matchesTerm
  })
})

const statItems = computed<StatItem[]>(() => [
  {
    id: 'total',
    label: 'Casos',
    value: stats.value.total,
    hint: 'registrados en el sprint',
    accent: '#6366f1',
  },
  {
    id: 'open',
    label: 'Abiertos',
    value: stats.value.open,
    hint: 'esperando triage',
    accent: '#f59e0b',
  },
  {
    id: 'progress',
    label: 'En progreso',
    value: stats.value.inProgress,
    hint: 'en manos del equipo',
    accent: '#38bdf8',
  },
  {
    id: 'resolved',
    label: 'Resueltos',
    value: stats.value.resolved,
    hint: `${resolutionRate.value}% de avance`,
    accent: '#22c55e',
  },
])

function handleCreate(payload: NewCasePayload) {
  addCase(payload)
  message.success(`Caso creado: ${payload.title}`)
}

function handleRemove(id: string) {
  removeCase(id)
  message.info('Caso eliminado')
}

function handleRefresh() {
  message.success('Datos sincronizados')
}
</script>

<template>
  <div class="dashboard">
    <section class="hero">
      <div class="hero-copy">
        <p class="hero-eyebrow">Resumen del hackathon</p>
        <h2 class="hero-title">Todo el estado del proyecto, en un solo lugar</h2>
        <p class="hero-sub">
          Base lista con Vue 3, Composition API, TypeScript y Naive UI para que el equipo empiece a construir.
        </p>
      </div>
      <div class="hero-actions">
        <NButton type="primary" size="large" @click="showModal = true">+ Nuevo caso</NButton>
        <NButton secondary size="large" @click="handleRefresh">Actualizar</NButton>
      </div>
    </section>

    <StatsGrid :items="statItems" />

    <NCard class="panel" size="small">
      <template #header>
        <div class="panel-head">
          <div>
            <p class="panel-title">Casos del equipo</p>
            <p class="panel-sub">{{ filteredCases.length }} de {{ cases.length }} casos</p>
          </div>
          <div class="panel-tools">
            <NInput v-model:value="search" clearable placeholder="Buscar caso, área o responsable" class="search" />
            <NSelect v-model:value="severityFilter" :options="severityOptions" class="filter" />
          </div>
        </div>
      </template>

      <CasesTable :cases="filteredCases" @remove="handleRemove" />
    </NCard>

    <NewCaseModal v-model:show="showModal" @create="handleCreate" />
  </div>
</template>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1240px;
  margin: 0 auto;
}

.hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 28px;
  flex-wrap: wrap;
  padding: 26px 28px;
  border-radius: 18px;
  border: 1px solid rgba(99, 102, 241, 0.22);
  background:
    radial-gradient(120% 160% at 0% 0%, rgba(99, 102, 241, 0.22), transparent 55%),
    radial-gradient(90% 140% at 100% 0%, rgba(34, 211, 238, 0.14), transparent 60%);
}

.hero-copy {
  max-width: 620px;
}

.hero-eyebrow {
  margin: 0;
  font-size: 10.5px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  opacity: 0.55;
}

.hero-title {
  margin: 6px 0 8px;
  font-size: clamp(1.35rem, 2.4vw, 1.9rem);
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.hero-sub {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.6;
  opacity: 0.65;
}

.hero-actions {
  display: flex;
  gap: 10px;
}

.panel-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.panel-title {
  margin: 0;
  font-size: 15px;
  font-weight: 650;
}

.panel-sub {
  margin: 2px 0 0;
  font-size: 11.5px;
  opacity: 0.5;
}

.panel-tools {
  display: flex;
  gap: 10px;
}

.search {
  width: 260px;
}

.filter {
  width: 190px;
}

@media (max-width: 640px) {
  .panel-tools {
    width: 100%;
    flex-direction: column;
  }

  .search,
  .filter {
    width: 100%;
  }
}
</style>
