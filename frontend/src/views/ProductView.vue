<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import {
  NAvatar,
  NButton,
  NEmpty,
  NPopconfirm,
  NTag,
  useMessage,
} from 'naive-ui'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import CurrentUserAvatar from '../components/auth/CurrentUserAvatar.vue'
import ListingPhoto from '../components/marketplace/ListingPhoto.vue'
import { fetchListing, formatPrice, markSold, rememberListing, removeListing, setFavorite, type Listing } from '../composables/useMarketplace'
import { ApiError } from '../services/api'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const listing = shallowRef<Listing>()
const loading = shallowRef(true)
const busy = shallowRef(false)
const error = shallowRef('')
const attempt = shallowRef(0)
const listingId = computed(() => route.name === 'product' ? String(route.params.id) : '')

watch([listingId, attempt], async ([id], _, onCleanup) => {
  const request = new AbortController()
  onCleanup(() => request.abort())
  listing.value = undefined
  if (!id) return
  loading.value = true
  error.value = ''
  try {
    const result = await fetchListing(id, request.signal)
    if (request.signal.aborted) return
    listing.value = result
    if (!result.sold) rememberListing(result.id)
  } catch (e) {
    if (!request.signal.aborted && !(e instanceof ApiError && e.status === 404)) {
      error.value = e instanceof Error ? e.message : 'No pudimos cargar el anuncio.'
    }
  } finally {
    if (!request.signal.aborted) loading.value = false
  }
}, { immediate: true })

async function updateListing(action: (item: Listing) => Promise<void>, fallback: string) {
  const item = listing.value
  if (!item || busy.value) return
  busy.value = true
  try {
    await action(item)
  } catch (e) {
    message.error(e instanceof Error ? e.message : fallback)
  } finally {
    busy.value = false
  }
}

function save() {
  return updateListing(async (item) => {
    const saved = await setFavorite(item.id, !item.saved)
    if (listing.value === item) listing.value = { ...item, saved }
  }, 'No pudimos actualizar tus guardados.')
}

function completeSale() {
  return updateListing(async (item) => {
    const updated = await markSold(item.id)
    if (listing.value !== item) return
    listing.value = updated
    message.success('Tu anuncio quedó marcado como vendido')
  }, 'No pudimos actualizar el anuncio.')
}

function deleteListing() {
  return updateListing(async (item) => {
    await removeListing(item.id)
    if (listing.value !== item) return
    message.success('Anuncio eliminado')
    await router.replace({ name: 'market-mine' })
  }, 'No pudimos eliminar el anuncio.')
}
</script>

<template>
  <section class="product-detail-page">
    <div class="product-back-row">
      <RouterLink :to="{ name: 'market' }" class="back-link"
        ><AppIcon name="arrow" :size="18" />Volver al mercadito</RouterLink
      ><NButton
        v-if="listing && !listing.owned"
        circle
        quaternary
        :aria-label="
          listing.saved ? 'Quitar producto de guardados' : 'Guardar producto'
        "
        :aria-pressed="listing.saved"
        :disabled="busy"
        @click="save"
        ><AppIcon name="bookmark" :size="21"
      /></NButton>
    </div>
    <p v-if="loading" class="empty-market" role="status">Cargando anuncio…</p>
    <template v-else-if="listing">
      <ListingPhoto
        :key="listing.id"
        class="product-hero-image"
        :listing-id="listing.id"
        :alt="listing.title"
        eager
      />
      <div class="product-detail-content">
        <div class="product-detail-price">
          <strong>{{ formatPrice(listing.price) }}</strong
          ><NTag
            round
            :bordered="false"
            :type="listing.sold ? 'default' : 'success'"
            >{{ listing.sold ? 'Vendido' : listing.condition }}</NTag
          >
        </div>
        <h1>{{ listing.title }}</h1>
        <p class="detail-meta">
          <AppIcon name="pin" :size="17" />{{ listing.neighborhood }} · Recogida
          en el barrio
        </p>
        <div class="product-description">
          <h2>Sobre el producto</h2>
          <p>{{ listing.description }}</p>
        </div>
        <div class="seller-profile">
          <CurrentUserAvatar v-if="listing.owned" :size="46" />
          <NAvatar
            v-else
            round
            :size="46"
            aria-hidden="true"
          >{{ listing.seller.name.trim().charAt(0) }}</NAvatar>
          <div>
            <strong>{{ listing.owned ? 'Tú' : listing.seller.name }}</strong>
            <p>Vecino de {{ listing.neighborhood }}</p>
          </div>
          <span class="seller-label">{{
            listing.owned ? 'Tu anuncio' : 'Vende'
          }}</span>
        </div>
        <NButton
          v-if="listing.owned"
          type="primary"
          block
          size="large"
          :disabled="listing.sold || busy"
          @click="completeSale"
          >{{
            listing.sold ? 'Producto vendido' : 'Marcar como vendido'
          }}</NButton
        >
        <RouterLink v-else :to="{ name: 'messages', query: { to: listing.seller.id } }" custom v-slot="{ href, navigate }">
          <NButton tag="a" :href="href" type="primary" block size="large" @click="navigate">
            <template #icon><AppIcon name="send" :size="19" /></template>
            Contactar al vendedor
          </NButton>
        </RouterLink>
        <div v-if="listing.owned" class="delete-listing-action">
          <NPopconfirm
            positive-text="Eliminar"
            negative-text="Conservar"
            @positive-click="deleteListing"
          >
            <template #trigger
              ><NButton text type="error" :disabled="busy">Eliminar anuncio</NButton></template
            >
            ¿Quieres eliminar este anuncio de tu mercadito?
          </NPopconfirm>
        </div>
      </div>
    </template>
    <div v-else-if="error" class="empty-market">
      <p role="alert">{{ error }}</p>
      <NButton @click="attempt++">Reintentar</NButton>
    </div>
    <div v-else class="empty-market">
      <NEmpty description="Este anuncio ya no está disponible"
        ><template #extra
          ><RouterLink :to="{ name: 'market' }" class="text-link"
            >Explorar el mercadito
            <AppIcon name="arrow" :size="16" /></RouterLink></template
      ></NEmpty>
    </div>
  </section>
</template>
