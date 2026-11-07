<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { RouterView, useRoute, useRouter, type RouteLocationNormalizedLoaded } from 'vue-router'
import CommunityHeader from '../community/CommunityHeader.vue'
import BottomNavigation from '../community/BottomNavigation.vue'
import CommunityPanels, { type Panel } from '../community/CommunityPanels.vue'
import { provideCommunity } from '../../composables/useCommunity'
import { provideProfileAvatar } from '../../composables/useProfileAvatar'

const { neighborhood, activities } = provideCommunity()
provideProfileAvatar()
const route = useRoute()
const router = useRouter()
const animatedTabs = new Set<RouteLocationNormalizedLoaded['name']>([
  'home',
  'neighbors',
  'market',
  'more',
])
const panel = shallowRef<Panel | null>(null)
const unread = shallowRef(3)
const selectedActivityId = shallowRef<string>()
const selectedActivity = computed(() =>
  activities.value.find((activity) => activity.id === selectedActivityId.value),
)
const activeNav = computed(() => route.meta.section)
const chatLayout = computed(() => route.name === 'messages')
const profileLayout = computed(() => route.name === 'more' || route.name === 'neighbor-profile')
const compactHeader = computed(
  () =>
    activeNav.value === 'home' ||
    activeNav.value === 'neighbors' ||
    activeNav.value === 'more' ||
    (activeNav.value === 'market' && route.name !== 'product'),
)

function openPanel(value: Panel) {
  if (value === 'messages') { void router.push('/mensajes'); return }
  panel.value = value
  if (value === 'notifications') unread.value = 0
}

function selectActivity(id: string) {
  selectedActivityId.value = id
  panel.value = 'activity'
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
  <div
    class="mobile-app"
    :class="{
      'profile-layout': profileLayout,
      'chat-layout': chatLayout,
      'chat-detail': chatLayout && Number(route.params.roomId),
    }"
  >
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
      @change-neighborhood="neighborhood = $event"
      @open="openPanel"
      @select-activity="selectActivity"
    />
  </div>
</template>

<style scoped>
.profile-layout :deep(.community-header),
.chat-layout :deep(.community-header) {
  border-bottom: 1px solid var(--line);
}

.chat-layout {
  display: flex;
  flex-direction: column;
  height: 100dvh;
}

.chat-layout :deep(.community-header) {
  flex: none;
}

.chat-layout .community-content {
  display: flex;
  flex: 1;
  min-height: 0;
}

@media (min-width: 760px) {
  .profile-layout {
    --app-max-width: 940px;
  }

  .profile-layout :deep(.topbar) {
    padding-inline: 32px;
  }

  .chat-layout {
    --app-max-width: 1120px;
    min-height: max(100dvh, 470px);
  }
}

@media (max-width: 759px) {
  .chat-layout.chat-detail {
    height: auto;
    min-height: 0;
  }

  .chat-layout.chat-detail .community-content {
    padding-bottom: 0;
  }

  .chat-layout.chat-detail :deep(.community-header),
  .chat-layout.chat-detail :deep(.bottom-navigation) {
    display: none;
  }
}

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
