<script setup lang="ts">
import { NEmpty } from 'naive-ui'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
</script>

<template>
  <section class="standard-page">
    <div class="page-heading">
      <div>
        <h1>No encontramos esta página</h1>
        <p>Regresa a Inicio para seguir explorando tu barrio.</p>
      </div>
    </div>
    <NEmpty description="Esta dirección no está disponible"
      ><template #extra
        ><RouterLink :to="{ name: 'home' }" class="text-link"
          >Volver a Inicio
          <AppIcon name="arrow" :size="16" /></RouterLink></template
    ></NEmpty>
  </section>
</template>
