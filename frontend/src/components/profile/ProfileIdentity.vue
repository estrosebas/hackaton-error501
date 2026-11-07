<script setup lang="ts">
import { useId } from 'vue'
import AppIcon from '../AppIcon.vue'

defineProps<{ name: string; district: string }>()
const headingId = useId()
</script>

<template>
  <section class="profile-identity" :class="{ 'has-action': $slots.action }" :aria-labelledby="headingId">
    <svg class="district-lines" viewBox="0 0 270 160" fill="none" aria-hidden="true">
      <path d="m15 169 32-47 47-10 37-49 59-12 37-44m-162 166 25-35 51-12 34-46 62-13 37-46m-158 155 19-25 50-11 35-47 60-12M99-14l71 74 26 52 54 51M158-19l74 75 26 51 43 38M38-12l58 57 37 61 37 58" />
      <circle cx="174" cy="80" r="4" />
    </svg>
    <div class="profile-person">
      <div class="profile-portrait" aria-hidden="true">
        <slot name="avatar" :size="72" />
      </div>
      <div class="profile-person-text">
        <h2 :id="headingId">{{ name }}</h2>
        <p><AppIcon name="pin" :size="16" /> {{ district }}</p>
      </div>
    </div>
    <div v-if="$slots.action" class="profile-action">
      <slot name="action" />
    </div>
  </section>
</template>

<style scoped>
.profile-identity {
  position: relative;
  isolation: isolate;
  display: grid;
  align-items: center;
  gap: 16px 24px;
  padding: 20px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--surface-soft);
}

.district-lines {
  position: absolute;
  z-index: -1;
  inset: 0 0 auto auto;
  width: min(62%, 270px);
  height: 100%;
  overflow: hidden;
  border-radius: inherit;
  color: var(--lavender);
  stroke: currentColor;
  opacity: 0.16;
  pointer-events: none;
}

.district-lines circle {
  fill: var(--coral);
}

.profile-person {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
}

.profile-portrait {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  padding: 4px;
  border: 1px solid var(--local-accent-line);
  border-radius: 50%;
}

.profile-portrait :deep(.n-avatar) {
  font-size: 1.625rem;
  background: var(--local-accent-fill);
  color: var(--local-accent-text);
}

.profile-person-text {
  min-width: 0;
  overflow-wrap: anywhere;
}

.profile-person h2 {
  margin: 0;
  font-size: 1.375rem;
  font-weight: 900;
  letter-spacing: -0.025em;
  line-height: 1.18;
}

.profile-person p {
  display: flex;
  align-items: baseline;
  gap: 5px;
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 0.875rem;
  line-height: 1.4;
}

.profile-person p svg {
  align-self: center;
  color: var(--local-accent-text);
}

.profile-action {
  display: grid;
}

.profile-action :deep(.n-button) {
  min-height: 44px;
  padding-inline: 12px;
  font-weight: 800;
}

@media (min-width: 760px) {
  .profile-identity {
    padding: 24px;
  }

  .profile-identity.has-action {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .profile-person h2 {
    font-size: 1.75rem;
  }
}

@media (max-width: 359px) {
  .profile-identity {
    padding: 14px;
  }

  .profile-person {
    gap: 10px;
  }

  .profile-portrait :deep(.n-avatar) {
    width: 56px;
    height: 56px;
    font-size: 1.375rem;
  }

  .profile-person h2 {
    font-size: 1.25rem;
  }
}
</style>
