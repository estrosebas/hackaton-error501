<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { RouterView, useRoute, type RouteLocationNormalizedLoaded } from 'vue-router'
import CommunityHeader from '../community/CommunityHeader.vue'
import BottomNavigation from '../community/BottomNavigation.vue'
import CommunityPanels, { type Panel } from '../community/CommunityPanels.vue'
import { provideCommunity, type Activity } from '../../composables/useCommunity'

const { neighborhood } = provideCommunity()
const route = useRoute()
const animatedTabs = new Set<RouteLocationNormalizedLoaded['name']>([
  'home',
  'neighbors',
  'market',
  'more',
])
const panel = shallowRef<Panel | null>(null)
const unread = shallowRef(3)
const selectedActivity = shallowRef<Activity | null>(null)
const interested = shallowRef<string[]>([])
const activeNav = computed(() => route.meta.section)
const compactHeader = computed(
  () =>
    activeNav.value === 'home' ||
    activeNav.value === 'neighbors' ||
    (activeNav.value === 'market' && route.name !== 'product'),
)

function openPanel(value: Panel) {
  panel.value = value
  if (value === 'notifications') unread.value = 0
}

function selectActivity(activity: Activity) {
  selectedActivity.value = activity
  panel.value = 'activity'
}

function toggleInterest(id: string) {
  interested.value = interested.value.includes(id)
    ? interested.value.filter((item) => item !== id)
    : [...interested.value, id]
}

function removePreviousView(_element: Element, done: () => void) {
  done()
}

watch(
  () => route.fullPath,
  () => {
    panel.value = null
  },
)
</script>

<template>
  <div class="mobile-app">
    <a class="skip-link" href="#page-content">Ir al contenido</a>
    <CommunityHeader
      :neighborhood="neighborhood"
      :unread="unread"
      :compact="compactHeader"
      @open="openPanel"
    />
    <main id="page-content" class="community-content" tabindex="-1">
      <RouterView v-slot="{ Component, route: viewRoute }">
        <Transition
          name="community-tab"
          :css="animatedTabs.has(viewRoute.name)"
          @leave="removePreviousView"
        >
          <component
            :is="Component"
            @open-panel="openPanel"
            @select-activity="selectActivity"
          />
        </Transition>
      </RouterView>
    </main>
    <BottomNavigation :active="activeNav" />
    <CommunityPanels
      v-model:panel="panel"
      :neighborhood="neighborhood"
      :selected-activity="selectedActivity"
      :interested="interested"
      @change-neighborhood="neighborhood = $event"
      @open="openPanel"
      @select-activity="selectActivity"
      @toggle-interest="toggleInterest"
    />
  </div>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
  .community-tab-enter-active {
    animation: community-tab-in 240ms cubic-bezier(0.22, 0.7, 0.25, 1);
  }

  @keyframes community-tab-in {
    from {
      opacity: 0.65;
      transform: translateY(6px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
}
</style>
