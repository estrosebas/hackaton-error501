<script setup lang="ts">
import { NAvatar, NDrawer, NDrawerContent } from 'naive-ui'
import NeighborProfileDetails from './NeighborProfileDetails.vue'
import type { NearbyNeighbor } from '../../composables/useNearbyNeighbors'
defineProps<{ neighbor?: NearbyNeighbor }>()
const show = defineModel<boolean>('show', { required: true })
</script>

<template>
  <NDrawer
    v-model:show="show"
    placement="bottom"
    height="min(85dvh, 700px)"
    class="app-bottom-drawer"
    ><NDrawerContent
      title="Conoce a tu vecino"
      closable
      :native-scrollbar="false"
      ><article v-if="neighbor" class="neighbor-full-profile">
        <div class="neighbor-profile-identity">
          <NAvatar
            round
            :size="82"
            :src="neighbor.avatar"
            :img-props="{ alt: '' }"
          />
          <h2>{{ neighbor.name }}</h2>
          <p>{{ neighbor.joined }}</p>
        </div>
        <div class="neighbor-profile-facts">
          <div>
            <span>Barrio</span><strong>{{ neighbor.neighborhood }}</strong>
          </div>
          <div>
            <span>Distancia aproximada</span
            ><strong>A {{ neighbor.distance }} m</strong>
          </div>
        </div>
        <NeighborProfileDetails
          :bio="neighbor.bio"
          :skills="neighbor.skills"
          :interests="neighbor.interests"
          :availability="neighbor.availability"
          :can-help="neighbor.canHelp"
        />
        <p class="neighbor-example-note">
          Perfil y ubicación de ejemplo para explorar masi.
        </p>
      </article></NDrawerContent
    ></NDrawer
  >
</template>
