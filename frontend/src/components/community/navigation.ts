import type { IconName } from '../AppIcon.vue'

export const navigationItems = [
  { key: 'home', label: 'Inicio', icon: 'home' },
  { key: 'neighbors', label: 'Vecinos', icon: 'users' },
  { key: 'create', label: 'Publicar', icon: 'plus' },
  { key: 'market', label: 'Mercadito', icon: 'shop' },
  { key: 'more', label: 'Más', icon: 'more' },
] as const satisfies ReadonlyArray<{ key: string; label: string; icon: IconName }>

export type NavSection = (typeof navigationItems)[number]['key']
