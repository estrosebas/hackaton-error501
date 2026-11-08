import { computed, onScopeDispose, shallowReactive, shallowRef, watch, type Ref } from 'vue'
import { api } from '../services/api'
import { currentUser } from './useAuth'

export const productCategories = ['Bicicletas', 'Hogar', 'Moda', 'Tecnología', 'Otros'] as const
export const productConditions = ['Como nuevo', 'Buen estado', 'Con detalles'] as const

export interface Listing {
  id: string
  title: string
  price: number
  category: string
  condition: string
  description: string
  neighborhood: string
  seller: { id: number; name: string }
  createdAt: string
  owned: boolean
  sold: boolean
  saved: boolean
}

export type NewListing = Pick<Listing, 'title' | 'price' | 'category' | 'condition' | 'description'> & {
  photo: File
  requestId: string
}

export type MarketView = 'all' | 'mine' | 'saved'
export type MarketScope = 'nearby' | 'neighborhood'
interface ListingFilters {
  view: MarketView
  scope?: MarketScope
  q?: string
}
interface ListingPage {
  items: Listing[]
  nextCursor: string | null
  total: number
}

export function fetchListings(filters: ListingFilters, signal: AbortSignal, cursor?: string) {
  const params = new URLSearchParams({ view: filters.view })
  if (filters.scope) params.set('scope', filters.scope)
  if (filters.q) params.set('q', filters.q)
  if (cursor) params.set('cursor', cursor)
  return api<ListingPage>(`/listings?${params}`, { signal })
}

export function fetchListing(id: string, signal: AbortSignal) {
  return api<Listing>(`/listings/${encodeURIComponent(id)}`, { signal })
}

export function addListing(listing: NewListing) {
  const body = new FormData()
  body.set('requestId', listing.requestId)
  body.set('title', listing.title.trim())
  body.set('price', listing.price.toFixed(2))
  body.set('category', listing.category)
  body.set('condition', listing.condition)
  body.set('description', listing.description.trim())
  body.set('photo', listing.photo)
  return api<Listing>('/listings', { method: 'POST', body })
}

export function markSold(id: string) {
  return api<Listing>(`/listings/${id}`, { method: 'PATCH', body: JSON.stringify({ sold: true }) })
}

export function removeListing(id: string) {
  return api<void>(`/listings/${id}`, { method: 'DELETE' })
}

export async function setFavorite(id: string, saved: boolean) {
  const result = await api<{ saved: boolean }>(`/listings/${id}/favorite`, {
    method: 'PUT', body: JSON.stringify({ saved }),
  })
  return result.saved
}

const recentIds = shallowRef<string[]>([])
watch(() => currentUser.value?.id, () => { recentIds.value = [] }, { flush: 'sync' })

export function rememberListing(id: string) {
  recentIds.value = [id, ...recentIds.value.filter((value) => value !== id)].slice(0, 6)
}

export function useMarketplace(filters: Readonly<Ref<ListingFilters>>) {
  const listings = shallowRef<Listing[]>([])
  const total = shallowRef(0)
  const nextCursor = shallowRef<string | null>(null)
  const loading = shallowRef(true)
  const error = shallowRef('')
  const saving = shallowReactive(new Set<string>())
  let controller: AbortController | undefined
  let searchTimer: ReturnType<typeof setTimeout> | undefined

  const recentlyViewed = computed(() => recentIds.value.flatMap((id) => {
    const listing = listings.value.find((item) => item.id === id)
    return listing ? [listing] : []
  }))

  async function load(append = false) {
    clearTimeout(searchTimer)
    const cursor = append ? nextCursor.value : null
    if (append && (loading.value || !cursor)) return
    controller?.abort()
    const request = new AbortController()
    controller = request
    loading.value = true
    error.value = ''
    try {
      const page = await fetchListings(filters.value, request.signal, cursor ?? undefined)
      if (request.signal.aborted) return
      listings.value = append ? [...listings.value, ...page.items] : page.items
      nextCursor.value = page.nextCursor
      total.value = page.total
    } catch (err) {
      if (!request.signal.aborted) error.value = err instanceof Error ? err.message : 'No pudimos cargar los anuncios.'
    } finally {
      if (!request.signal.aborted) loading.value = false
    }
  }

  watch(
    [filters, () => currentUser.value?.id, () => currentUser.value?.ubigeo, () => currentUser.value?.neighborhood],
    ([next, userId], [previous]) => {
      controller?.abort()
      clearTimeout(searchTimer)
      listings.value = []
      nextCursor.value = null
      total.value = 0
      loading.value = Boolean(userId)
      error.value = ''
      if (!userId) return
      if (previous && previous.q !== next.q) {
        searchTimer = setTimeout(() => void load(), 220)
      } else {
        void load()
      }
    },
    { immediate: true },
  )

  async function toggleFavorite(listing: Listing) {
    if (saving.has(listing.id)) return
    const userId = currentUser.value?.id
    saving.add(listing.id)
    try {
      const saved = await setFavorite(listing.id, !listing.saved)
      if (currentUser.value?.id !== userId) return
      if (loading.value) {
        await load()
        return
      }
      if (filters.value.view === 'saved' && !saved && listings.value.some((item) => item.id === listing.id)) {
        listings.value = listings.value.filter((item) => item.id !== listing.id)
        total.value = Math.max(0, total.value - 1)
      } else {
        listings.value = listings.value.map((item) => item.id === listing.id ? { ...item, saved } : item)
      }
    } finally {
      saving.delete(listing.id)
    }
  }

  onScopeDispose(() => {
    clearTimeout(searchTimer)
    controller?.abort()
  })

  return { listings, total, nextCursor, loading, error, saving, recentlyViewed, load, toggleFavorite }
}

export function formatPrice(price: number, { compact = false } = {}) {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: compact && Number.isInteger(price) ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(price)
}
