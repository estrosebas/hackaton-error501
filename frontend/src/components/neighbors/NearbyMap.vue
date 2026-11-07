<script setup lang="ts">
import {
  onBeforeUnmount,
  onMounted,
  shallowRef,
  useTemplateRef,
  watch,
} from 'vue'
import * as L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { NButton } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import type {
  Coordinates,
  NearbyNeighbor,
} from '../../composables/useNearbyNeighbors'

const props = defineProps<{
  center: Coordinates
  neighbors: NearbyNeighbor[]
  selectedId?: string
  radius: number
}>()
const emit = defineEmits<{ select: [id: string] }>()
const container = useTemplateRef<HTMLDivElement>('map-container')
const tileLoaded = shallowRef(false)
const tileFailed = shallowRef(false)
let map: L.Map | undefined
let tiles: L.TileLayer | undefined
let markers: L.LayerGroup | undefined
let radiusCircle: L.Circle | undefined
let origin: L.Marker | undefined
let resizeObserver: ResizeObserver | undefined
const neighborMarkers = new Map<string, L.Marker>()
const reducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

function bottomPadding() {
  // Short screens put the profile below the map, freeing the lower edge.
  return map && map.getSize().y < 360 ? 43 : 205
}

function fitNeighbors() {
  if (!map) return
  const positions: L.LatLngExpression[] = [
    props.center,
    ...props.neighbors.map((person) => person.coordinates),
  ]
  const bounds = L.latLngBounds(positions)
  map.fitBounds(bounds, {
    paddingTopLeft: [36, 62],
    paddingBottomRight: [55, bottomPadding()],
    maxZoom: 16.5,
    animate: false,
  })
}

function updateSelection() {
  for (const [id, pin] of neighborMarkers) {
    const selected = id === props.selectedId
    pin.getElement()?.classList.toggle('is-selected', selected)
    pin.getElement()?.setAttribute('aria-pressed', String(selected))
    pin.setZIndexOffset(selected ? 1000 : 0)
  }
  const person = props.neighbors.find(
    (neighbor) => neighbor.id === props.selectedId,
  )
  if (person)
    map?.panInside(person.coordinates, {
      paddingTopLeft: [40, 70],
      paddingBottomRight: [55, bottomPadding()],
      animate: !reducedMotion(),
    })
}

function renderNeighbors() {
  if (!map || !markers) return
  markers.clearLayers()
  neighborMarkers.clear()
  for (const person of props.neighbors) {
    const content = document.createElement('div')
    content.className = 'neighbor-map-face'
    const image = document.createElement('img')
    image.src = person.avatar
    image.alt = ''
    image.width = 44
    image.height = 44
    const name = document.createElement('span')
    name.className = 'neighbor-pin-name'
    name.textContent = person.name
    content.append(image, name)
    const pin = L.marker(person.coordinates, {
      icon: L.divIcon({
        className: 'neighbor-map-pin',
        html: content,
        iconSize: [52, 68],
        iconAnchor: [26, 30],
      }),
      title: `${person.name}, a ${person.distance} metros`,
      keyboard: true,
      riseOnHover: true,
    }).addTo(markers)
    pin.on('click', () => emit('select', person.id))
    const element = pin.getElement()
    element?.setAttribute(
      'aria-label',
      `Ver a ${person.name}, a ${person.distance} metros`,
    )
    element?.addEventListener('keydown', (event) => {
      if (event.key === ' ') {
        event.preventDefault()
        emit('select', person.id)
      }
    })
    neighborMarkers.set(person.id, pin)
  }
  origin?.setLatLng(props.center)
  radiusCircle?.setLatLng(props.center).setRadius(props.radius)
  fitNeighbors()
  updateSelection()
}

function retryTiles() {
  tileFailed.value = false
  tiles?.redraw()
}

onMounted(() => {
  if (!container.value) return
  map = L.map(container.value, {
    zoomControl: false,
    scrollWheelZoom: false,
    minZoom: 13,
    maxZoom: 19,
    zoomSnap: 0.5,
    zoomAnimation: !reducedMotion(),
    fadeAnimation: !reducedMotion(),
    markerZoomAnimation: !reducedMotion(),
  }).setView(props.center, 16)
  map.attributionControl.setPrefix(false)
  radiusCircle = L.circle(props.center, {
    radius: props.radius,
    color: '#bd9bcf',
    weight: 1,
    opacity: 0.45,
    fillColor: '#c3a7d5',
    fillOpacity: 0.045,
    dashArray: '5 8',
    interactive: false,
  }).addTo(map)
  const originElement = document.createElement('div')
  originElement.className = 'mock-origin-dot'
  const originLabel = document.createElement('span')
  originLabel.textContent = 'Tu zona'
  originElement.append(originLabel)
  origin = L.marker(props.center, {
    icon: L.divIcon({
      className: 'mock-origin',
      html: originElement,
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    }),
    keyboard: false,
    interactive: false,
  }).addTo(map)
  markers = L.layerGroup().addTo(map)
  renderNeighbors()
  tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>',
  })
  tiles.on('tileload', () => {
    tileLoaded.value = true
  })
  tiles.on('tileerror', () => {
    tileFailed.value = true
  })
  tiles.addTo(map)
  resizeObserver = new ResizeObserver(() => {
    map?.invalidateSize({ pan: false })
    fitNeighbors()
  })
  resizeObserver.observe(container.value)
})

watch(() => [props.neighbors, props.center, props.radius], renderNeighbors)
watch(() => props.selectedId, updateSelection)
onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  map?.remove()
  neighborMarkers.clear()
})
</script>

<template>
  <div class="nearby-map">
    <div
      ref="map-container"
      class="neighbor-map-canvas"
      role="region"
      aria-label="Mapa de vecinos con ubicaciones de ejemplo"
      aria-describedby="neighbor-map-help"
    />
    <p id="neighbor-map-help" class="visually-hidden">
      Selecciona un rostro para conocer al vecino. Puedes mover el mapa con las
      flechas, ampliar con los controles o usar la vista de lista.
    </p>
    <span class="map-demo-label"
      ><AppIcon name="pin" :size="11" />Ubicaciones de ejemplo</span
    >
    <span v-if="!tileLoaded && !tileFailed" class="map-load-state" role="status"
      >Cargando las calles del barrio…</span
    >
    <div
      v-if="tileFailed && !tileLoaded"
      class="map-load-state map-load-error"
      role="status"
    >
      No pudimos cargar las calles.<button type="button" @click="retryTiles">
        Reintentar
      </button>
    </div>
    <div class="neighbor-map-controls" aria-label="Controles del mapa">
      <NButton circle aria-label="Ampliar mapa" @click="map?.zoomIn()"
        ><AppIcon name="plus" :size="20" /></NButton
      ><NButton circle aria-label="Reducir mapa" @click="map?.zoomOut()"
        ><AppIcon name="minus" :size="20" /></NButton
      ><NButton
        circle
        aria-label="Volver a la zona de ejemplo"
        @click="fitNeighbors"
        ><AppIcon name="locate" :size="20"
      /></NButton>
    </div>
  </div>
</template>
