import { computed, shallowRef, type Ref } from 'vue'

export interface Coordinates {
  lat: number
  lng: number
}
export type NeighborFilter = 'all' | 'help' | 'activities'
interface NeighborExample {
  id: string
  name: string
  avatar: string
  offset: Coordinates
  bio: string
  skills: string[]
  interests: string[]
  availability: string
  canHelp: boolean
  openToActivities: boolean
  joined: string
}
export interface NearbyNeighbor extends Omit<NeighborExample, 'offset'> {
  coordinates: Coordinates
  neighborhood: string
  distance: number
}

const defaultCenter = { lat: -13.5134, lng: -71.9779 }
const centers: Record<string, Coordinates> = {
  'San Blas': defaultCenter,
  'San Cristóbal': { lat: -13.5123, lng: -71.9824 },
  'Santa Ana': { lat: -13.5121, lng: -71.9868 },
}

const examples: NeighborExample[] = [
  {
    id: 'miguel',
    name: 'Miguel',
    avatar: '/images/miguel.jpg',
    offset: { lat: 0.0014, lng: 0.0014 },
    bio: 'Me acabo de mudar con mi familia. Me gusta arreglar cosas y conocer a las personas que viven cerquita.',
    skills: ['Reparaciones', 'Bicicletas'],
    interests: ['Caminatas', 'Huerto vecinal'],
    availability: 'Hoy por la tarde',
    canHelp: true,
    openToActivities: true,
    joined: 'Nuevo en el barrio',
  },
  {
    id: 'lucia',
    name: 'Lucía',
    avatar: '/images/lucia.jpg',
    offset: { lat: 0.0016, lng: -0.0019 },
    bio: 'Siempre hay espacio para una buena conversación. Me encanta reunir a los vecinos y cuidar nuestras plantas.',
    skills: ['Jardinería', 'Cuidado de plantas'],
    interests: ['Huerto vecinal', 'Cocina'],
    availability: 'Los fines de semana',
    canHelp: true,
    openToActivities: true,
    joined: 'Vecina desde hace 2 años',
  },
  {
    id: 'carla',
    name: 'Carla',
    avatar: '/images/carla.jpg',
    offset: { lat: -0.0016, lng: 0.0029 },
    bio: 'Trabajo desde casa y disfruto pasear con mi perrita. Feliz de conocer a más personas del barrio.',
    skills: ['Mascotas', 'Paseos de perros'],
    interests: ['Caminatas', 'Mascotas'],
    availability: 'Por las mañanas',
    canHelp: true,
    openToActivities: false,
    joined: 'Vecina desde hace 8 meses',
  },
  {
    id: 'diego',
    name: 'Diego',
    avatar: '/images/diego.jpg',
    offset: { lat: -0.0023, lng: -0.0026 },
    bio: 'Me gusta compartir lo que sé de tecnología y salir a jugar una pichanga con los vecinos.',
    skills: ['Tecnología', 'Computadoras'],
    interests: ['Deporte', 'Caminatas'],
    availability: 'Después de las 6 p. m.',
    canHelp: true,
    openToActivities: false,
    joined: 'Vecino desde hace 1 año',
  },
  {
    id: 'elena',
    name: 'Elena',
    avatar: '/images/elena.jpg',
    offset: { lat: -0.0038, lng: 0.0011 },
    bio: 'Me encanta cocinar en compañía. Quisiera organizar un intercambio de recetas entre vecinos.',
    skills: ['Cocina', 'Repostería'],
    interests: ['Cocina', 'Talleres'],
    availability: 'Los sábados',
    canHelp: false,
    openToActivities: true,
    joined: 'Vecina desde hace 6 meses',
  },
  {
    id: 'rosa',
    name: 'Rosa',
    avatar: '/images/rosa.jpg',
    offset: { lat: 0.0041, lng: 0.0006 },
    bio: 'Tengo un pequeño jardín y muchas ganas de compartir semillas. Siempre es bonito hacer algo juntos.',
    skills: ['Jardinería', 'Costura'],
    interests: ['Huerto vecinal', 'Talleres'],
    availability: 'Por las tardes',
    canHelp: true,
    openToActivities: true,
    joined: 'Vecina desde hace 4 años',
  },
  {
    id: 'tomas',
    name: 'Tomás',
    avatar: '/images/tomas.jpg',
    offset: { lat: 0.0005, lng: -0.0064 },
    bio: 'Soy músico y me gustaría encontrar vecinos para tocar un rato o armar una actividad en la plazoleta.',
    skills: ['Música', 'Guitarra'],
    interests: ['Música', 'Talleres'],
    availability: 'Los domingos',
    canHelp: false,
    openToActivities: true,
    joined: 'Nuevo en el barrio',
  },
]

function searchable(text: string) {
  return text
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase('es')
}

export function useNearbyNeighbors(neighborhood: Ref<string>) {
  const search = shallowRef('')
  const radius = shallowRef(600)
  const filter = shallowRef<NeighborFilter>('all')
  const selectedId = shallowRef('miguel')
  const mode = shallowRef<'map' | 'list'>('map')
  const center = computed(() => centers[neighborhood.value] ?? defaultCenter)
  const neighbors = computed<NearbyNeighbor[]>(() =>
    examples
      .map((person) => {
        const { offset, ...profile } = person
        const northMeters = offset.lat * 111195
        const eastMeters =
          offset.lng * 111195 * Math.cos((center.value.lat * Math.PI) / 180)
        return {
          ...profile,
          coordinates: {
            lat: center.value.lat + offset.lat,
            lng: center.value.lng + offset.lng,
          },
          neighborhood: neighborhood.value,
          distance: Math.round(Math.hypot(northMeters, eastMeters) / 10) * 10,
        }
      })
      .sort((first, second) => first.distance - second.distance),
  )
  const filteredNeighbors = computed(() => {
    const term = searchable(search.value.trim())
    return neighbors.value.filter((person) => {
      const matchesTerm = searchable(
        `${person.name} ${person.skills.join(' ')} ${person.interests.join(' ')}`,
      ).includes(term)
      const matchesFilter =
        filter.value === 'all' ||
        (filter.value === 'help' ? person.canHelp : person.openToActivities)
      return person.distance <= radius.value && matchesTerm && matchesFilter
    })
  })
  const selectedNeighbor = computed(
    () =>
      filteredNeighbors.value.find(
        (person) => person.id === selectedId.value,
      ) ?? filteredNeighbors.value[0],
  )

  return {
    search,
    radius,
    filter,
    selectedId,
    mode,
    center,
    filteredNeighbors,
    selectedNeighbor,
  }
}
