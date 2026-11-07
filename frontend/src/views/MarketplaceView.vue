<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { NButton, NEmpty, NInput, NSelect } from 'naive-ui'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import ProductCard from '../components/marketplace/ProductCard.vue'
import { useCommunity } from '../composables/useCommunity'
import { useMarketplace } from '../composables/useMarketplace'

const { neighborhood } = useCommunity()
const { listings, ownListings, favoriteIds } = useMarketplace()
const router = useRouter()
const route = useRoute()
const search = shallowRef('')
const area = shallowRef('nearby')
const showMine = computed(() => route.query.vista === 'mios')
const showFavorites = computed(() => route.query.vista === 'guardados')
const areaOptions = computed(() => [
  { label: 'Mi barrio y alrededores', value: 'nearby' },
  { label: `Solo ${neighborhood.value}`, value: 'neighborhood' },
])
const filteredListings = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('es')
  return listings.value.filter((listing) => {
    const matchesOwner = !showMine.value || listing.owned
    const matchesFavorite =
      !showFavorites.value || favoriteIds.value.includes(listing.id)
    const matchesArea =
      showMine.value ||
      area.value === 'nearby' ||
      listing.neighborhood === neighborhood.value
    const matchesTerm =
      !term ||
      `${listing.title} ${listing.description} ${listing.category}`
        .toLocaleLowerCase('es')
        .includes(term)
    return (
      matchesOwner &&
      matchesFavorite &&
      matchesArea &&
      matchesTerm &&
      (showMine.value || !listing.sold)
    )
  })
})

function toggleMine() {
  router.replace({
    name: 'market',
    query: showMine.value ? {} : { vista: 'mios' },
  })
}
</script>

<template>
  <section class="marketplace-page" aria-labelledby="market-title">
    <div class="marketplace-intro">
      <div class="page-heading">
        <div>
          <h1 id="market-title">
            {{ showFavorites ? 'Tus productos guardados' : 'El mercadito' }}
          </h1>
          <p>Lo que buscas puede estar cerquita.</p>
        </div>
        <span class="page-heading-icon"
          ><AppIcon name="shop" :size="25"
        /></span>
      </div>
      <div class="marketplace-filters">
        <NSelect
          v-model:value="area"
          :options="areaOptions"
          aria-label="Filtrar por zona"
          class="area-select"
        />
        <NButton
          :type="showMine ? 'primary' : 'default'"
          :aria-pressed="showMine"
          @click="toggleMine"
          >Mis anuncios
          <span class="listing-count">{{ ownListings.length }}</span></NButton
        >
      </div>
      <div class="marketplace-actions">
        <NButton
          type="primary"
          size="large"
          @click="router.push({ name: 'create', query: { tipo: 'producto' } })"
          ><template #icon><AppIcon name="plus" :size="20" /></template>Vender
          algo</NButton
        ><span
          >{{ filteredListings.length }}
          {{ filteredListings.length === 1 ? 'anuncio' : 'anuncios' }}
          {{
            showMine
              ? filteredListings.length === 1
                ? 'tuyo'
                : 'tuyos'
              : 'cerca de ti'
          }}</span
        >
      </div>
      <NInput
        v-model:value="search"
        clearable
        placeholder="¿Qué estás buscando?"
        :input-props="{ 'aria-label': 'Buscar productos' }"
        ><template #prefix><AppIcon name="search" :size="18" /></template
      ></NInput>
      <NButton
        v-if="showFavorites"
        text
        class="return-to-market"
        @click="router.replace({ name: 'market' })"
        >Ver todos los anuncios <AppIcon name="arrow" :size="16"
      /></NButton>
    </div>
    <div class="product-feed" aria-live="polite">
      <ProductCard
        v-for="listing in filteredListings"
        :key="listing.id"
        :listing="listing"
      />
      <div v-if="!filteredListings.length" class="empty-market">
        <NEmpty
          :description="
            showMine
              ? 'Tu primer anuncio empieza aquí'
              : 'Todavía no hay productos por aquí'
          "
          ><template #extra
            ><p class="sheet-hint">
              {{
                search
                  ? 'Prueba con otra búsqueda o amplía la zona.'
                  : 'Dale una nueva vida a eso que ya no usas.'
              }}
            </p>
            <NButton
              v-if="showMine"
              type="primary"
              @click="
                router.push({ name: 'create', query: { tipo: 'producto' } })
              "
              >Publicar un producto</NButton
            ></template
          ></NEmpty
        >
      </div>
    </div>
    <p class="marketplace-demo-note">
      Anuncios de ejemplo · Tus anuncios se guardan en este dispositivo.
    </p>
  </section>
</template>
