import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import { router } from './router'
import { initializeNativeRuntime } from './native/runtime'

void initializeNativeRuntime().catch(()=>{})

// Keep the HTML loading screen visible while the initial guard and route chunk load.
const app = createApp(App).use(router)
void router.isReady().then(() => app.mount('#app')).catch(() => {
  const root = document.getElementById('app')
  if (!root) return
  const message = document.createElement('p')
  message.textContent = 'No pudimos abrir masi. Revisa tu conexión y vuelve a intentar.'
  const retry = document.createElement('button')
  retry.textContent = 'Reintentar'
  retry.onclick = () => location.reload()
  root.replaceChildren(message, retry)
})
