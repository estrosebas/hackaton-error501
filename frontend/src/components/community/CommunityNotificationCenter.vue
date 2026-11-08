<script setup lang="ts">
import { computed, nextTick, onMounted, shallowRef, useTemplateRef, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useCommunityNotifications, type CommunityNotification } from '../../composables/useCommunityNotifications'
import AppIcon, { type IconName } from '../AppIcon.vue'
import CommunityNotificationAvatar from './CommunityNotificationAvatar.vue'
import CommunityNotificationDetail from './CommunityNotificationDetail.vue'
import CommunityNotificationSettings from './CommunityNotificationSettings.vue'

const { snapshot, failed, emergenciesFailed, emergenciesReady, notices, unread, refresh, refreshEmergencies } = useCommunityNotifications()
defineEmits<{ close: [] }>()
const selectedId = shallowRef<string>()
const selected = computed(() => notices.value.find((notice) => notice.id === selectedId.value))
const settingsOpen = shallowRef(false)
const settingsButton = useTemplateRef<HTMLButtonElement>('settings-button')
const list = useTemplateRef<HTMLUListElement>('notices')
const heading = useTemplateRef<HTMLHeadingElement>('heading')
const types: Record<CommunityNotification['kind'], { label: string; icon: IconName }> = {
  activity: { label: 'Actividad comunitaria', icon: 'calendar' },
  need_help: { label: 'Pedido de ayuda', icon: 'hand' },
  alert: { label: 'Alerta vecinal', icon: 'bell' },
  emergency: { label: 'Emergencia', icon: 'bell' },
}
const publishedDate = new Intl.DateTimeFormat('es-PE', {
  day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit',
})

async function back() {
  const id = selected.value?.id
  selectedId.value = undefined
  await nextTick()
  list.value?.querySelector<HTMLButtonElement>(`[data-notice-id="${id}"]`)?.focus()
}

async function closeSettings() {
  settingsOpen.value = false
  await nextTick()
  settingsButton.value?.focus()
}

function refreshNotices() {
  void refresh()
  void refreshEmergencies()
}

onMounted(refreshNotices)

watch(selected, async (notice) => {
  if (notice || !selectedId.value) return
  selectedId.value = undefined
  await nextTick()
  heading.value?.focus()
})
</script>

