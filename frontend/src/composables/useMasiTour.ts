import { shallowRef, nextTick } from 'vue'
import type { Driver, DriveStep } from 'driver.js'
import type { Router } from 'vue-router'
import { currentUser } from './useAuth'
import type { MapCategory } from './useMapEvents'

export const tourRunning = shallowRef(false)
let active: Driver | undefined
let detachViewport: (() => void) | undefined
function clearViewportTracking() { detachViewport?.(); detachViewport = undefined }
type TourKind = 'home' | 'map'
const key = (id: number, kind: TourKind = 'home') => `masi.${kind === 'map' ? 'map-tour' : 'tour'}.v1.${id}`
export function hasSeenTour(id: number, kind: TourKind = 'home') {
  try { return Boolean(localStorage.getItem(key(id, kind))) } catch { return false }
}
export function stopMasiTour() { active?.destroy(); clearViewportTracking(); active = undefined; tourRunning.value = false }

export async function startMasiTour(router: Router) {
  return startTour(router, 'home', [
    { element: '[data-tour="feed"]', popover: { title: 'Tu barrio en Inicio', description: 'Encuentra ayuda, servicios y novedades de tu distrito. Busca por palabras o categoría. El visto bueno vecinal recomienda a la persona y es independiente de un Me gusta.', side: 'bottom' } },
    { element: '[data-tour="create"] .nav-icon', popover: { title: 'Mapa, encuentros y puntos', description: 'El botón + crea un evento en el mapa. En Mapa puedes cambiar el radio. Los puntos se suman al inscribirte y validar asistencia durante el evento, a 100 m o menos. El apoyo con costo no da puntos.', side: 'top' } },
    { element: '[data-tour="more"] .nav-icon', popover: { title: 'Tu perfil, a tu manera', description: 'En Más gestionas tus datos privados, foto y gustos. También puedes volver a abrir esta guía desde Cómo funciona masi.', side: 'top', align: 'end' } },
  ])
}

export async function startMapTour(router: Router, categories: MapCategory[]) {
  const legend = document.createElement('div')
  legend.className = 'map-tour-legend'
  for (const category of categories) {
    const row = document.createElement('div')
    const dot = document.createElement('span')
    dot.className = 'map-tour-color'
    dot.style.borderColor = category.color
    row.append(dot, document.createTextNode(category.label))
    legend.append(row)
  }
  return startTour(router, 'map', [
    { element: '[data-tour="map-radius"]', popover: { title: 'Explora cerca de ti', description: 'El radio muestra eventos alrededor de tu ubicación en el mapa: 300 m, 600 m o 1 km. Todo mi distrito amplía la vista a los eventos de tu distrito de registro.', side: 'bottom', align: 'end' } },
    { element: '[data-tour="map-categories"]', popover: { title: 'Cada color cuenta una historia', description: `${legend.outerHTML}<p>El borde de cada foto indica el tipo de evento. Las emergencias destacan en rojo. Usa este filtro para ver una categoría o todas.</p>`, side: 'bottom', align: 'end' } },
    { element: '[data-tour="map-canvas"]', popover: { title: 'Toca un evento para conocerlo', description: 'Toca una foto del mapa para ver los detalles, el horario y el lugar. Puedes inscribirte; los puntos se acreditan al validar tu asistencia durante el evento, a 100 m o menos. La inscripción sola no suma puntos.', side: 'top' } },
    { element: '[data-tour="create"] .nav-icon', popover: { title: 'Cómo crear una emergencia', description: '1. Toca el botón +.<br>2. Elige <strong>Emergencias</strong> y el tipo: incendio, inundación, accidente u otra emergencia.<br>3. Describe lo que ocurre, revisa el horario y confirma tu ubicación o elige el punto en el mapa.<br>4. Publica: aparecerá con borde rojo.<p>Usa esta categoría para situaciones reales. Publicar aquí no contacta automáticamente a los servicios de emergencia.</p>', side: 'top' } },
  ])
}

async function startTour(router: Router, kind: TourKind, steps: DriveStep[]) {
  const id = currentUser.value?.id
  if (!id || tourRunning.value) return
  tourRunning.value = true
  try {
    const routeName = kind === 'map' ? 'neighbors' : 'home'
    await router.push({ name: routeName })
    await nextTick()
    const { driver } = await import('driver.js')
    await import('driver.js/dist/driver.css')
    if (currentUser.value?.id !== id || router.currentRoute.value.name !== routeName) return
    const remember = () => { try { localStorage.setItem(key(id, kind), 'seen') } catch { /* Storage may be disabled. */ } }
    active = driver({
      showProgress: true, progressText: '{{current}} de {{total}}',
      nextBtnText: 'Siguiente', prevBtnText: 'Atrás', doneBtnText: 'Entendido', closeBtnLabel: 'Cerrar guía',
      allowClose: true, disableActiveInteraction: true, waitForElement: 2000,
      animate: !matchMedia('(prefers-reduced-motion: reduce)').matches,
      overlayOpacity: .7, stageRadius: 12, stagePadding: 8, popoverOffset: 20, popoverClass: 'masi-tour',
      steps,
      onDoneClick: (_element, _step, options) => { remember(); options.driver.destroy() },
      onCloseClick: (_element, _step, options) => { remember(); options.driver.destroy() },
      onDestroyed: (_element, _step, options) => {
        clearViewportTracking()
        if (currentUser.value?.id === id && options.reason !== 'api') {
          remember()
        }
        active = undefined
        tourRunning.value = false
      },
    })
    let frame = 0
    const refresh = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(() => active?.refresh()) }
    const viewport = window.visualViewport
    viewport?.addEventListener('resize', refresh)
    viewport?.addEventListener('scroll', refresh)
    window.addEventListener('orientationchange', refresh)
    detachViewport = () => {
      cancelAnimationFrame(frame)
      viewport?.removeEventListener('resize', refresh)
      viewport?.removeEventListener('scroll', refresh)
      window.removeEventListener('orientationchange', refresh)
    }
    active.drive()
  } catch {
    clearViewportTracking()
    tourRunning.value = false
  } finally {
    if (!active) tourRunning.value = false
  }
}
