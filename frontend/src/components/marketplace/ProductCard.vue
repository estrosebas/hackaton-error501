<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { formatPrice, type Listing } from '../../composables/useMarketplace'
import AppIcon from '../AppIcon.vue'
defineProps<{ listing: Listing; wide: boolean }>()
</script>

<template>
  <li class="listing" :class="{ 'listing--wide': wide }">
    <RouterLink :to="{ name: 'product', params: { id: listing.id } }" class="listing-link">
      <div class="listing-media">
        <img
          class="listing-photo"
          :src="listing.image"
          alt=""
          width="720"
          height="460"
          :loading="wide ? 'eager' : 'lazy'"
        />
        <span class="listing-condition" :class="{ 'listing-condition--sold': listing.sold }">
          {{ listing.sold ? 'Vendido' : listing.condition }}
        </span>
      </div>
      <div class="listing-info">
        <h3 class="listing-title">{{ listing.title }}</h3>
        <strong class="listing-price">{{ formatPrice(listing.price, { compact: true }) }}</strong>
        <p class="listing-byline">
          <span class="listing-seller">
            <img v-if="listing.avatar" :src="listing.avatar" alt="" width="20" height="20" loading="lazy" />
            {{ listing.owned ? 'Tu anuncio' : listing.seller }}
          </span>
          <span class="listing-location"><AppIcon name="pin" :size="13" />{{ listing.neighborhood }}</span>
        </p>
      </div>
    </RouterLink>
  </li>
</template>

<style scoped>
.listing {
  display: flex;
  min-width: 0;
}
.listing--wide {
  grid-column: 1 / -1;
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
.listing-link:focus-visible {
  outline-offset: 2px;
}
.listing-media {
  position: relative;
  overflow: hidden;
  aspect-ratio: 5 / 4;
  background: var(--surface-soft);
}
.listing--wide .listing-media {
  aspect-ratio: 2 / 1;
}
.listing-photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 180ms ease-out;
}
.listing-condition {
  position: absolute;
  top: 9px;
  left: 9px;
  max-width: calc(100% - 18px);
  padding: 3px 7px;
  border-radius: 5px;
  background: var(--surface);
  color: var(--text);
  font-size: 11px;
  font-weight: 800;
  overflow-wrap: anywhere;
}
.listing-condition--sold {
  color: var(--local-accent-text);
}
.listing-info {
  display: grid;
  flex: 1;
  grid-template-rows: auto auto 1fr;
  align-items: start;
  gap: 6px;
  padding: 11px;
  overflow-wrap: anywhere;
}
.listing--wide .listing-info {
  grid-template-columns: minmax(0, 1fr) minmax(0, max-content);
  grid-template-rows: auto auto;
  column-gap: 10px;
  padding: 12px 14px;
}
.listing-title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  line-height: 1.25;
}
.listing--wide .listing-title {
  font-size: 19px;
  font-weight: 900;
}
.listing-byline,
.listing-seller,
.listing-location {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 3px 6px;
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
.listing-byline {
  align-self: end;
  gap: 5px 10px;
}
.listing-location {
  color: var(--lavender);
}
.listing-price {
  color: var(--local-accent-text);
  font-size: 18px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.listing--wide .listing-price {
  grid-column: 2;
  grid-row: 1 / 3;
  align-self: center;
  max-width: 9ch;
  padding: 7px 9px;
  border-radius: 8px;
  background: var(--local-accent-fill);
  font-size: 20px;
  text-align: center;
}
.listing-seller img {
  flex-shrink: 0;
  border-radius: 50%;
  object-fit: cover;
}
@media (min-width: 350px) {
  .listing--wide .listing-link {
    display: grid;
    grid-template-columns: 57% minmax(0, 1fr);
  }
  .listing--wide .listing-media {
    min-width: 0;
    min-height: clamp(140px, 37vw, 166px);
    aspect-ratio: auto;
  }
  .listing--wide .listing-info {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: auto auto auto;
    align-content: center;
    gap: 9px;
  }
  .listing--wide .listing-title {
    font-weight: 700;
    text-wrap: balance;
  }
  .listing--wide .listing-price {
    grid-area: auto;
    justify-self: start;
    max-width: 100%;
  }
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
