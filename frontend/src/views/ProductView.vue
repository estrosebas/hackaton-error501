<script setup lang="ts">
import { computed } from 'vue'
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
import { formatPrice, useMarketplace } from '../composables/useMarketplace'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const { listings, favoriteIds, toggleFavorite, markSold, removeListing } =
  useMarketplace()
const listing = computed(() =>
  listings.value.find((item) => item.id === route.params.id),
)
const saved = computed(() =>
  listing.value ? favoriteIds.value.includes(listing.value.id) : false,
)

function save() {
  if (!listing.value) return
  try {
    toggleFavorite(listing.value.id)
  } catch {
    message.error(
      'No pudimos guardar el producto. Revisa el almacenamiento de tu navegador.',
    )
  }
}

function completeSale() {
  if (!listing.value?.owned) return
  try {
    markSold(listing.value.id)
    message.success('Tu anuncio quedó marcado como vendido')
  } catch {
    message.error('No pudimos actualizar el anuncio. Inténtalo de nuevo.')
  }
}

function deleteListing() {
  if (!listing.value?.owned) return
  try {
    removeListing(listing.value.id)
    message.success('Anuncio eliminado')
    router.replace({ name: 'market', query: { vista: 'mios' } })
  } catch {
    message.error('No pudimos eliminar el anuncio. Inténtalo de nuevo.')
  }
}
</script>

<template>
  <section class="product-detail-page">
    <div class="product-back-row">
      <RouterLink to="/mercadito" class="back-link"
        ><AppIcon name="arrow" :size="18" />Volver al mercadito</RouterLink
      ><NButton
        v-if="listing && !listing.owned"
        circle
        quaternary
        :aria-label="
          saved ? 'Quitar producto de guardados' : 'Guardar producto'
        "
        :aria-pressed="saved"
        @click="save"
        ><AppIcon name="bookmark" :size="21"
      /></NButton>
    </div>
    <template v-if="listing">
      <img
        class="product-hero-image"
        :src="listing.image"
        :alt="listing.title"
        width="720"
        height="520"
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
          <NAvatar
            round
            :size="46"
            :src="listing.avatar"
            :img-props="{ alt: '' }"
          />
          <div>
            <strong>{{ listing.owned ? 'Ana · Tú' : listing.seller }}</strong>
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
          :disabled="listing.sold"
          @click="completeSale"
          >{{
            listing.sold ? 'Producto vendido' : 'Marcar como vendido'
          }}</NButton
        ><NButton
          v-else
          type="primary"
          block
          size="large"
          :aria-pressed="saved"
          @click="save"
          ><template #icon
            ><AppIcon
              :name="saved ? 'check' : 'bookmark'"
              :size="19" /></template
          >{{
            saved
              ? 'Guardado en tus favoritos'
              : 'Me interesa · Guardar producto'
          }}</NButton
        >
        <div v-if="listing.owned" class="delete-listing-action">
          <NPopconfirm
            positive-text="Eliminar"
            negative-text="Conservar"
            @positive-click="deleteListing"
          >
            <template #trigger
              ><NButton text type="error">Eliminar anuncio</NButton></template
            >
            ¿Quieres eliminar este anuncio de tu mercadito?
          </NPopconfirm>
        </div>
        <p class="marketplace-demo-note">
          {{
            listing.owned
              ? 'Tu anuncio está guardado en este dispositivo.'
              : 'Producto de ejemplo para conocer el mercadito.'
          }}
        </p>
      </div>
    </template>
    <div v-else class="empty-market">
      <NEmpty description="Este anuncio ya no está disponible"
        ><template #extra
          ><RouterLink to="/mercadito" class="text-link"
            >Explorar el mercadito
            <AppIcon name="arrow" :size="16" /></RouterLink></template
      ></NEmpty>
    </div>
  </section>
</template>
