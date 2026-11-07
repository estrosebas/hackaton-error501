<script setup lang="ts">
import { h } from 'vue'
import { NButton, NDataTable, NTag, type DataTableColumns } from 'naive-ui'
import type { BugCase, CaseStatus, Severity } from '../../types'

defineProps<{ cases: BugCase[] }>()

const emit = defineEmits<{ remove: [id: string] }>()

type TagType = 'default' | 'error' | 'warning' | 'info' | 'success'

const severityType: Record<Severity, TagType> = {
  crítica: 'error',
  alta: 'warning',
  media: 'info',
  baja: 'default',
}

const statusType: Record<CaseStatus, TagType> = {
  abierto: 'warning',
  'en progreso': 'info',
  resuelto: 'success',
}

const columns: DataTableColumns<BugCase> = [
  {
    title: 'Caso',
    key: 'title',
    minWidth: 280,
    render: (row) =>
      h('div', { class: 'case-cell' }, [
        h('span', { class: 'case-title' }, row.title),
        h('span', { class: 'case-meta' }, row.area),
      ]),
  },
  {
    title: 'Severidad',
    key: 'severity',
    width: 120,
    render: (row) =>
      h(
        NTag,
        { size: 'small', round: true, bordered: false, type: severityType[row.severity] },
        { default: () => row.severity },
      ),
  },
  {
    title: 'Estado',
    key: 'status',
    width: 130,
    render: (row) =>
      h(
        NTag,
        { size: 'small', round: true, bordered: false, type: statusType[row.status] },
        { default: () => row.status },
      ),
  },
  { title: 'Responsable', key: 'owner', width: 160 },
  { title: 'Actualizado', key: 'updatedAt', width: 130 },
  {
    title: '',
    key: 'actions',
    width: 96,
    align: 'right',
    render: (row) =>
      h(
        NButton,
        {
          size: 'tiny',
          quaternary: true,
          type: 'error',
          onClick: () => emit('remove', row.id),
        },
        { default: () => 'Eliminar' },
      ),
  },
]
</script>

<template>
  <NDataTable
    class="cases-table"
    :columns="columns"
    :data="cases"
    :row-key="(row: BugCase) => row.id"
    :bordered="false"
    :bottom-bordered="false"
    size="small"
  />
</template>

<style scoped>
.cases-table :deep(.case-cell) {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cases-table :deep(.case-title) {
  font-size: 13px;
  font-weight: 600;
}

.cases-table :deep(.case-meta) {
  font-size: 11px;
  opacity: 0.5;
}
</style>
