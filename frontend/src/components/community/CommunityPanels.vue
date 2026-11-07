<script setup lang="ts">
import { computed } from 'vue'
import { currentUser } from '../../composables/useAuth'
import {
  NDrawer,
  NDrawerContent,
  NEmpty,
  NTag,
} from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import CurrentUserAvatar from '../auth/CurrentUserAvatar.vue'
import PostPhotoGallery from './PostPhotoGallery.vue'
import ActivityDetails from './ActivityDetails.vue'
import ActivitiesAgenda from './ActivitiesAgenda.vue'
import NeighborProfileDetails from '../neighbors/NeighborProfileDetails.vue'
import { useCommunity, type Post } from '../../composables/useCommunity'

export type Panel =
  | 'messages'
  | 'notifications'
  | 'profile'
  | 'neighborhood'
  | 'activities'
  | 'activity'
defineProps<{
  neighborhood: string
  selectedActivity?: Post
}>()
const panel = defineModel<Panel | null>('panel', { required: true })
const emit = defineEmits<{
  changeNeighborhood: [neighborhood: string]
  open: [panel: Panel]
  selectActivity: [id: string]
}>()
const { activities, activitiesLoading, activitiesError, loadActivities } = useCommunity()
const neighborhoodOptions = computed(() => [
  currentUser.value?.neighborhood || 'Tu comunidad',
])
const titles: Record<Panel, string> = {
  messages: 'Tus conversaciones',
  notifications: 'Lo que pasa cerquita',
  profile: 'Tu espacio',
  neighborhood: 'Tu barrio',
  activities: 'Agenda del barrio',
  activity: 'Un plan en el barrio',
}
const drawerTitle = computed(() => (panel.value ? titles[panel.value] : ''))
function selectNeighborhood(neighborhood: string) {
  emit('changeNeighborhood', neighborhood)
  panel.value = null
}
</script>

<template>
  <NDrawer
    :show="panel !== null"
    placement="bottom"
    height="min(85dvh, 640px)"
    class="app-bottom-drawer"
    :auto-focus="true"
    @update:show="
      (show: boolean) => {
        if (!show) panel = null
      }
    "
  >
    <NDrawerContent
      :title="drawerTitle"
      closable
      :native-scrollbar="false"
      body-content-style="padding-bottom: max(24px, env(safe-area-inset-bottom));"
    >
      <div v-if="panel === 'neighborhood'" class="sheet-list">
        <p class="sheet-hint">
          Las publicaciones se limitan a tu distrito de registro.
        </p>
        <button
          v-for="option in neighborhoodOptions"
          :key="option"
          type="button"
          class="sheet-row"
          @click="selectNeighborhood(option)"
        >
          <AppIcon name="pin" /><span
            >{{ option }}<small>Tu zona de registro</small></span
          ><AppIcon v-if="neighborhood === option" name="check" />
        </button>
      </div>
      <div v-else-if="panel === 'notifications'" class="sheet-list">
        <div class="sheet-row">
          <span class="sheet-icon"><AppIcon name="users" /></span
          ><span
            >¡Miguel llegó al barrio!<small
              >Dale una cálida bienvenida.</small
            ></span
          >
        </div>
        <div class="sheet-row">
          <span class="sheet-icon"><AppIcon name="chat" /></span
          ><span
            >Una bienvenida de Lucía<small
              >Hay una nueva conversación en el muro.</small
            ></span
          >
        </div>
        <button
          class="sheet-row"
          type="button"
          @click="emit('open', 'activities')"
        >
          <span class="sheet-icon"><AppIcon name="calendar" /></span
          ><span
            >Planes del barrio<small
              >Consulta las actividades publicadas.</small
            ></span
          ><AppIcon name="arrow" :size="18" />
        </button>
      </div>
      <div v-else-if="panel === 'messages'" class="sheet-empty">
        <NEmpty description="Todo empieza con un saludo"
          ><template #extra
            ><p class="sheet-hint">
              Todavía no tienes conversaciones.
            </p></template
          ></NEmpty
        >
      </div>
      <div v-else-if="panel === 'profile'" class="profile-sheet">
        <CurrentUserAvatar :size="76" />
        <h3>{{ currentUser?.name || 'Mi cuenta' }}</h3>
        <p class="sheet-hint">{{ neighborhood }}</p>
        <NTag round :bordered="false" type="primary"
          >Parte de esta comunidad</NTag
        >
        <NeighborProfileDetails
          v-if="currentUser?.neighbor_profile"
          :bio="currentUser.neighbor_profile.bio"
          :skills="currentUser.neighbor_profile.skills"
          :interests="currentUser.neighbor_profile.interests"
          :availability="currentUser.neighbor_profile.availability"
          :can-help="currentUser.neighbor_profile.can_help"
        />
      </div>
      <ActivitiesAgenda
        v-else-if="panel === 'activities'"
        :activities="activities"
        :loading="activitiesLoading"
        :failed="activitiesError"
        @select="emit('selectActivity', $event)"
        @retry="loadActivities"
      />
      <div v-else-if="panel === 'activity'">
        <div v-if="selectedActivity" class="activity-detail">
          <button class="text-link activity-back" type="button" @click="emit('open', 'activities')">
            <AppIcon name="arrow" :size="16" /> Volver a la agenda
          </button>
          <ActivityDetails v-if="selectedActivity.activity" :details="selectedActivity.activity" />
          <p class="detail-meta">
            <AppIcon name="pin" :size="16" />
            {{ selectedActivity.author }} · {{ selectedActivity.neighborhood }}
          </p>
          <NTag v-if="selectedActivity.status === 'resolved'" size="small" round>Finalizada</NTag>
          <p v-for="(paragraph, index) in selectedActivity.paragraphs" :key="index">
            {{ paragraph }}
          </p>
          <PostPhotoGallery
            v-if="selectedActivity.has_photo"
            :key="selectedActivity.id"
            :post-id="selectedActivity.id"
            :count="selectedActivity.photo_count ?? 1"
          />
        </div>
        <p v-else class="sheet-hint">Esta publicación ya no está disponible.</p>
      </div>
      <p v-if="panel === 'notifications'" class="demo-note">Modo de ejemplo · Datos temporales</p>
    </NDrawerContent>
  </NDrawer>
</template>

<style scoped>
.activity-back {
  min-height: 44px;
  font-size: 0.8125rem;
}
.activity-back svg {
  transform: rotate(180deg);
}
.activity-detail p {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}
</style>
