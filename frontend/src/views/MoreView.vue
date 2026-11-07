<script setup lang="ts">
import { NAvatar } from 'naive-ui'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import type { Panel } from '../components/community/CommunityPanels.vue'
import { useCommunity } from '../composables/useCommunity'
import { useMarketplace } from '../composables/useMarketplace'
const { neighborhood } = useCommunity()
const { ownListings, favoriteIds } = useMarketplace()
const emit = defineEmits<{ openPanel: [panel: Panel] }>()
</script>

<template>
  <section class="standard-page more-page">
    <div class="page-heading">
      <div>
        <h1>Tu rincón en masi</h1>
        <p>Todo lo que te conecta con tu comunidad.</p>
      </div>
    </div>
    <button
      class="profile-summary"
      type="button"
      @click="emit('openPanel', 'profile')"
    >
      <NAvatar
        round
        :size="60"
        src="/images/ana.jpg"
        :img-props="{ alt: '' }"
      /><span
        ><strong>Ana</strong><small>Vecina de {{ neighborhood }}</small></span
      ><AppIcon name="arrow" :size="20" />
    </button>
    <div class="more-menu">
      <RouterLink
        class="sheet-row"
        :to="{ name: 'market', query: { vista: 'mios' } }"
        ><AppIcon name="shop" /><span
          >Mis anuncios<small
            >{{ ownListings.length }} {{ ownListings.length === 1 ? 'producto publicado' : 'productos publicados' }}</small
          ></span
        ><AppIcon name="arrow" :size="18" /></RouterLink
      ><RouterLink
        class="sheet-row"
        :to="{ name: 'market', query: { vista: 'guardados' } }"
        ><AppIcon name="bookmark" /><span
          >Productos guardados<small
            >{{ favoriteIds.length }} {{ favoriteIds.length === 1 ? 'favorito' : 'favoritos' }} del mercadito</small
          ></span
        ><AppIcon name="arrow" :size="18" /></RouterLink
      ><button
        class="sheet-row"
        type="button"
        @click="emit('openPanel', 'activities')"
      >
        <AppIcon name="calendar" /><span>Planes del barrio</span
        ><AppIcon name="arrow" :size="18" /></button
      ><button
        class="sheet-row"
        type="button"
        @click="emit('openPanel', 'neighborhood')"
      >
        <AppIcon name="pin" /><span
          >Cambiar de barrio<small
            >{{ neighborhood }} y alrededores</small
          ></span
        ><AppIcon name="arrow" :size="18" />
      </button>
    </div>
    <div class="about-masi">
      <AppIcon name="home" :size="26" /><strong>masi.</strong>
      <p>
        «Colega», en quechua.<br />Un nombre para sentirnos un poquito más
        cerca.
      </p>
    </div>
  </section>
</template>
