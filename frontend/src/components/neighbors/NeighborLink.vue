<script setup lang="ts">
import { computed } from 'vue'
import { NDropdown } from 'naive-ui'
import { useRouter } from 'vue-router'
import { currentUser } from '../../composables/useAuth'
defineOptions({ inheritAttrs: false })
const props = defineProps<{ id: number; name: string }>()
const router = useRouter()
const options = computed(() => [
  { label: 'Ver perfil', key: 'profile' },
  ...(props.id === currentUser.value?.id
    ? []
    : [{ label: 'Escribir mensaje', key: 'message' }]),
])
function select(key: string) {
  void router.push(
    key === 'profile'
      ? `/vecinos/${props.id}`
      : { path: '/mensajes', query: { to: props.id } },
  )
}
</script>
<template>
  <NDropdown trigger="click" :options="options" @select="select">
    <button
      v-bind="$attrs"
      type="button"
      class="neighbor-link"
      :aria-label="`Opciones de ${name}`"
      @click.stop
    >
      <slot />
    </button>
  </NDropdown>
</template>
<style scoped>
.neighbor-link {
  display: inline-flex;
  align-items: center;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: inherit;
  cursor: pointer;
  border-radius: 8px;
}
.neighbor-link:hover {
  color: var(--local-accent-text);
}
.neighbor-link:focus-visible {
  outline: 2px solid var(--lavender);
  outline-offset: 3px;
}
</style>
