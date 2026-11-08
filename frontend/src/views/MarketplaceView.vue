<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { NButton, NEmpty, NInput, NSelect, useMessage } from 'naive-ui'
import { RouterLink, useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import LocalPageHeading from '../components/layout/LocalPageHeading.vue'
import MarketplaceHighlights from '../components/marketplace/MarketplaceHighlights.vue'
import ProductCard from '../components/marketplace/ProductCard.vue'
import type { Panel } from '../components/community/CommunityPanels.vue'
import { useCommunity } from '../composables/useCommunity'
import { useMarketplace, type Listing, type MarketScope, type MarketView } from '../composables/useMarketplace'
import { currentUser } from '../composables/useAuth'

const { neighborhood } = useCommunity()
const message = useMessage()
const route = useRoute()
const emit = defineEmits<{ openPanel: [panel: Panel] }>()
const search = shallowRef('')
const searchTheme = {
  borderRadius: '8px',
  fontSizeLarge: '16px',
  heightLarge: '44px',
  placeholderColor: 'var(--muted)',
}
const areaTheme = { peers: { InternalSelection: { borderRadius: '8px', heightLarge: '44px' } } }
const area = shallowRef<MarketScope>('nearby')
const showMine = computed(() => route.name === 'market-mine')
const showFavorites = computed(() => route.name === 'market-saved')
const view = computed<MarketView>(() => showMine.value ? 'mine' : showFavorites.value ? 'saved' : 'all')
const filters = computed(() => ({ view: view.value, scope: area.value, q: search.value.trim() }))
const { listings, total, nextCursor, loading, error, saving, recentlyViewed, load, toggleFavorite } = useMarketplace(filters)
const hasFilters = computed(
  () => Boolean(search.value.trim()) || (!showMine.value && area.value !== 'nearby'),
)
const title = computed(() => {
  if (showMine.value) return 'Mis anuncios'
  if (showFavorites.value) return 'Productos guardados'
  return 'El mercadito'
})
const areaOptions = computed(() => [
  { label: 'Todo mi distrito', value: 'nearby' },
  { label: `Solo ${currentUser.value?.neighborhood || 'mi barrio'}`, value: 'neighborhood' },
])
const emptyTitle = computed(() => {
  if (hasFilters.value) return 'No encontramos anuncios con esos filtros'
  if (showMine.value) return 'Tu primer anuncio empieza aquí'
  if (showFavorites.value) return 'Aún no tienes productos guardados'
  return 'Todavía no hay productos por aquí'
})
const featuredListing = computed(() =>
  view.value === 'all' && !hasFilters.value ? listings.value[0] : undefined,
)
const latest = computed(() => listings.value.slice(1))

async function save(listing: Listing) {
  try {
    await toggleFavorite(listing)
  } catch (e) {
    message.error(e instanceof Error ? e.message : 'No pudimos actualizar tus guardados.')
  }
}

function resetFilters() {
  search.value = ''
  area.value = 'nearby'
}
</script>

<template>
  <section class="local-page marketplace-page" aria-labelledby="market-title">
    <header class="marketplace-intro">
      <LocalPageHeading
        :title="title"
        title-id="market-title"
        :neighborhood="neighborhood"
        @change-neighborhood="emit('openPanel', 'neighborhood')"
      >
        <RouterLink class="marketplace-sell" :to="{ name: 'create-product' }">
          <AppIcon name="plus" :size="17" /> Vender
        </RouterLink>
      </LocalPageHeading>
      <div class="marketplace-search-row">
        <NInput
          v-model:value="search"
          class="marketplace-search"
          size="large"
          :theme-overrides="searchTheme"
          clearable
          :maxlength="100"
          placeholder="Buscar…"
          :input-props="{ 'aria-label': 'Buscar productos', inputmode: 'search' }"
        >
          <template #prefix><AppIcon name="search" :size="17" /></template>
        </NInput>
        <NSelect
          v-if="!showMine"
          v-model:value="area"
          :options="areaOptions"
          size="large"
          :theme-overrides="areaTheme"
          aria-label="Filtrar por zona"
          class="area-select"
        />
      </div>
      <nav class="marketplace-tabs" aria-label="Vistas del mercadito">
        <RouterLink class="marketplace-tab" :to="{ name: 'market' }" replace>
          Todos
        </RouterLink>
        <RouterLink class="marketplace-tab" :to="{ name: 'market-mine' }" replace>
          Mis anuncios
        </RouterLink>
        <RouterLink class="marketplace-tab" :to="{ name: 'market-saved' }" replace>
          Guardados
        </RouterLink>
      </nav>
    </header>
    <p v-if="loading && !listings.length" class="empty-market" role="status">Cargando anuncios…</p>
    <MarketplaceHighlights
      v-else-if="featuredListing"
      :featured="featuredListing"
      :latest="latest"
      :recent="recentlyViewed"
      :saving="saving"
      @toggle-favorite="save"
    />
    <template v-else-if="listings.length || !error">
      <div class="marketplace-results">
        <h2 id="market-results-title">Resultados</h2>
        <span role="status">
          {{ total }}
          {{ total === 1 ? 'anuncio' : 'anuncios' }}
        </span>
      </div>
      <ul v-if="listings.length" class="product-feed" role="list" aria-labelledby="market-results-title">
        <ProductCard
          v-for="listing in listings"
          :key="listing.id"
          :listing="listing"
          :saving="saving.has(listing.id)"
          @toggle-favorite="save"
        />
      </ul>
      <div v-else class="empty-market">
        <NEmpty :description="emptyTitle">
          <template #extra>
            <NButton v-if="hasFilters" @click="resetFilters">Limpiar filtros</NButton>
            <RouterLink v-else-if="showFavorites" class="text-link" :to="{ name: 'market' }" replace>
              Ver todos los anuncios <AppIcon name="arrow" :size="15" />
            </RouterLink>
            <RouterLink v-else class="marketplace-sell" :to="{ name: 'create-product' }">
              Publicar un producto
            </RouterLink>
          </template>
        </NEmpty>
      </div>
    </template>
    <div v-if="error || nextCursor" class="marketplace-pagination">
      <p v-if="error" role="alert">{{ error }}</p>
      <NButton :loading="loading" @click="load(Boolean(nextCursor))">
        {{ error ? 'Reintentar' : 'Cargar más anuncios' }}
      </NButton>
    </div>
  </section>
</template>

<style scoped>
.marketplace-page {
  padding-inline: var(--local-gutter);
  border-top: 1px solid var(--line);
}
.marketplace-intro {
  padding-top: 14px;
}
.marketplace-intro :deep(#market-title) {
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: -0.035em;
  line-height: 1.08;
}
.marketplace-sell {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding-inline: 13px;
  border-radius: 11px;
  background: var(--coral);
  color: var(--surface);
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
}
.marketplace-sell:hover {
  background: var(--local-accent-text);
}
.marketplace-search-row {
  display: flex;
  gap: 8px;
  margin-block: 2px 8px;
}
.marketplace-search {
  flex: 1;
  min-width: 0;
}
.marketplace-search :deep(.n-input__prefix) {
  color: var(--muted);
}
.area-select {
  flex: 0 0 min(46%, 148px);
  min-width: 0;
}
.marketplace-tabs {
  display: flex;
  flex-wrap: wrap;
  column-gap: clamp(14px, 5vw, 24px);
  border-bottom: 1px solid var(--line);
}
.marketplace-tab {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  margin-bottom: -1px;
  padding-inline: 2px;
  border-bottom: 2px solid transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
}
.marketplace-tab:hover,
.marketplace-tab[aria-current='page'] {
  color: var(--text);
}
.marketplace-tab[aria-current='page'] {
  border-color: var(--coral);
}
.marketplace-results {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-block: 17px 10px;
}
.marketplace-results h2 {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 900;
}
.marketplace-results > span {
  color: var(--muted);
  font-size: 12px;
  text-align: end;
}
.product-feed {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.marketplace-pagination {
  margin: 16px 0 24px;
  text-align: start;
}
@media (max-width: 359px) {
  .marketplace-intro :deep(#market-title) {
    font-size: 1.625rem;
  }
}
@media (max-width: 319px) {
  .product-feed {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
