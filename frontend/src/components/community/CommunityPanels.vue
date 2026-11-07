<script setup lang="ts">
import { computed } from 'vue'
import {
  NAvatar,
  NButton,
  NDrawer,
  NDrawerContent,
  NEmpty,
  NTag,
} from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import { activities, type Activity } from '../../composables/useCommunity'

export type Panel =
  | 'messages'
  | 'notifications'
  | 'profile'
  | 'neighborhood'
  | 'activities'
  | 'activity'
defineProps<{
  neighborhood: string
  selectedActivity: Activity | null
  interested: string[]
}>()
const panel = defineModel<Panel | null>('panel', { required: true })
const emit = defineEmits<{
  changeNeighborhood: [neighborhood: string]
  open: [panel: Panel]
  selectActivity: [activity: Activity]
  toggleInterest: [id: string]
}>()
const neighborhoodOptions = ['San Blas', 'San Cristóbal', 'Santa Ana']
const titles: Record<Panel, string> = {
  messages: 'Tus conversaciones',
  notifications: 'Lo que pasa cerquita',
  profile: 'Tu espacio',
  neighborhood: 'Tu barrio',
  activities: 'Planes para encontrarnos',
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
    :style="{
      maxHeight: '85dvh',
      maxWidth: '480px',
      marginInline: 'auto',
      borderRadius: '24px 24px 0 0',
    }"
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
        <p class="sheet-hint">Elige la comunidad que quieres ver.</p>
        <button
          v-for="option in neighborhoodOptions"
          :key="option"
          type="button"
          class="sheet-row"
          @click="selectNeighborhood(option)"
        >
          <AppIcon name="pin" /><span
            >{{ option }}<small>Cusco, Perú</small></span
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
            >Tenemos planes cerquita<small
              >Descubre las actividades del barrio.</small
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
        <NAvatar round :size="76" src="/images/ana.jpg" />
        <h3>Ana</h3>
        <p class="sheet-hint">Vecina de {{ neighborhood }} · Cusco</p>
        <NTag round :bordered="false" type="primary"
          >Parte de esta comunidad</NTag
        >
      </div>
      <div v-else-if="panel === 'activities'" class="sheet-list">
        <button
          v-for="activity in activities"
          :key="activity.id"
          class="sheet-row"
          type="button"
          @click="emit('selectActivity', activity)"
        >
          <span class="sheet-icon"><AppIcon name="calendar" /></span
          ><span
            >{{ activity.title
            }}<small
              >{{ activity.date }} · {{ activity.time }} ·
              {{ activity.location }}</small
            ></span
          ><AppIcon name="arrow" :size="18" />
        </button>
      </div>
      <div
        v-else-if="panel === 'activity' && selectedActivity"
        class="activity-detail"
      >
        <img
          v-if="selectedActivity.image"
          class="detail-image"
          :src="selectedActivity.image"
          alt=""
        /><NTag round :bordered="false" type="primary">{{
          selectedActivity.category
        }}</NTag>
        <h3>{{ selectedActivity.title }}</h3>
        <p>{{ selectedActivity.description }}</p>
        <div class="detail-meta">
          <AppIcon name="calendar" :size="18" />{{ selectedActivity.date }} ·
          {{ selectedActivity.time }}
        </div>
        <div class="detail-meta">
          <AppIcon name="pin" :size="18" />{{ selectedActivity.location }}
        </div>
        <NButton
          type="primary"
          size="large"
          block
          :aria-pressed="interested.includes(selectedActivity.id)"
          @click="emit('toggleInterest', selectedActivity.id)"
          >{{
            interested.includes(selectedActivity.id)
              ? 'Guardado en mis planes'
              : 'Me interesa este plan'
          }}</NButton
        >
      </div>
      <p class="demo-note">Modo de ejemplo · Datos temporales</p>
    </NDrawerContent>
  </NDrawer>
</template>
