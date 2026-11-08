<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { NButton } from 'naive-ui'
import AppIcon from '../components/AppIcon.vue'
import { api, clearSession } from '../services/api'

const props = defineProps<{ mode: 'request' | 'reset' }>()
const route = useRoute()
const values = reactive({ email: '', password: '', confirmation: '' })
const token = ref(''), error = ref(''), notice = ref(''), saving = ref(false), completed = ref(false)
let generation = 0
watch(() => [props.mode, route.query.email, route.query.token], () => {
  generation++
  values.email = typeof route.query.email === 'string' ? route.query.email : ''
  token.value = typeof route.query.token === 'string' ? route.query.token : ''
  values.password = ''; values.confirmation = ''; error.value = ''; notice.value = ''; saving.value = false; completed.value = false
}, { immediate: true })

async function submit() {
  if (saving.value) return
  error.value = ''
  if (props.mode === 'reset' && values.password !== values.confirmation) {
    error.value = 'Las contraseñas deben coincidir.'
    return
  }
  const version = generation
  saving.value = true
  try {
    const reset = props.mode === 'reset'
    const result = await api<{ message: string }>(reset ? '/auth/reset-password' : '/auth/forgot-password', {
      method: 'POST', referrerPolicy: 'no-referrer',
      body: JSON.stringify(reset ? { email: values.email.trim(), token: token.value, password: values.password, password_confirmation: values.confirmation } : { email: values.email.trim() }),
    })
    if (version !== generation) return
    if (reset) { clearSession(); values.password = ''; values.confirmation = '' }
    notice.value = result.message
    completed.value = true
  } catch (e) {
    if (version === generation) error.value = e instanceof Error ? e.message : 'No pudimos completar la solicitud. Intenta nuevamente.'
  } finally { if (version === generation) saving.value = false }
}
</script>

<template>
  <main id="page-content" class="mobile-app recovery-page">
    <header class="recovery-header">
      <RouterLink to="/iniciar-sesion" class="wordmark" aria-label="masi, iniciar sesión"><AppIcon name="home" :size="30" /><span>masi<span class="brand-dot">.</span></span></RouterLink>
    </header>
    <section class="recovery-card" aria-labelledby="recovery-title">
      <span class="recovery-icon" aria-hidden="true"><AppIcon :name="mode === 'request' ? 'mail' : 'lock'" :size="28" /></span>
      <h1 id="recovery-title">{{ mode === 'request' ? 'Recupera tu cuenta' : 'Crea una contraseña nueva' }}</h1>
      <p v-if="!completed" class="recovery-intro">{{ mode === 'request' ? 'Escribe el correo que usaste al registrarte y te enviaremos un enlace para volver a entrar.' : 'Elige una contraseña de al menos 8 caracteres para volver a tu barrio.' }}</p>

      <div v-if="completed" class="recovery-result" role="status">
        <AppIcon name="check" :size="26" />
        <p>{{ notice }}</p>
        <NButton v-if="mode === 'request'" secondary round @click="completed = false">Usar otro correo</NButton>
        <RouterLink to="/iniciar-sesion" class="recovery-link">Volver a iniciar sesión</RouterLink>
      </div>
      <div v-else-if="mode === 'reset' && (!token || !values.email)" role="alert" class="recovery-error">
        <p>Este enlace está incompleto. Solicita uno nuevo para cambiar tu contraseña.</p>
        <RouterLink to="/recuperar-contrasena" class="recovery-link">Solicitar otro enlace</RouterLink>
      </div>
      <form v-else class="recovery-form" @submit.prevent="submit">
        <label>Correo electrónico<input v-model="values.email" type="email" autocomplete="email" required maxlength="255" :readonly="mode === 'reset'" :disabled="saving" placeholder="Tu correo de registro" /></label>
        <template v-if="mode === 'reset'">
          <label>Nueva contraseña<input v-model="values.password" type="password" autocomplete="new-password" required minlength="8" maxlength="255" :disabled="saving" placeholder="Al menos 8 caracteres" /></label>
          <label>Confirma la contraseña<input v-model="values.confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="255" :disabled="saving" placeholder="Repite la contraseña nueva" /></label>
        </template>
        <p v-if="error" role="alert" class="recovery-error">{{ error }}</p>
        <NButton type="primary" size="large" round block attr-type="submit" :loading="saving" :disabled="saving">{{ mode === 'request' ? 'Enviar enlace' : 'Guardar contraseña nueva' }}</NButton>
        <RouterLink v-if="mode === 'reset'" to="/recuperar-contrasena" class="recovery-link">Solicitar otro enlace</RouterLink>
        <RouterLink to="/iniciar-sesion" class="recovery-back">← Volver a iniciar sesión</RouterLink>
      </form>
    </section>
    <footer>Tu barrio te espera.</footer>
  </main>
</template>

<style scoped>
.recovery-page { min-height: 100dvh; padding: env(safe-area-inset-top,0px) 20px 28px; background: radial-gradient(ellipse at top right, #33203c 0, transparent 58%), var(--surface); border-top: 4px solid var(--coral-strong); }
.recovery-header { display:flex; align-items:center; min-height:76px; }
.recovery-header a { text-decoration:none; }
.recovery-card { margin:32px auto 0; padding:24px 20px; border:1px solid var(--line); border-radius:22px; background:var(--surface-soft); }
.recovery-icon { display:inline-grid; place-items:center; width:54px; height:54px; color:var(--local-accent-text); background:var(--local-accent-fill); border-radius:17px; }
h1 { font-size:1.5rem; line-height:1.25; margin:20px 0 10px; }
.recovery-intro, .recovery-result p { color:var(--muted); font-size:.875rem; line-height:1.7; }
.recovery-form { display:grid; gap:18px; margin-top:24px; }
label { display:grid; gap:8px; font-size:.8125rem; font-weight:800; color:var(--lavender); }
input { min-width:0; width:100%; min-height:48px; border:1px solid var(--line); border-radius:12px; padding:12px; background:var(--surface); color:var(--text); font:inherit; font-size:.9375rem; }
input:focus { outline:2px solid var(--coral); outline-offset:2px; }
input[readonly] { color:var(--muted); }
.recovery-error { color:var(--local-accent-text); font-size:.8125rem; line-height:1.6; overflow-wrap:anywhere; }
.recovery-result { display:grid; gap:12px; margin-top:24px; color:var(--lavender); }
.recovery-link, .recovery-back { display:flex; align-items:center; justify-content:center; min-height:44px; font-size:.8125rem; text-align:center; color:var(--local-accent-text); }
.recovery-back { color:var(--muted); text-decoration:none; }
footer { margin-top:28px; text-align:center; color:var(--muted); font-size:.75rem; }
</style>
