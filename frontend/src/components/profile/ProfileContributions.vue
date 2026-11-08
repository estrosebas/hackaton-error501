<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useMapEvents } from '../../composables/useMapEvents'
const { catalog, loadCatalog } = useMapEvents()
const ready=ref(false),failed=ref(false)
async function load(){ready.value=false;failed.value=false;try{await loadCatalog(true);ready.value=true}catch{failed.value=true}}
onMounted(()=>{void load()})
</script>
<template><section class="contributions"><h2>Mis aportes al barrio</h2><p v-if="failed" role="alert">No pudimos consultar tus aportes. <button type="button" @click="load">Reintentar</button></p><p v-else-if="!ready" role="status">Consultando tus aportes…</p><div v-else class="contribution-totals"><strong>{{catalog.points}} <small>puntos de empatía</small></strong><strong>{{catalog.attendances ?? 0}} <small>asistencias validadas</small></strong></div><p>Inscribirse o publicar no suma puntos. La asistencia se valida durante el evento, a 100 m o menos. El apoyo con costo cuenta como asistencia, pero no otorga puntos.</p></section></template>
<style scoped>.contributions{border:1px solid var(--line);border-radius:16px;padding:20px;background:var(--surface-soft)}.contributions h2{margin:0 0 12px;font-size:17px}.contribution-totals{display:flex;gap:20px;flex-wrap:wrap}.contribution-totals strong{font-size:24px;color:var(--lavender);display:grid;gap:4px}.contribution-totals small{font-size:12px;color:var(--text)}.contributions p{font-size:12px;color:var(--muted);line-height:1.6}.contributions button{color:var(--coral);background:transparent;border:0;cursor:pointer}</style>
