import { computed, ref } from 'vue'

export const productCategories = [
  'Bicicletas',
  'Hogar',
  'Moda',
  'Tecnología',
  'Otros',
] as const
export const productConditions = [
  'Como nuevo',
  'Buen estado',
  'Con detalles',
] as const

export interface Listing {
  id: string
  title: string
  price: number
  category: string
  condition: string
  description: string
  image: string
  neighborhood: string
  seller: string
  avatar: string
  createdAt: string
  owned: boolean
  sold: boolean
}

export type NewListing = Pick<
  Listing,
  | 'title'
  | 'price'
  | 'category'
  | 'condition'
  | 'description'
  | 'image'
  | 'neighborhood'
>
const storageKey = 'masi-marketplace-v1'
const favoritesKey = 'masi-marketplace-favorites-v1'

const exampleListings: Listing[] = [
  {
    id: 'bicicleta-paseo',
    title: 'Bicicleta de paseo, lista para nuevas aventuras',
    price: 280,
    category: 'Bicicletas',
    condition: 'Buen estado',
    description:
      'Una bici cómoda para moverse por el barrio. Frenos y cambios funcionando, asiento ajustable y lista para usar. La vendo porque ya tengo otra. Podemos coordinar para que la veas en San Blas.',
    image: '/images/bicycle.jpg',
    neighborhood: 'San Blas',
    seller: 'Miguel',
    avatar: '/images/miguel.jpg',
    createdAt: '2026-11-05T15:00:00-05:00',
    owned: false,
    sold: false,
  },
  {
    id: 'ceramica-artesanal',
    title: 'Piezas de cerámica para darle vida a tu mesa',
    price: 65,
    category: 'Hogar',
    condition: 'Como nuevo',
    description:
      'Piezas de cerámica artesanal, cuidadas y sin rajaduras. Ideales para decorar tu cocina o compartir una mesa bonita. Se venden juntas y se recogen en el barrio.',
    image: '/images/ceramics.jpg',
    neighborhood: 'San Blas',
    seller: 'Lucía',
    avatar: '/images/lucia.jpg',
    createdAt: '2026-11-04T10:00:00-05:00',
    owned: false,
    sold: false,
  },
  {
    id: 'silla-lectura',
    title: 'Una silla cómoda para tu rincón favorito',
    price: 120,
    category: 'Hogar',
    condition: 'Buen estado',
    description:
      'Silla en buen estado, firme y muy cómoda. Tiene poco uso y la tapicería está limpia. Recogida por San Cristóbal, a coordinar.',
    image: '/images/chair.jpg',
    neighborhood: 'San Cristóbal',
    seller: 'Rosa',
    avatar: '/images/lucia.jpg',
    createdAt: '2026-11-03T10:00:00-05:00',
    owned: false,
    sold: false,
  },
]

function isListing(value: unknown): value is Listing {
  if (!value || typeof value !== 'object') return false
  const item = value as Record<string, unknown>
  return (
    [
      'id',
      'title',
      'category',
      'condition',
      'description',
      'image',
      'neighborhood',
      'seller',
      'avatar',
      'createdAt',
    ].every((key) => typeof item[key] === 'string') &&
    typeof item.price === 'number' &&
    Number.isFinite(item.price) &&
    item.price >= 0 &&
    item.owned === true &&
    typeof item.sold === 'boolean' &&
    typeof item.image === 'string' &&
    /^data:image\/(jpeg|png|webp);base64,/.test(item.image)
  )
}

function readListings(): Listing[] {
  try {
    const stored: unknown = JSON.parse(localStorage.getItem(storageKey) ?? '[]')
    return Array.isArray(stored) ? stored.filter(isListing) : []
  } catch {
    return []
  }
}

function readFavorites(): string[] {
  try {
    const stored: unknown = JSON.parse(
      localStorage.getItem(favoritesKey) ?? '[]',
    )
    return Array.isArray(stored)
      ? stored.filter((id): id is string => typeof id === 'string')
      : []
  } catch {
    return []
  }
}

const listings = ref<Listing[]>([...readListings(), ...exampleListings])
const favoriteIds = ref<string[]>(readFavorites())
const ownListings = computed(() =>
  listings.value.filter((listing) => listing.owned),
)

function commitListings(next: Listing[]) {
  localStorage.setItem(
    storageKey,
    JSON.stringify(next.filter((listing) => listing.owned)),
  )
  listings.value = next
}

function addListing(payload: NewListing) {
  const listing: Listing = {
    ...payload,
    title: payload.title.trim(),
    description: payload.description.trim(),
    id: crypto.randomUUID(),
    seller: 'Ana',
    avatar: '/images/ana.jpg',
    createdAt: new Date().toISOString(),
    owned: true,
    sold: false,
  }
  commitListings([listing, ...listings.value])
  return listing
}

function markSold(id: string) {
  commitListings(
    listings.value.map((listing) =>
      listing.id === id && listing.owned ? { ...listing, sold: true } : listing,
    ),
  )
}

function toggleFavorite(id: string) {
  const next = favoriteIds.value.includes(id)
    ? favoriteIds.value.filter((value) => value !== id)
    : [...favoriteIds.value, id]
  localStorage.setItem(favoritesKey, JSON.stringify(next))
  favoriteIds.value = next
}

function removeListing(id: string) {
  commitListings(
    listings.value.filter((listing) => listing.id !== id || !listing.owned),
  )
}

export function useMarketplace() {
  return {
    listings,
    ownListings,
    favoriteIds,
    addListing,
    markSold,
    removeListing,
    toggleFavorite,
  }
}

export function formatPrice(price: number) {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    maximumFractionDigits: 2,
  }).format(price)
}
