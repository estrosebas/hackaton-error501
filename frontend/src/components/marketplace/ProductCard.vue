<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { formatPrice, type Listing } from '../../composables/useMarketplace'
import AppIcon from '../AppIcon.vue'
import ListingPhoto from './ListingPhoto.vue'

const props = defineProps<{ listing: Listing; featured?: boolean; saving?: boolean }>()
const emit = defineEmits<{ toggleFavorite: [listing: Listing] }>()
const condition = computed(() => props.listing.sold ? 'Vendido' : props.listing.condition)
</script>

<template>
  <li class="listing" :class="{ 'listing--featured': featured }">
    <RouterLink :to="{ name: 'product', params: { id: listing.id } }" class="listing-link">
      <div class="listing-media">
        <ListingPhoto class="listing-photo" :listing-id="listing.id" alt="" :eager="featured" />
        <span v-if="!featured" class="listing-condition">{{ condition }}</span>
      </div>
      <div class="listing-info">
        <h3 class="listing-title">{{ listing.title }}</h3>
        <strong class="listing-price">{{ formatPrice(listing.price, { compact: true }) }}</strong>
        <span v-if="featured" class="listing-featured-condition">{{ condition }}</span>
        <span v-else class="listing-location">
          <AppIcon name="pin" :size="13" />
          <span>{{ listing.neighborhood }}{{ listing.owned ? ' · Tu anuncio' : '' }}</span>
        </span>
      </div>
    </RouterLink>
    <button
      v-if="!listing.owned && !featured"
      class="listing-save"
      type="button"
      :aria-label="`${listing.saved ? 'Quitar de guardados' : 'Guardar'} ${listing.title}`"
      :aria-pressed="listing.saved"
      :disabled="saving"
      @click="emit('toggleFavorite', listing)"
    >
      <AppIcon name="heart" :size="18" />
    </button>
  </li>
</template>

<style scoped>
.listing {
  position: relative;
  display: flex;
  min-width: 0;
}
.listing-link {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-raised);
  color: var(--text);
  text-decoration: none;
}
.listing-link:focus-visible,
.listing-save:focus-visible {
  outline-offset: 2px;
}
.listing-media {
  position: relative;
  overflow: hidden;
  aspect-ratio: 1;
  background: var(--surface-soft);
}
.listing-photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transition: transform 180ms ease-out;
}
.listing-condition {
  position: absolute;
  inset-inline-start: 8px;
  bottom: 8px;
  max-width: calc(100% - 16px);
  padding: 3px 7px;
  border-radius: 6px;
  background: var(--surface);
  color: var(--text);
  font-size: 0.6875rem;
  font-weight: 800;
  overflow-wrap: anywhere;
}
.listing-save {
  position: absolute;
  inset-inline-end: 8px;
  top: 8px;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--surface);
  color: var(--text);
}
.listing-save:hover,
.listing-save[aria-pressed="true"] {
  border-color: var(--local-accent-line);
  color: var(--local-accent-text);
}
.listing-save[aria-pressed="true"] svg {
  fill: currentColor;
}
.listing-info {
  display: grid;
  flex: 1;
  grid-template-rows: auto auto 1fr;
  align-items: start;
  gap: 5px;
  padding: 10px 12px 12px;
  overflow-wrap: anywhere;
}
.listing-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 800;
  line-height: 1.3;
}
.listing-price {
  color: var(--local-accent-text);
  font-size: 1rem;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}
.listing-location {
  display: flex;
  align-self: end;
  align-items: center;
  gap: 4px;
  color: var(--muted);
  font-size: 0.75rem;
  line-height: 1.4;
}
.listing--featured .listing-link {
  display: grid;
  grid-template-columns: 53% minmax(0, 1fr);
  border-color: var(--local-accent-line);
  background: var(--local-accent-fill);
}
.listing--featured .listing-media {
  min-height: 132px;
  aspect-ratio: auto;
}
.listing--featured .listing-info {
  grid-template-rows: auto auto auto;
  align-content: center;
  gap: 6px;
  padding: 12px;
}
.listing--featured .listing-title {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  font-size: clamp(1rem, 4.7vw, 1.25rem);
  font-weight: 900;
  line-height: 1.1;
}
.listing--featured .listing-price {
  font-size: clamp(1.125rem, 5vw, 1.5rem);
  line-height: 1.15;
}
.listing-featured-condition {
  color: var(--text);
  font-size: 0.75rem;
}
@media (hover: hover) {
  .listing-link:hover .listing-title {
    text-decoration: underline;
    text-underline-offset: 3px;
  }
  .listing-link:hover .listing-photo {
    transform: scale(1.03);
  }
}
</style>
