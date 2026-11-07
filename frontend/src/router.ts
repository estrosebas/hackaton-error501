import { createRouter, createWebHistory } from 'vue-router'
import { nextTick } from 'vue'
import CommunityFeed from './views/CommunityFeed.vue'
import type { NavSection } from './components/community/BottomNavigation.vue'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    section?: NavSection
  }
}

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/inicio' },
    {
      path: '/inicio',
      name: 'home',
      component: CommunityFeed,
      meta: { title: 'Inicio', section: 'home' },
    },
    {
      path: '/vecinos',
      name: 'neighbors',
      component: () => import('./views/NeighborsView.vue'),
      meta: { title: 'Vecinos', section: 'neighbors' },
    },
    {
      path: '/publicar',
      name: 'create',
      component: () => import('./views/PublishView.vue'),
      meta: { title: 'Publicar', section: 'create' },
    },
    {
      path: '/mercadito',
      name: 'market',
      component: () => import('./views/MarketplaceView.vue'),
      meta: { title: 'Mercadito', section: 'market' },
    },
    {
      path: '/mercadito/:id',
      name: 'product',
      component: () => import('./views/ProductView.vue'),
      meta: { title: 'Producto', section: 'market' },
    },
    {
      path: '/mas',
      name: 'more',
      component: () => import('./views/MoreView.vue'),
      meta: { title: 'Más', section: 'more' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('./views/NotFoundView.vue'),
      meta: { title: 'Página no encontrada' },
    },
  ],
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    const target = to.hash && document.getElementById(to.hash.slice(1))
    return target ? { el: target, top: 8 } : { top: 0 }
  },
})

router.afterEach(async (to, from, failure) => {
  if (failure) return
  document.title = `${to.meta.title ?? 'Tu barrio'} · masi`
  if (from.name && to.path !== from.path && !to.hash) {
    await nextTick()
    document.getElementById('page-content')?.focus({ preventScroll: true })
  }
})
