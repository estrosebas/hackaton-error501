<script setup lang="ts">
import { shallowRef, watch } from 'vue'
import { useMessage } from 'naive-ui'
import { RouterLink, useRouter } from 'vue-router'
import AuthForm from '../components/auth/AuthForm.vue'
import RegistrationForm from '../components/auth/RegistrationForm.vue'
import AppIcon from '../components/AppIcon.vue'
import { login, sessionError } from '../composables/useAuth'

const props = defineProps<{ mode: 'login' | 'register' }>()
const router = useRouter()
const message = useMessage()
const entering = shallowRef(false)
watch(() => props.mode, () => { sessionError.value = '' })

async function enterCommunity() {
  if (entering.value) return
  const redirect = router.options.history.state.masiContinueTo
  const destination =
    typeof redirect === 'string' &&
    redirect.startsWith('/') &&
    !redirect.startsWith('//') &&
    !redirect.includes('\\') &&
    !/[\t\r\n]/.test(redirect)
      ? router.resolve(redirect)
      : null

  entering.value = true
  try {
    await router.replace({
      ...(destination?.meta.requiresSession
        ? { path: destination.path, query: destination.query, hash: destination.hash }
        : { name: 'home' }),
      state: { masiContinueTo: null },
    })
  } catch {
    message.error('No pudimos abrir tu barrio. Intenta de nuevo.')
  } finally {
    entering.value = false
  }
}

async function signIn(credentials: { dni: string; password: string }) {
  if (entering.value) return
  entering.value = true
  sessionError.value = ''
  try { await login(credentials) }
  catch (error) { sessionError.value = error instanceof Error ? error.message : 'No pudimos iniciar sesión.'; return }
  finally { entering.value = false }
  await enterCommunity()
}
</script>

<template>
  <main
    id="page-content"
    class="mobile-app auth-page"
    :class="{ 'auth-page--register': mode === 'register' }"
    tabindex="-1"
  >
    <header class="auth-header">
      <div class="wordmark">
        <AppIcon name="home" :size="31" /><span
          >masi<span class="brand-dot">.</span></span
        >
      </div>
      <span class="auth-demo-badge">{{ mode === 'login' ? 'Bienvenido' : 'Crear cuenta' }}</span>
    </header>

    <div class="auth-hero">
      <div class="auth-neighbours">
        <div class="auth-portraits" aria-hidden="true">
          <img src="/images/lucia.jpg" alt="" width="28" height="28" />
          <img src="/images/miguel.jpg" alt="" width="28" height="28" />
          <img src="/images/carla.jpg" alt="" width="28" height="28" />
        </div>
        <span>Entre vecinos</span>
      </div>
      <p>Tu barrio, más cerca.</p>
    </div>

    <div class="auth-content">
      <section class="auth-card" aria-labelledby="auth-title">
        <header class="auth-intro">
          <h1 id="auth-title">{{ mode === 'login' ? 'Inicia sesión' : 'Únete a masi' }}</h1>
          <p>
            {{
              mode === 'login'
                ? 'Descubre qué está pasando cerca de ti.'
                : 'Conoce a tus vecinos y comparte con tu barrio.'
            }}
          </p>
        </header>
        <RegistrationForm v-if="mode === 'register'" @registered="enterCommunity" />
        <AuthForm v-else
          :key="mode"
          :mode="mode"
          :busy="entering"
          @submit="signIn"
        />
        <p v-if="sessionError" class="session-error" role="alert">{{ sessionError }}</p>
        <p class="auth-switch">
          {{
            mode === 'login'
              ? '¿Primera vez en masi?'
              : '¿Ya tienes una cuenta?'
          }}
          <RouterLink
            :to="{
              name: mode === 'login' ? 'register' : 'login',
              state: { masiContinueTo: router.options.history.state.masiContinueTo ?? null },
            }"
          >
            {{ mode === 'login' ? 'Crea tu cuenta' : 'Inicia sesión' }}
          </RouterLink>
        </p>
      </section>

      <footer class="auth-footer">
        <p>{{ mode === 'login' ? 'Ingresa con tu DNI y la contraseña que creaste al registrarte.' : 'Tus datos de registro y tu fotografía se guardan de forma privada.' }}</p>
        <a href="https://unsplash.com/photos/O1D1C4DL-H0">Foto de Will Goodman · Cusco</a>
      </footer>
    </div>
  </main>
