<script setup lang="ts">
import { computed } from 'vue'
import type { Listing } from '../../composables/useMarketplace'
import ProductCard from './ProductCard.vue'

const props = defineProps<{
  featured: Listing
  latest: Listing[]
  recent: Listing[]
  saving: ReadonlySet<string>
}>()
const emit = defineEmits<{ toggleFavorite: [listing: Listing] }>()

const shelves = computed(() => [
  { id: 'market-latest', title: 'Recién publicados', listings: props.latest },
  { id: 'market-recent', title: 'Vistos recientemente', listings: props.recent },
].filter((shelf) => shelf.listings.length > 0))
</script>

<template>
  <div class="marketplace-highlights">
    <section aria-labelledby="market-featured">
      <h2 id="market-featured">Anuncio destacado</h2>
      <ul class="featured-list" role="list">
        <ProductCard :listing="featured" featured />
      </ul>
    </section>
    <section v-for="shelf in shelves" :key="shelf.id" :aria-labelledby="shelf.id">
      <h2 :id="shelf.id">{{ shelf.title }}</h2>
      <ul class="market-shelf" role="list" tabindex="0" :aria-labelledby="shelf.id">
        <ProductCard
          v-for="listing in shelf.listings"
          :key="listing.id"
          :listing="listing"
          :saving="saving.has(listing.id)"
          @toggle-favorite="emit('toggleFavorite', $event)"
        />
      </ul>
    </section>
  </div>
</template>

<style scoped>
.marketplace-highlights {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 20px;
  padding-top: 17px;
}
.marketplace-highlights h2 {
  margin: 0 0 9px;
  font-size: 1.0625rem;
  font-weight: 900;
}
.featured-list,
.market-shelf {
  margin: 0;
  padding: 0;
  list-style: none;
}
.market-shelf {
  display: flex;
  gap: 10px;
  margin-inline: calc(-1 * var(--local-gutter));
  padding: 4px var(--local-gutter) 8px;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scroll-padding-inline: var(--local-gutter);
  scroll-snap-type: x mandatory;
  scrollbar-width: thin;
  scrollbar-color: var(--line) transparent;
}
.market-shelf:focus-visible {
  outline: 2px solid var(--coral);
  outline-offset: -2px;
}
.market-shelf > li {
  flex: 0 0 min(74%, 232px);
  scroll-snap-align: start;
}
</style>
