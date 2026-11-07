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
        alt=""
        width="720"
        height="460"
        loading="lazy"
      />
      <span class="product-state">{{ listing.sold ? 'Vendido' : listing.condition }}</span>
    </div>
    <div class="product-card-body">
      <div class="product-card-heading">
        <p class="product-card-category">{{ listing.category }}</p>
        <h3>{{ listing.title }}</h3>
        <p class="product-card-price">{{ formatPrice(listing.price) }}</p>
      </div>
      <div class="product-card-meta">
        <span class="product-seller">
          <img
            v-if="listing.avatar"
            :src="listing.avatar"
            alt=""
            width="18"
            height="18"
            loading="lazy"
          />
          {{ listing.owned ? 'Tu anuncio' : listing.seller }}
        </span>
        <span class="product-location">
          <AppIcon name="pin" :size="14" />{{ listing.neighborhood }}
        </span>
      </div>
    </div>
  </RouterLink>
</template>