</template>

<style scoped>
.session-error { color: #ffb0c0; font-size: 13px; line-height: 1.5; }
.auth-page {
  --auth-gutter: clamp(14px, 5vw, 24px);
  --auth-scrim-position: 46%;
  display: flex;
  flex-direction: column;
  padding: env(safe-area-inset-top, 0px) 0
    max(6px, env(safe-area-inset-bottom, 0px));
  background:
    linear-gradient(
      180deg,
      #101426d9,
      #1014261a 16%,
      #101426a6 var(--auth-scrim-position),
      var(--surface) 98%
    ),
    url('/images/cusco-auth.webp') center top / cover no-repeat,
    var(--surface);
}
.auth-page--register {
  --auth-scrim-position: 29%;
}
.auth-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 72px;
  padding-inline: var(--auth-gutter);
}
.auth-header .wordmark {
  gap: 7px;
  font-size: 34px;
  text-shadow: 0 2px 10px #080a14;
}
.auth-demo-badge {
  padding: 6px 10px;
  border: 1px solid #ffffff4d;
  border-radius: 999px;
  color: var(--text);
  background: #101426d9;
  font-size: 12px;
  font-weight: 800;
}
.auth-hero {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-end;
  gap: 10px;
  min-height: 172px;
  padding: 18px var(--auth-gutter) 16px;
  color: var(--text);
}
.auth-page--register .auth-hero {
  min-height: 130px;
}
.auth-neighbours {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 3px 9px 3px 4px;
  border: 1px solid #ffffff40;
  border-radius: 999px;
  background: #101426d9;
  font-size: 12px;
  font-weight: 800;
}
.auth-portraits {
  display: flex;
  align-items: center;
}
.auth-portraits img {
  display: block;
  border: 1px solid var(--lavender);
  border-radius: 50%;
  object-fit: cover;
}
.auth-portraits img + img {
  margin-left: -7px;
}
.auth-hero p {
  margin: 0;
  font-size: clamp(29px, 8.5vw, 42px);
  font-weight: 900;
  letter-spacing: -0.045em;
  line-height: 1.05;
  text-shadow: 0 2px 12px #090c18;
}
.auth-content {
  padding-inline: var(--auth-gutter);
}
.auth-card {
  padding: clamp(15px, 5vw, 24px);
  border-radius: 16px;
  background: linear-gradient(150deg, var(--surface-raised), var(--surface));
}
@supports (backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)) {
  .auth-card {
    background: linear-gradient(150deg, #2b314ae8, #101426e6 82%);
    -webkit-backdrop-filter: blur(18px);
    backdrop-filter: blur(18px);
  }
}
.auth-intro {
  margin-bottom: 16px;
}
.auth-intro h1 {
  margin: 0;
  color: var(--text);
  font-size: clamp(26px, 7vw, 33px);
  font-weight: 900;
  letter-spacing: -0.035em;
  line-height: 1.1;
}
.auth-intro p {
  margin: 5px 0 0;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.4;
}
.auth-switch {
  margin: 3px 0 0;
  color: var(--muted);
  text-align: center;
  font-size: 13px;
}
.auth-switch a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  margin-left: 4px;
  color: #ff9bb4;
  font-weight: 800;
  text-underline-offset: 3px;
}
.auth-footer {
  margin-top: 12px;
  color: var(--muted);
  text-align: center;
  font-size: 12px;
  line-height: 1.5;
}
.auth-footer p {
  margin: 0;
}
.auth-footer a {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  color: var(--lavender);
  text-underline-offset: 3px;
}
@media (max-height: 720px) {
  .auth-header {
    min-height: 60px;
  }
  .auth-page--register .auth-hero {
    min-height: 106px;
    padding-block: 10px 12px;
  }
}
</style>
