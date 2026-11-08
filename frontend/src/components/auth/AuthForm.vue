<script setup lang="ts">
import {
  computed,
  nextTick,
  reactive,
  shallowRef,
  useTemplateRef,
  type InputHTMLAttributes,
} from 'vue'
import { NButton, NInput } from 'naive-ui'
import AppIcon from '../AppIcon.vue'

const props = defineProps<{ mode: 'login' | 'register'; busy: boolean }>()
const emit = defineEmits<{ submit: [credentials: { dni: string; password: string }] }>()

const inputTheme = {
  color: 'transparent',
  colorHover: 'transparent',
  colorFocus: 'transparent',
  fontSizeLarge: '16px',
  heightLarge: '46px',
  paddingLarge: '0 2px',
  placeholderColor: 'var(--muted)',
}

const values = reactive({ name: '', dni: '', password: '', confirmation: '' })
type FieldKey = keyof typeof values
interface Field {
  key: FieldKey
  label: string
  placeholder: string
  autocomplete: InputHTMLAttributes['autocomplete']
  secret?: boolean
  maxLength: number
}

const credentials = computed<Field[]>(() => [
  {
    key: 'dni',
    label: 'DNI',
    placeholder: 'Tus 8 dígitos',
    autocomplete: 'username',
    maxLength: 8,
  },
  {
    key: 'password',
    label: 'Contraseña',
    placeholder: props.mode === 'login' ? 'Tu contraseña' : '8 caracteres o más',
    autocomplete:
      props.mode === 'register' ? 'new-password' : 'current-password',
    secret: true,
    maxLength: 128,
  },
])
const fields = computed<Field[]>(() =>
  props.mode === 'register'
    ? [
        {
          key: 'name',
          label: 'Nombre',
          placeholder: '¿Cómo te llamas?',
          autocomplete: 'name',
          maxLength: 80,
        },
        ...credentials.value,
        {
          key: 'confirmation',
          label: 'Confirma tu contraseña',
          placeholder: 'Repite tu contraseña',
          autocomplete: 'new-password',
          secret: true,
          maxLength: 128,
        },
      ]
    : credentials.value,
)

const touched = reactive<Partial<Record<FieldKey, boolean>>>({})
const visible = reactive<Partial<Record<FieldKey, boolean>>>({})
const submitted = shallowRef(false)
const form = useTemplateRef<HTMLFormElement>('auth-form')
const errors = computed<Record<FieldKey, string>>(() => ({
  name: values.name.trim() ? '' : 'Escribe tu nombre.',
  dni: /^[0-9]{8}$/.test(values.dni.trim())
    ? ''
    : 'Escribe los 8 dígitos de tu DNI.',
  password: props.mode === 'login'
    ? (values.password.length ? '' : 'Escribe tu contraseña.')
    : (values.password.length >= 8 ? '' : 'Escribe al menos 8 caracteres.'),
  confirmation:
    values.confirmation && values.confirmation === values.password
      ? ''
      : 'Escribe la misma contraseña.',
}))

function fieldError(key: FieldKey) {
  return submitted.value || touched[key] ? errors.value[key] : ''
}

async function submit() {
  if (props.busy) return
  submitted.value = true
  if (fields.value.some(({ key }) => errors.value[key])) {
    await nextTick()
    form.value
      ?.querySelector<HTMLInputElement>('input[aria-invalid="true"]')
      ?.focus()
    return
  }
  emit('submit', { dni: values.dni.trim(), password: values.password })
}
</script>

<template>
  <form ref="auth-form" class="auth-form" novalidate @submit.prevent="submit">
    <div class="auth-fields">
      <div v-for="field in fields" :key="field.key" class="auth-field">
        <div class="auth-field-row">
          <div class="auth-field-value">
            <label :for="`auth-${field.key}`">{{ field.label }}</label>
            <NInput
              v-model:value="values[field.key]"
              :type="field.secret && !visible[field.key] ? 'password' : 'text'"
              size="large"
              :bordered="false"
              :theme-overrides="inputTheme"
              :placeholder="field.placeholder"
              :maxlength="field.maxLength"
              :input-props="{
                id: `auth-${field.key}`,
                name: field.key,
                ...(field.key === 'dni' ? { inputmode: 'numeric', pattern: '[0-9]{8}' } : {}),
                autocomplete: field.autocomplete,
                required: true,
                'aria-invalid': Boolean(fieldError(field.key)),
                'aria-describedby': fieldError(field.key)
                  ? `auth-${field.key}-error`
                  : undefined,
              }"
              @blur="touched[field.key] = true"
            />
          </div>
          <button
            v-if="field.secret"
            type="button"
            class="password-toggle"
            :aria-label="`${visible[field.key] ? 'Ocultar' : 'Mostrar'} ${field.key === 'confirmation' ? 'confirmación de contraseña' : 'contraseña'}`"
            :aria-controls="`auth-${field.key}`"
            @click="visible[field.key] = !visible[field.key]"
          >
            <AppIcon :name="visible[field.key] ? 'eye-off' : 'eye'" :size="19" />
          </button>
        </div>
        <p
          v-if="fieldError(field.key)"
          :id="`auth-${field.key}-error`"
          class="auth-error"
          role="status"
        >
          {{ fieldError(field.key) }}
        </p>
      </div>
    </div>

    <NButton
      class="auth-submit"
      attr-type="submit"
      type="primary"
      size="large"
      block
      :loading="busy"
      :disabled="busy"
    >
      {{ mode === 'login' ? 'Iniciar sesión' : 'Crear cuenta' }}
    </NButton>
  </form>
</template>

<style scoped>
.auth-form {
  display: grid;
  gap: 16px;
}
.auth-fields {
  display: grid;
  gap: 16px;
}
.auth-field-row {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  padding-block-end: 1px;
  border-bottom: 1px solid #a5acc78f;
}
.auth-field-row:has([aria-invalid='true']) {
  border-bottom-color: #ff9cab;
}
.auth-field-row:focus-within {
  padding-block-end: 0;
  border-bottom-width: 2px;
  border-bottom-color: var(--coral);
}
@media (forced-colors: active) {
  .auth-field-row:focus-within {
    outline: 2px solid Highlight;
    outline-offset: 1px;
  }
}
.auth-field-value {
  display: grid;
  gap: 3px;
}
.auth-field label {
  display: block;
  color: var(--text);
  font-size: 12px;
  font-weight: 700;
  line-height: 1.25;
  cursor: text;
}
.auth-field label::after {
  position: absolute;
  inset: 0;
  content: '';
}
.auth-field-value :deep(.n-input),
.password-toggle {
  position: relative;
  z-index: 1;
}
.password-toggle {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--lavender);
}
.password-toggle:focus-visible {
  outline-offset: -4px;
}
@media (hover: hover) {
  .password-toggle:hover {
    background: var(--surface-raised);
  }
}
.auth-error {
  margin: 6px 0 0;
  color: #ff9cab;
  font-size: 12px;
  line-height: 1.4;
}
.auth-submit.n-button {
  min-height: 44px;
  font-weight: 800;
}
</style>
