<script setup lang="ts">
import NeighborEndorsement from '../components/neighbors/NeighborEndorsement.vue'
import { useMessage } from 'naive-ui'
import { computed, shallowRef, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import CurrentUserAvatar from '../components/auth/CurrentUserAvatar.vue'
import ProfilePhoto from '../components/auth/ProfilePhoto.vue'
import ProfileEditor from '../components/profile/ProfileEditor.vue'
import ProfileIdentity from '../components/profile/ProfileIdentity.vue'
import ProfilePage from '../components/profile/ProfilePage.vue'
import ProfileShortcuts from '../components/profile/ProfileShortcuts.vue'
import PrivateAccountCard from '../components/profile/PrivateAccountCard.vue'
import FeedInterestsCard from '../components/profile/FeedInterestsCard.vue'
import ProfileContributions from '../components/profile/ProfileContributions.vue'
import { startMasiTour } from '../composables/useMasiTour'
import { currentUser, logout } from '../composables/useAuth'
import { useCommunity } from '../composables/useCommunity'
import { fetchListings } from '../composables/useMarketplace'

const emit = defineEmits<{ openPanel: [panel: 'activities' | 'neighborhood'] }>()
const { neighborhood } = useCommunity()
const listingCount = shallowRef<number>()
const favoriteCount = shallowRef<number>()
watch(() => currentUser.value?.id, async (id, _, onCleanup) => {
  listingCount.value = undefined
  favoriteCount.value = undefined
  if (!id) return
  const request = new AbortController()
  onCleanup(() => request.abort())
  const [mine, saved] = await Promise.allSettled([
    fetchListings({ view: 'mine' }, request.signal),
    fetchListings({ view: 'saved' }, request.signal),
  ])
  if (request.signal.aborted) return
  if (mine.status === 'fulfilled') listingCount.value = mine.value.total
  if (saved.status === 'fulfilled') favoriteCount.value = saved.value.total
}, { immediate: true })
const district = computed(() => currentUser.value?.neighborhood || neighborhood.value)
const districtName = computed(() => currentUser.value?.district || 'Tu distrito')
const locationLabel = computed(() => district.value===districtName.value ? district.value : `${district.value} · ${districtName.value}`)
const buildId = import.meta.env.VITE_MASI_BUILD_ID || 'local'
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
    <ProfileIdentity :name="currentUser?.name || 'Mi cuenta'" :district="locationLabel">
      <template #avatar="{ size }"><CurrentUserAvatar :size="size" /></template>
      <template #action><ProfilePhoto /></template>
    </ProfileIdentity>

    <template #presentation>
      <NeighborEndorsement v-if="currentUser" :neighbor-id="currentUser.id" :name="currentUser.name"/><ProfileEditor />
      <PrivateAccountCard />
      <FeedInterestsCard />
      <ProfileContributions />
      <button type="button" class="tour-replay" @click="startMasiTour(router)"><AppIcon name="map" :size="19" /> Cómo funciona masi</button>
    </template>
    <template #sidebar>
      <ProfileShortcuts
        :district="district"
        :district-name="districtName"
        :listings="listingCount"
        :favorites="favoriteCount"
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
        <small>Versión {{buildId.slice(0,8)}}</small>
      </footer>
    </template>
  </ProfilePage>
</template>

<style scoped>
.tour-replay{display:flex;align-items:center;gap:10px;padding:14px 18px;border:1px solid var(--line);border-radius:14px;background:var(--surface-soft);color:var(--lavender);font:inherit;font-weight:800;cursor:pointer}
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