<template>
  <CommunityNotificationDetail v-if="selected" :notice="selected" @back="back" @close="$emit('close')" />
  <CommunityNotificationSettings v-else-if="settingsOpen" @back="closeSettings" />
  <div v-else class="notification-center">
    <div v-if="failed || emergenciesFailed" role="status" class="notice-status">
      <p>{{ emergenciesFailed ? 'No pudimos actualizar las emergencias.' : 'No pudimos actualizar los avisos.' }}</p>
      <button type="button" class="text-link" @click="refreshNotices">Reintentar</button>
    </div>
    <p v-if="!snapshot && !failed && !notices.length" class="notice-status" role="status">Cargando avisos…</p>
    <p v-else-if="snapshot && !snapshot.has_district && !notices.length" class="notice-status">Completa tu distrito para ver los avisos de tu comunidad.</p>
    <template v-else-if="snapshot?.has_district || notices.length">
      <section aria-labelledby="notices-heading" class="notice-section">
        <div class="notice-heading">
          <div class="notice-heading-copy">
            <h3 id="notices-heading" ref="heading" tabindex="-1">Avisos del distrito</h3>
            <span v-if="unread" class="unread-count">{{ unread }} sin leer</span>
          </div>
          <button v-if="snapshot?.has_district" ref="settings-button" type="button" class="visibility-button" @click="settingsOpen = true">
            <AppIcon name="eye" :size="16" /> Visibilidad
          </button>
        </div>
        <ul v-if="notices.length" ref="notices" class="notice-list">
          <li v-for="notice in notices" :key="notice.id">
            <button
              type="button"
              class="notice-row"
              :data-kind="notice.kind"
              :class="{ 'notice-unread': !notice.read }"
              :data-notice-id="notice.id"
              @click="selectedId = notice.id"
            >
              <span class="notice-avatar">
                <CommunityNotificationAvatar v-if="notice.author" :person="notice.author" :size="44" />
                <span v-else class="empty-icon"><AppIcon name="bell" :size="20" /></span>
                <span v-if="notice.author" class="notice-icon"><AppIcon :name="types[notice.kind].icon" :size="13" /></span>
              </span>
              <span class="notice-copy">
                <span class="notice-meta">
                  <span class="notice-type">{{ types[notice.kind].label }}</span>
                  <span v-if="!notice.read" class="notice-new">Nuevo</span>
                </span>
                <strong v-if="notice.author">{{ notice.author.name }}</strong>
                <span>{{ notice.summary }}</span>
                <time :datetime="notice.published_at">{{ publishedDate.format(new Date(notice.published_at)) }}</time>
              </span>
              <AppIcon name="chevron" :size="16" class="notice-arrow" />
            </button>
          </li>
        </ul>
        <p v-else-if="!emergenciesReady && !emergenciesFailed" class="notice-status" role="status">Consultando emergencias…</p>
        <div v-else-if="!failed && !emergenciesFailed" class="notice-empty">
          <span class="empty-icon"><AppIcon name="bell" :size="23" /></span>
          <div>
            <strong>Estás al día</strong>
            <p class="notice-status">Por ahora no hay avisos recientes en tu distrito.</p>
          </div>
        </div>
      </section>
      <section v-if="snapshot" aria-labelledby="active-neighbors-heading" class="presence-section">
        <h3 id="active-neighbors-heading"><AppIcon name="users" :size="17" /> Vecinos activos cerca</h3>
        <ul v-if="snapshot.active.length" class="presence-list">
          <li v-for="person in snapshot.active" :key="person.id">
            <RouterLink
              class="presence-link"
              :to="{ name: 'neighbor-profile', params: { neighborId: person.id } }"
              :aria-label="`${person.name}, activo recientemente`"
              @click="$emit('close')"
            >
              <span class="presence-avatar">
                <CommunityNotificationAvatar :person="person" :size="30" />
                <span class="presence-dot" aria-hidden="true"></span>
              </span>
              <span>{{ person.name }}</span>
            </RouterLink>
          </li>
        </ul>
        <p v-else class="notice-status">Por ahora no hay vecinos del distrito compartiendo que están activos.</p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.notification-center {
  --presence-ink: #a7e4c6;
}
.presence-section {
  margin-top: 20px;
}
.presence-section h3 {
  display: flex;
  align-items: center;
  gap: 7px;
  margin: 0 0 8px;
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 800;
}
.presence-section h3 svg {
  color: var(--presence-ink);
}
.presence-list {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  margin: 0 -4px;
  padding: 4px;
  list-style: none;
  scrollbar-width: thin;
  scrollbar-color: var(--line) transparent;
}
.presence-list li {
  flex: 0 0 auto;
  max-width: 100%;
}
.presence-link {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 5px 12px 5px 6px;
  border: 1px solid var(--line);
  border-radius: 22px;
  background: var(--surface-raised);
  color: var(--text);
  font-size: 0.8125rem;
  font-weight: 800;
  overflow-wrap: anywhere;
  text-decoration: none;
}
.presence-link:focus-visible {
  outline-offset: 1px;
}
.presence-avatar, .notice-avatar {
  position: relative;
  display: flex;
  flex: none;
}
.presence-dot {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 10px;
  height: 10px;
  border: 2px solid var(--surface-raised);
  border-radius: 50%;
  background: var(--presence-ink);
}
.notice-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.notice-heading-copy {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 8px;
}
.notice-heading h3 {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 900;
}
.unread-count {
  padding: 3px 9px;
  border-radius: 12px;
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
  font-size: 0.75rem;
  font-weight: 800;
}
.visibility-button {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 5px;
  min-height: 44px;
  padding: 0 8px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--lavender);
  font-size: 0.8125rem;
  font-weight: 800;
}
.visibility-button:hover {
  background: var(--surface-raised);
}
.notice-list {
  display: grid;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.notice-row {
  --notice-ink: var(--lavender);
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: start;
  gap: 11px;
  width: 100%;
  min-height: 76px;
  padding: 12px 8px;
  border: 1px solid transparent;
  border-bottom-color: var(--line);
  background: transparent;
  color: var(--text);
  text-align: left;
}
.notice-row[data-kind='need_help'] {
  --notice-ink: var(--presence-ink);
}
.notice-row:is([data-kind='alert'], [data-kind='emergency']) {
  --notice-ink: var(--local-accent-text);
}
.notice-row[data-kind='alert'].notice-unread,
.notice-row[data-kind='emergency'] {
  border-color: var(--local-accent-line);
  border-radius: 14px;
  background: var(--local-accent-fill);
}
.notice-avatar {
  margin: 2px 2px 0 0;
}
.notice-icon {
  position: absolute;
  right: -4px;
  bottom: -4px;
  display: grid;
  width: 22px;
  height: 22px;
  place-items: center;
  border: 2px solid var(--surface);
  border-radius: 50%;
  background: var(--surface-raised);
  color: var(--notice-ink);
}
.notice-copy {
  display: grid;
  gap: 3px;
  min-width: 0;
  overflow-wrap: anywhere;
  font-size: 0.875rem;
  line-height: 1.35;
}
.notice-copy strong {
  font-size: 0.9375rem;
  font-weight: 900;
}
.notice-copy time, .notice-status {
  color: var(--muted);
  font-size: 0.8125rem;
}
.notice-copy time {
  margin-top: 2px;
  font-size: 0.75rem;
}
.notice-unread[data-kind='alert'] time {
  color: var(--local-accent-text);
}
.notice-status {
  margin: 0;
  line-height: 1.5;
}
.notice-status button {
  min-height: 44px;
}
.notice-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 3px 8px;
}
.notice-type {
  color: var(--notice-ink);
  font-size: 0.75rem;
  font-weight: 900;
}
.notice-new {
  color: var(--local-accent-text);
  font-size: 0.75rem;
  font-weight: 800;
}
.notice-new::before {
  display: inline-block;
  width: 5px;
  height: 5px;
  margin-inline-end: 4px;
  border-radius: 50%;
  background: currentColor;
  vertical-align: 2px;
  content: '';
}
.notice-arrow {
  align-self: center;
  color: var(--muted);
  rotate: -90deg;
}
.notice-empty {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 12px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--surface-soft);
}
.empty-icon {
  display: grid;
  flex: 0 0 38px;
  height: 38px;
  place-items: center;
  border-radius: 12px;
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
}
.notice-empty strong {
  display: block;
  margin-bottom: 3px;
  color: var(--text);
  font-size: 0.9375rem;
}
@media (hover: hover) {
  .notice-row:hover .notice-arrow {
    color: var(--coral);
  }
  .presence-link:hover {
    border-color: var(--lavender);
  }
}
@media (prefers-reduced-motion: no-preference) {
  .notice-arrow {
    transition: translate var(--press-duration) ease-out, color var(--press-duration) ease-out;
  }
  .notice-row:hover .notice-arrow {
    translate: 2px 0;
  }
}
</style>
