<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import AppIcon, { type IconName } from '../AppIcon.vue'

const props = defineProps<{ district: string; listings?: number; favorites?: number }>()
const emit = defineEmits<{ openPanel: [panel: 'activities' | 'neighborhood'] }>()

type Shortcut = { icon: IconName; label: string; detail: string } & (
  | { to: RouteLocationRaw; panel?: never }
  | { panel: 'activities' | 'neighborhood'; to?: never }
)

const groups = computed<{ title: string; items: Shortcut[] }[]>(() => [
  {
    title: 'Tu barrio',
    items: [
      {
        icon: 'calendar',
        label: 'Planes del barrio',
        detail: 'Consulta la agenda de actividades',
        panel: 'activities',
      },
      {
        icon: 'pin',
        label: 'Distrito de registro',
        detail: props.district,
        panel: 'neighborhood',
      },
    ],
  },
  {
    title: 'Mercadito',
    items: [
      {
        icon: 'shop',
        label: 'Mis anuncios',
        detail: props.listings === undefined ? 'Ver tus publicaciones' : props.listings === 1 ? '1 producto publicado' : `${props.listings} productos publicados`,
        to: { name: 'market-mine' },
      },
      {
        icon: 'bookmark',
        label: 'Productos guardados',
        detail: props.favorites === undefined ? 'Ver tus favoritos' : props.favorites === 1 ? '1 favorito' : `${props.favorites} favoritos`,
        to: { name: 'market-saved' },
      },
    ],
  },
])
</script>

<template>
  <div class="profile-shortcuts">
    <section v-for="group in groups" :key="group.title">
      <h2>{{ group.title }}</h2>
      <ul class="shortcut-list">
        <li v-for="item in group.items" :key="item.label">
          <component
            :is="item.to ? RouterLink : 'button'"
            v-bind="item.to ? { to: item.to } : { type: 'button', 'aria-haspopup': 'dialog' }"
            class="shortcut"
            @click="item.panel && emit('openPanel', item.panel)"
          >
            <span class="shortcut-icon"><AppIcon :name="item.icon" :size="20" /></span>
            <span class="shortcut-text">
              <strong>{{ item.label }}</strong>
              <small>{{ item.detail }}</small>
            </span>
            <AppIcon class="shortcut-chevron" name="chevron" :size="19" />
          </component>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.profile-shortcuts {
  display: grid;
  align-content: start;
  gap: 22px;
  min-width: 0;
}

h2 {
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 850;
}

.shortcut-list {
  margin: 0;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-soft);
  list-style: none;
}

.shortcut-list li + li {
  border-top: 1px solid var(--line);
}

.shortcut {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 11px;
  width: 100%;
  min-height: 62px;
  padding: 10px 12px;
  border: 0;
  border-radius: 13px;
  color: var(--text);
  background: transparent;
  text-align: start;
  text-decoration: none;
}

.shortcut:focus-visible {
  position: relative;
  outline-offset: -3px;
}

@media (hover: hover) {
  .shortcut:hover {
    background: var(--surface-raised);
  }
}

.shortcut:active {
  background: var(--surface-raised);
}

.shortcut-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  color: var(--lavender);
  background: var(--surface-raised);
}

.shortcut-text {
  display: grid;
  gap: 2px;
  overflow-wrap: anywhere;
  line-height: 1.35;
}

.shortcut-text strong {
  font-size: 0.9375rem;
  font-weight: 800;
}

.shortcut-text small {
  color: var(--muted);
  font-size: 0.8125rem;
}

.shortcut-chevron {
  color: var(--muted);
  transform: rotate(-90deg);
}
</style>
