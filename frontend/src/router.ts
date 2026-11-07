import { createRouter, createWebHistory } from 'vue-router'
import { nextTick } from 'vue'
import type { NavSection } from './components/community/BottomNavigation.vue'
import { validateSession } from './composables/useAuth'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    section?: NavSection
    requiresSession?: boolean
    guestOnly?: boolean
  }
}

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/iniciar-sesion',
      name: 'login',
      component: () => import('./views/AuthView.vue'),
      props: { mode: 'login' },
      meta: { title: 'Iniciar sesión', guestOnly: true },
    },
    {
      path: '/registro',
      name: 'register',
      component: () => import('./views/AuthView.vue'),
      props: { mode: 'register' },
      meta: { title: 'Crear cuenta', guestOnly: true },
    },
    {
      path: '/',
      component: () => import('./components/layout/AppShell.vue'),
      meta: { requiresSession: true },
      children: [
        { path: '', redirect: { name: 'home' } },
        {
          path: 'inicio',
          name: 'home',
          component: () => import('./views/CommunityFeed.vue'),
          meta: { title: 'Inicio', section: 'home' },
        },
        {
          path: 'vecinos',
          name: 'neighbors',
          component: () => import('./views/NeighborsView.vue'),
          meta: { title: 'Vecinos', section: 'neighbors' },
        },
        {
          path: 'vecinos/:neighborId',
          name: 'neighbor-profile',
          component: () => import('./views/NeighborView.vue'),
          meta: { title: 'Perfil del vecino', section: 'neighbors' },
        },
        {
          path: 'mensajes/:roomId?',
          name: 'messages',
          component: () => import('./views/ChatsView.vue'),
          meta: { title: 'Mensajes', section: 'home' },
        },
        {
          path: 'publicar',
          name: 'create',
          component: () => import('./views/PublishView.vue'),
          meta: { title: 'Publicar', section: 'create' },
        },
        {
          path: 'publicar/actividad',
          name: 'create-activity',
          component: () => import('./views/PublishView.vue'),
          meta: { title: 'Publicar actividad', section: 'create' },
        },
        {
          path: 'publicar/producto',
          name: 'create-product',
          component: () => import('./views/PublishView.vue'),
          meta: { title: 'Publicar producto', section: 'create' },
        },
        {
          path: 'mercadito',
          name: 'market',
          component: () => import('./views/MarketplaceView.vue'),
          meta: { title: 'Mercadito', section: 'market' },
        },
        {
          path: 'mercadito/mis-anuncios',
          name: 'market-mine',
          component: () => import('./views/MarketplaceView.vue'),
          meta: { title: 'Mis anuncios', section: 'market' },
        },
        {
          path: 'mercadito/guardados',
          name: 'market-saved',
          component: () => import('./views/MarketplaceView.vue'),
          meta: { title: 'Productos guardados', section: 'market' },
        },
        {
          path: 'mercadito/:id',
          name: 'product',
          component: () => import('./views/ProductView.vue'),
          meta: { title: 'Producto', section: 'market' },
        },
        {
          path: 'mas',
          name: 'more',
          component: () => import('./views/MoreView.vue'),
          meta: { title: 'Mi perfil', section: 'more' },
        },
        {
          path: ':pathMatch(.*)*',
          name: 'not-found',
          component: () => import('./views/NotFoundView.vue'),
          meta: { title: 'Página no encontrada' },
        },
      ],
    },
  ],
  scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    const target = to.hash && document.getElementById(to.hash.slice(1))
    return target ? { el: target, top: 8 } : { top: 0 }
  },
})

let navigating = false
router.beforeEach(async (to) => {
  navigating = true
  // Keep old bookmarks working while the app links to the new paths.
  if (to.name === 'create' && 'tipo' in to.query) {
    const { tipo, ...query } = to.query
    return {
      name: tipo === 'producto' ? 'create-product' : 'create',
      query,
      hash: to.hash,
    }
  }

  if (to.name === 'market' && 'vista' in to.query) {
    const { vista, ...query } = to.query
    return {
      name: vista === 'mios' ? 'market-mine' : vista === 'guardados' ? 'market-saved' : 'market',
      query,
      hash: to.hash,
    }
  }

  const valid = (to.meta.requiresSession || to.meta.guestOnly) ? await validateSession() : false
  if (to.meta.requiresSession && !valid) {
    return {
      name: 'login',
      state: { masiContinueTo: to.fullPath === '/inicio' ? null : to.fullPath },
    }
  }

  if (to.meta.guestOnly) {
    if (valid) return { name: 'home', state: { masiContinueTo: null } }

    if ('continuar' in to.query) {
      const { continuar, ...query } = to.query
      return {
        path: to.path,
        query,
        hash: to.hash,
        state: { masiContinueTo: typeof continuar === 'string' ? continuar : null },
      }
    }
  }
})

router.afterEach(async (to, from, failure) => {
  navigating = false
  if (failure) return
  document.title = `${to.meta.title ?? 'Tu barrio'} · masi`
  const sameSectionView =
    to.meta.section &&
    to.meta.section === from.meta.section &&
    to.matched.at(-1)?.components?.default === from.matched.at(-1)?.components?.default
  if (from.name && to.path !== from.path && !to.hash && !sameSectionView) {
    await nextTick()
    document.getElementById('page-content')?.focus({ preventScroll: true })
  }
})

function sessionEnded() {
  if (!navigating && router.currentRoute.value.meta.requiresSession) void router.replace({ name: 'login', state: { masiContinueTo: router.currentRoute.value.fullPath } })
}
window.addEventListener('masi:session-ended', sessionEnded)
async function recheckSession() {
  if (navigating || document.visibilityState !== 'visible' || !router.currentRoute.value.meta.requiresSession) return
  if (!await validateSession() && router.currentRoute.value.meta.requiresSession) {
    await router.replace({ name: 'login', state: { masiContinueTo: router.currentRoute.value.fullPath } })
  }
}
function checkVisibleSession() { void recheckSession() }
window.addEventListener('focus', checkVisibleSession)
document.addEventListener('visibilitychange', checkVisibleSession)
window.addEventListener('pageshow', checkVisibleSession)
const sessionInterval = setInterval(() => { void recheckSession() }, 30000)
if (import.meta.hot) import.meta.hot.dispose(() => {
  clearInterval(sessionInterval)
  window.removeEventListener('masi:session-ended', sessionEnded)
  window.removeEventListener('focus', checkVisibleSession)
  document.removeEventListener('visibilitychange', checkVisibleSession)
  window.removeEventListener('pageshow', checkVisibleSession)
})
