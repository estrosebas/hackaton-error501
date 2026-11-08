import { shallowRef, nextTick } from 'vue'
import type { Driver } from 'driver.js'
import type { Router } from 'vue-router'
import { currentUser } from './useAuth'

export const tourRunning = shallowRef(false)
let active: Driver | undefined
const key = (id: number) => `masi.tour.v1.${id}`
export function hasSeenTour(id: number) {
  try { return Boolean(localStorage.getItem(key(id))) } catch { return false }
}
export function stopMasiTour() { active?.destroy(); active = undefined; tourRunning.value = false }

export async function startMasiTour(router: Router) {
  const id = currentUser.value?.id
  if (!id || tourRunning.value) return
  tourRunning.value = true
  try {
    await router.push({ name: 'home' })
    await nextTick()
    const { driver } = await import('driver.js')
    await import('driver.js/dist/driver.css')
    if (currentUser.value?.id !== id || router.currentRoute.value.name !== 'home') return
    const remember = () => { try { localStorage.setItem(key(id), 'seen') } catch { /* Storage may be disabled. */ } }
    active = driver({
      showProgress: true, progressText: '{{current}} de {{total}}',
      nextBtnText: 'Siguiente', prevBtnText: 'Atrás', doneBtnText: 'Entendido', closeBtnLabel: 'Cerrar guía',
      allowClose: true, disableActiveInteraction: true, waitForElement: 2000,
      animate: !matchMedia('(prefers-reduced-motion: reduce)').matches,
      overlayOpacity: .7, stageRadius: 12, popoverClass: 'masi-tour',
      steps: [
        { element: '[data-tour="feed"]', popover: { title: 'Tu barrio en Inicio', description: 'Encuentra ayuda, servicios y novedades de tu distrito. Busca por palabras o categoría. El visto bueno vecinal recomienda a la persona y es independiente de un Me gusta.', side: 'bottom' } },
        { element: '[data-tour="create"]', popover: { title: 'Mapa, encuentros y puntos', description: 'El botón + crea un evento en el mapa. En Mapa puedes cambiar el radio. Los puntos se suman al inscribirte y validar asistencia durante el evento, a 100 m o menos. El apoyo con costo no da puntos.', side: 'top' } },
        { element: '[data-tour="more"]', popover: { title: 'Tu perfil, a tu manera', description: 'En Más gestionas tus datos privados, foto y gustos. También puedes volver a abrir esta guía desde Cómo funciona masi.', side: 'top', align: 'end' } },
      ],
      onDoneClick: (_element, _step, options) => { remember(); options.driver.destroy() },
      onCloseClick: (_element, _step, options) => { remember(); options.driver.destroy() },
      onDestroyed: (_element, _step, options) => {
        if (currentUser.value?.id === id && options.reason !== 'api') {
          remember()
        }
        active = undefined
        tourRunning.value = false
      },
    })
    active.drive()
  } catch {
    tourRunning.value = false
  } finally {
    if (!active) tourRunning.value = false
  }
}
