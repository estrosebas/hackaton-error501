import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import { router } from './router'
import { initializeNativeRuntime } from './native/runtime'

void initializeNativeRuntime().catch(()=>{})

createApp(App).use(router).mount('#app')
