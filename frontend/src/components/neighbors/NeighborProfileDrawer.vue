<script setup lang="ts">
import { NAvatar, NDrawer, NDrawerContent, NTag } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import type { NearbyNeighbor } from '../../composables/useNearbyNeighbors'
defineProps<{ neighbor?: NearbyNeighbor }>()
const show = defineModel<boolean>('show', { required: true })
</script>

<template>
  <NDrawer
    v-model:show="show"
    placement="bottom"
    height="min(85dvh, 700px)"
    :style="{
      maxWidth: '480px',
      marginInline: 'auto',
      borderRadius: '24px 24px 0 0',
    }"
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
        <section>
          <h3>Un poquito sobre mí</h3>
          <p>{{ neighbor.bio }}</p>
        </section>
        <section>
          <h3><AppIcon name="hand" :size="18" />Puedo echarte una mano con</h3>
          <div class="neighbor-profile-tags">
            <NTag
              v-for="skill in neighbor.skills"
              :key="skill"
              round
              :bordered="false"
              type="primary"
              >{{ skill }}</NTag
            >
          </div>
        </section>
        <section>
          <h3><AppIcon name="calendar" :size="18" />Me apunto a</h3>
          <div class="neighbor-profile-tags">
            <NTag
              v-for="interest in neighbor.interests"
              :key="interest"
              round
              :bordered="false"
              >{{ interest }}</NTag
            >
          </div>
        </section>
        <section class="neighbor-availability">
          <AppIcon name="clock" :size="20" />
          <div>
            <h3>Disponibilidad</h3>
            <p>{{ neighbor.availability }}</p>
          </div>
          <NTag v-if="neighbor.canHelp" round :bordered="false" type="success"
            >Puede ayudar</NTag
          >
        </section>
        <p class="neighbor-example-note">
          Perfil y ubicación de ejemplo para explorar masi.
        </p>
      </article></NDrawerContent
    ></NDrawer
  >
</template>
