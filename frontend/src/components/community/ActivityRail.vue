<script setup lang="ts">
import { activities, type Activity } from '../../composables/useCommunity'
import AppIcon from '../AppIcon.vue'
const emit = defineEmits<{ select: [activity: Activity]; showAll: [] }>()
</script>

<template>
  <section class="activities-section" aria-labelledby="activities-title">
    <div class="section-heading">
      <h2 id="activities-title">Nos vemos en el barrio</h2>
      <button class="text-link" type="button" @click="emit('showAll')">
        Ver todo <AppIcon name="arrow" :size="15" />
      </button>
    </div>
    <div class="activity-rail">
      <button
        v-for="activity in activities"
        :key="activity.id"
        class="activity-card"
        type="button"
        @click="emit('select', activity)"
      >
        <div
          class="activity-cover"
          :class="{ 'market-cover': !activity.image }"
        >
          <img
            v-if="activity.image"
            :src="activity.image"
            alt=""
            width="400"
            height="260"
            loading="lazy"
          />
          <div v-else class="market-poster">
            <span>DE MANOS VECINAS</span
            ><strong>hecho<br />cerquita<span>de ti.</span></strong
            ><span>ARTE · SABORES · ENCUENTROS</span>
          </div>
          <span class="activity-date"
            >{{ activity.date }}<strong>{{ activity.time }}</strong></span
          >
        </div>
        <div class="activity-info">
          <span class="activity-category">{{ activity.category }}</span>
          <h3>{{ activity.title }}</h3>
          <p><AppIcon name="pin" :size="13" />{{ activity.location }}</p>
        </div>
      </button>
    </div>
  </section>
</template>
