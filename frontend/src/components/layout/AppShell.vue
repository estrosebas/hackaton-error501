<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { useMessage } from 'naive-ui'
import CommunityHeader from '../community/CommunityHeader.vue'
import CommunityEmergencyBanner from '../community/CommunityEmergencyBanner.vue'
import BottomNavigation from '../community/BottomNavigation.vue'
import { navigationItems } from '../community/navigation'
import CommunityPanels, { type Panel } from '../community/CommunityPanels.vue'
import { provideCommunity } from '../../composables/useCommunity'
import { provideCommunityNotifications } from '../../composables/useCommunityNotifications'
import { provideProfileAvatar } from '../../composables/useProfileAvatar'
import FeedInterestsDialog from '../profile/FeedInterestsDialog.vue'
import { currentUser } from '../../composables/useAuth'

const { neighborhood, activities } = provideCommunity()
provideProfileAvatar()
const route = useRoute()
const router = useRouter()
const tabDirection = shallowRef<'forward' | 'back'>()
const panel = shallowRef<Panel | null>(null)
const interestsOpen = shallowRef(false)
watch(() => currentUser.value?.id, id => { interestsOpen.value = !!id && !currentUser.value?.feed_interests_completed_at }, {immediate:true})
const message = useMessage()
const { unread, unreadEmergencies } = provideCommunityNotifications({
  onNewAlerts: (count) => {
    message.warning(count === 1 ? 'Nueva alerta vecinal en tu distrito' : `${count} alertas vecinales nuevas en tu distrito`)
  },
  onNewEmergencies: (count) => {
    message.error(count === 1 ? 'Nueva emergencia en tu distrito' : `${count} emergencias nuevas en tu distrito`, {
      duration: 6500,
      closable: true,
    })
  },
})
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
}

function selectActivity(id: string) {
  selectedActivityId.value = id
  panel.value = 'activity'
}

function removePreviousView(_element: Element, done: () => void) {
  done()
}

watch(() => route.name, (to, from) => {
  const nextIndex = navigationItems.findIndex((item) => item.key === to)
  const previousIndex = navigationItems.findIndex((item) => item.key === from)
  tabDirection.value = nextIndex < 0 || previousIndex < 0
    ? undefined
    : nextIndex > previousIndex ? 'forward' : 'back'
}, { flush: 'sync' })

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
    <div class="community-sticky-header">
      <CommunityHeader
        :neighborhood="neighborhood"
        :unread="unread"
        :compact="compactHeader"
        @open="openPanel"
      />
      <CommunityEmergencyBanner :count="unreadEmergencies.length" @open="openPanel('notifications')" />
    </div>
    <main id="page-content" class="community-content" :data-tab-direction="tabDirection" tabindex="-1">
      <RouterView v-slot="{ Component }">
        <Transition name="community-page" @leave="removePreviousView">
          <component
            :is="Component"
            @open-panel="openPanel"
            @select-activity="selectActivity"
          />
        </Transition>
      </RouterView>
    </main>
    <BottomNavigation :active="activeNav" />
    <FeedInterestsDialog v-model:show="interestsOpen" />
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
.community-sticky-header {
  position: sticky;
  top: 0;
  z-index: 9;
  flex: none;
}

.community-content {
  isolation: isolate;
}

.profile-layout :deep(.community-header),
.chat-layout :deep(.community-header) {
  border-bottom: 1px solid var(--line);
}

.chat-layout {
  display: flex;
  flex-direction: column;
  height: 100dvh;
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
  .community-page-enter-active {
    animation: community-page-fade 150ms ease-out;
  }

  .community-content[data-tab-direction]:has(> .community-page-enter-active) {
    overflow-x: clip;
  }

  .community-content[data-tab-direction] > .community-page-enter-active {
    --tab-entry-x: 12px;
    animation: community-tab-in 200ms cubic-bezier(0.16, 1, 0.3, 1);
  }

  .community-content[data-tab-direction='back'] > .community-page-enter-active {
    --tab-entry-x: -12px;
  }

  @keyframes community-page-fade {
    from { opacity: 0.88; }
    to { opacity: 1; }
  }

  @keyframes community-tab-in {
    from {
      opacity: 0.82;
      translate: var(--tab-entry-x) 0;
    }
    to {
      opacity: 1;
      translate: 0 0;
    }
  }
}
</style>
