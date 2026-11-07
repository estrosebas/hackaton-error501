<script setup lang="ts">
import { useMessage } from 'naive-ui'
import { computed, shallowRef } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import CurrentUserAvatar from '../components/auth/CurrentUserAvatar.vue'
import ProfilePhoto from '../components/auth/ProfilePhoto.vue'
import ProfileEditor from '../components/profile/ProfileEditor.vue'
import ProfileIdentity from '../components/profile/ProfileIdentity.vue'
import ProfilePage from '../components/profile/ProfilePage.vue'
import ProfileShortcuts from '../components/profile/ProfileShortcuts.vue'
import { currentUser, logout } from '../composables/useAuth'
import { useCommunity } from '../composables/useCommunity'
import { useMarketplace } from '../composables/useMarketplace'

const emit = defineEmits<{ openPanel: [panel: 'activities' | 'neighborhood'] }>()
const { neighborhood } = useCommunity()
const { ownListings, favoriteIds } = useMarketplace()
const district = computed(() => currentUser.value?.neighborhood || neighborhood.value)
const router = useRouter()
const signingOut = shallowRef(false)
const message = useMessage()

async function signOut() {
  if (signingOut.value) return
  signingOut.value = true
  try {
    await logout()
    await router.replace({ name: 'login', state: { masiContinueTo: null } })
  } catch {
    message.error('No pudimos cerrar la sesión. Revisa tu conexión e inténtalo de nuevo.')
  } finally {
    signingOut.value = false
  }
}
</script>

<template>
  <ProfilePage title="Mi perfil" description="Tu información y tus accesos en masi.">
    <ProfileIdentity :name="currentUser?.name || 'Mi cuenta'" :district="district">
      <template #avatar="{ size }"><CurrentUserAvatar :size="size" /></template>
      <template #action><ProfilePhoto /></template>
    </ProfileIdentity>

    <template #presentation>
      <ProfileEditor />
    </template>
    <template #sidebar>
      <ProfileShortcuts
        :district="district"
        :listings="ownListings.length"
        :favorites="favoriteIds.length"
        @open-panel="emit('openPanel', $event)"
      />
    </template>
    <template #footer>
      <footer class="profile-footer">
        <button class="sign-out" type="button" :disabled="signingOut" @click="signOut">
          <AppIcon name="log-out" :size="19" />
          {{ signingOut ? 'Cerrando sesión…' : 'Cerrar sesión' }}
        </button>
        <p><strong>masi.</strong> «Colega» en quechua.</p>
      </footer>
    </template>
  </ProfilePage>
</template>

<style scoped>
.profile-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 20px;
  margin-top: 24px;
  padding-top: 14px;
  border-top: 1px solid var(--line);
}

.profile-footer p {
  margin: 0;
  color: var(--muted);
  font-size: 0.8125rem;
}

.profile-footer strong {
  color: var(--text);
}

.sign-out {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 7px 10px;
  border: 0;
  border-radius: 10px;
  color: var(--local-accent-text);
  background: transparent;
  text-align: start;
  font-size: 0.875rem;
  font-weight: 800;
}

.sign-out:disabled {
  color: var(--muted);
  cursor: wait;
}

@media (hover: hover) {
  .sign-out:hover:not(:disabled) {
    background: var(--local-accent-fill);
  }
}

.sign-out:active:not(:disabled) {
  background: var(--local-accent-fill);
}
</style>
