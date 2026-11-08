<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue'
const update = ref(false)
const build = import.meta.env.VITE_MASI_BUILD_ID
let timer: ReturnType<typeof setInterval> | undefined
async function check() {
  if(import.meta.env.DEV || document.visibilityState!=='visible') return
  try {
    const response=await fetch(`/build/version.json?t=${Date.now()}`,{cache:'no-store',signal:AbortSignal.timeout(5000)})
    if(!response.ok)return
    const data=await response.json()
    update.value=typeof data.id==='string'&&data.id!==build
  }catch { /* Updates do not interrupt offline use or forms. */ }
}
const refresh=()=>location.reload()
onMounted(()=>{void check();timer=setInterval(()=>{void check()},60000);document.addEventListener('visibilitychange',check)})
onBeforeUnmount(()=>{clearInterval(timer);document.removeEventListener('visibilitychange',check)})
</script>
<template><aside v-if="update" class="version-notice" role="status"><span>Hay una nueva versión de masi.</span><button type="button" @click="refresh">Actualizar</button></aside></template>
<style scoped>.version-notice{display:flex;align-items:center;justify-content:center;gap:12px;padding:10px 16px;background:var(--surface-raised);color:var(--text);font-size:12px}.version-notice button{border:1px solid var(--coral);border-radius:12px;background:transparent;color:var(--coral);padding:6px 12px;cursor:pointer}</style>
