<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { formatPrice, type Listing } from '../../composables/useMarketplace'
import AppIcon from '../AppIcon.vue'
defineProps<{ listing: Listing }>()
</script>

<template>
  <RouterLink
    :to="{ name: 'product', params: { id: listing.id } }"
    class="product-card"
  >
    <div class="product-card-photo">
      <img
        :src="listing.image"
        :alt="listing.title"
        width="720"
        height="460"
        loading="lazy"
      />
      <span v-if="listing.sold" class="product-state">Vendido</span>
      <span v-else class="product-state">{{ listing.condition }}</span>
      <span class="product-card-price">{{ formatPrice(listing.price) }}</span>
    </div>
    <div class="product-card-body">
      <h2>{{ listing.title }}</h2>
      <p class="product-seller">
        <span>{{ listing.owned ? 'Tu anuncio' : listing.seller }}</span
        ><span>·</span>{{ listing.category }}
      </p>
      <p class="product-location">
        <AppIcon name="pin" :size="14" />{{ listing.neighborhood
        }}<span>Recogida en el barrio</span>
      </p>
    </div>
  </RouterLink>
</template>
