<script setup lang="ts">
import { NBadge, NButton } from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'
import { RouterLink } from 'vue-router'

defineProps<{ neighborhood: string; unread: number; compact?: boolean }>()
const emit = defineEmits<{
  open: [panel: 'messages' | 'notifications' | 'profile' | 'neighborhood']
}>()
</script>

<template>
  <header class="community-header">
    <div class="topbar">
      <RouterLink class="wordmark" :to="{ name: 'home' }" aria-label="masi, inicio">
        <AppIcon name="home" :size="30" />
        <span>masi<span class="brand-dot">.</span></span>
      </RouterLink>
      <div class="header-actions">
        <NButton
          circle
          quaternary
          class="icon-button"
          aria-label="Mensajes"
          @click="emit('open', 'messages')"
          ><AppIcon name="send" :size="21"
        /></NButton>
        <NBadge
          :value="unread"
          :max="9"
          :show="unread > 0"
          color="#ff6389"
          :offset="[-6, 7]"
        >
          <NButton
            circle
            quaternary
            class="icon-button"
            :aria-label="unread ? `Notificaciones (${unread} sin leer)` : 'Notificaciones'"
            @click="emit('open', 'notifications')"
            ><AppIcon name="bell" :size="22"
          /></NButton>
        </NBadge>
        <NButton
          circle
          quaternary
          class="profile-button"
          aria-label="Mi perfil"
          @click="emit('open', 'profile')"
        >
          <CurrentUserAvatar :size="36" />
        </NButton>
      </div>
    </div>
    <div v-if="!compact" class="neighborhood-bar">
      <button
        class="neighborhood-selector"
        type="button"
        @click="emit('open', 'neighborhood')"
      >
        <span class="location-mark"><AppIcon name="pin" :size="20" /></span>
        <span class="neighborhood-label"
          ><strong>{{ neighborhood }}</strong
          ><span>Tu barrio y alrededores</span></span
        >
        <span class="location-chevron"
          ><AppIcon name="chevron" :size="19"
        /></span>
      </button>
    </div>
  </header>
</template>
