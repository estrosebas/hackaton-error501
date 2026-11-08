<script setup lang="ts">
import { NButton, NSwitch } from 'naive-ui'
import { onMounted, shallowRef, useTemplateRef } from 'vue'
import { currentUser } from '../../composables/useAuth'
import { useCommunityNotifications } from '../../composables/useCommunityNotifications'
import AppIcon from '../AppIcon.vue'
import CommunityNotificationAvatar from './CommunityNotificationAvatar.vue'

const { snapshot, setPresence, setAvatar } = useCommunityNotifications()
defineEmits<{ back: [] }>()
const backButton = useTemplateRef<HTMLButtonElement>('back')
const photoInput = useTemplateRef<HTMLInputElement>('photo-input')
const busy = shallowRef(false)
const error = shallowRef('')
onMounted(() => backButton.value?.focus())

async function save(change: () => Promise<void>) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await change()
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'No pudimos guardar tu preferencia.'
  } finally {
    busy.value = false
  }
}

function chooseAvatar(event: Event) {
  const input = event.target as HTMLInputElement
  const photo = input.files?.[0]
  input.value = ''
  if (photo) void save(() => setAvatar(photo))
}

</script>

<template>
  <section class="notification-settings" aria-labelledby="visibility-title">
    <button ref="back" type="button" class="text-link settings-back" @click="$emit('back')">
      <AppIcon name="arrow" :size="16" /> Volver a los avisos
    </button>
    <h3 id="visibility-title">Mi visibilidad en el distrito</h3>
    <template v-if="snapshot?.has_district">
      <div class="presence-setting">
        <div>
          <strong id="presence-label">Mostrar que estoy activo</strong>
          <p id="presence-hint">Tus vecinos del distrito podrán verte mientras usas la app.</p>
        </div>
        <NSwitch
          size="large"
          :value="snapshot.sharing_presence"
          :disabled="busy"
          aria-labelledby="presence-label"
          aria-describedby="presence-hint"
          @update:value="save(() => setPresence($event))"
        />
      </div>
      <div class="avatar-setting">
        <CommunityNotificationAvatar
          v-if="currentUser"
          :person="{ id: currentUser.id, name: currentUser.name, avatar: snapshot.my_avatar }"
          :size="48"
        />
        <div>
          <strong>Avatar comunitario</strong>
          <p>Tu imagen aparece en los avisos que compartes con el distrito.</p>
        </div>
      </div>
      <div class="avatar-actions">
        <NButton type="primary" ghost :disabled="busy" @click="photoInput?.click()">
          <template #icon><AppIcon name="camera" :size="17" /></template>
          {{ snapshot.my_avatar ? 'Cambiar foto' : 'Subir foto' }}
        </NButton>
        <NButton v-if="snapshot.my_avatar" quaternary :disabled="busy" @click="save(() => setAvatar())">
          Retirar foto
        </NButton>
      </div>
      <input
        ref="photo-input"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        :disabled="busy"
        hidden
        @change="chooseAvatar"
      />
    </template>
    <p v-else>Completa tu distrito para editar tu visibilidad.</p>
    <p v-if="busy" role="status">Guardando…</p>
    <p v-if="error" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.settings-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
}
.settings-back svg {
  rotate: 180deg;
}
.notification-settings h3 {
  margin: 8px 0 0;
  font-size: 1.125rem;
  font-weight: 900;
}
.notification-settings p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}
.notification-settings > p[role] {
  margin-top: 12px;
}
.notification-settings p[role='alert'] {
  color: var(--local-accent-text);
}
.presence-setting, .avatar-setting {
  display: flex;
  align-items: center;
  gap: 14px;
}
.presence-setting {
  justify-content: space-between;
  padding-block: 16px;
  border-bottom: 1px solid var(--line);
}
.presence-setting :deep(.n-switch) {
  flex: none;
  min-width: 44px;
  min-height: 44px;
}
.avatar-setting {
  padding-top: 16px;
}
.avatar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}
.avatar-actions :deep(.n-button) {
  min-height: 44px;
}
</style>
