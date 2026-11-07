<script setup lang="ts">
import { reactive, ref } from 'vue'
import {
  NButton,
  NForm,
  NFormItem,
  NInput,
  NModal,
  NSelect,
  NSpace,
  type FormInst,
  type FormRules,
} from 'naive-ui'
import type { NewCasePayload, Severity } from '../../types'

const show = defineModel<boolean>('show', { required: true })

const emit = defineEmits<{ create: [payload: NewCasePayload] }>()

const formRef = ref<FormInst | null>(null)

const createDefaults = (): NewCasePayload => ({
  title: '',
  area: 'Frontend',
  severity: 'media',
  owner: '',
})

const model = reactive<NewCasePayload>(createDefaults())

const rules: FormRules = {
  title: {
    required: true,
    message: 'Describe el caso en una línea',
    trigger: ['input', 'blur'],
  },
  owner: {
    required: true,
    message: 'Asigna un responsable',
    trigger: ['input', 'blur'],
  },
}

const areaOptions = [
  { label: 'Frontend', value: 'Frontend' },
  { label: 'Backend', value: 'Backend' },
  { label: 'Diseño', value: 'Diseño' },
  { label: 'Autenticación', value: 'Autenticación' },
  { label: 'Datos', value: 'Datos' },
]

const severityOptions: { label: string; value: Severity }[] = [
  { label: 'Crítica', value: 'crítica' },
  { label: 'Alta', value: 'alta' },
  { label: 'Media', value: 'media' },
  { label: 'Baja', value: 'baja' },
]

async function handleSubmit() {
  try {
    await formRef.value?.validate()
  } catch {
    return
  }

  emit('create', { ...model })
  Object.assign(model, createDefaults())
  show.value = false
}

function handleCancel() {
  show.value = false
}
</script>

<template>
  <NModal
    v-model:show="show"
    preset="card"
    title="Nuevo caso"
    :style="{ width: '540px', maxWidth: 'calc(100vw - 32px)' }"
    :bordered="false"
  >
    <NForm ref="formRef" :model="model" :rules="rules" label-placement="top" @submit.prevent="handleSubmit">
      <NFormItem label="Título del caso" path="title">
        <NInput v-model:value="model.title" placeholder="Ej. El login falla con correos corporativos" />
      </NFormItem>

      <div class="form-row">
        <NFormItem label="Área" path="area">
          <NSelect v-model:value="model.area" :options="areaOptions" />
        </NFormItem>
        <NFormItem label="Severidad" path="severity">
          <NSelect v-model:value="model.severity" :options="severityOptions" />
        </NFormItem>
      </div>

      <NFormItem label="Responsable" path="owner">
        <NInput v-model:value="model.owner" placeholder="Usuario de GitHub" />
      </NFormItem>
    </NForm>

    <template #footer>
      <NSpace justify="end">
        <NButton quaternary @click="handleCancel">Cancelar</NButton>
        <NButton type="primary" @click="handleSubmit">Crear caso</NButton>
      </NSpace>
    </template>
  </NModal>
</template>

<style scoped>
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
</style>
