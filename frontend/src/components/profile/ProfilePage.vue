<script setup lang="ts">
import { useId } from 'vue'

defineProps<{ title: string; description: string }>()
const headingId = useId()
</script>

<template>
  <section class="profile-page" :aria-labelledby="headingId">
    <header class="profile-heading">
      <div>
        <h1 :id="headingId">{{ title }}</h1>
        <p>{{ description }}</p>
      </div>
      <slot name="heading-action" />
    </header>

    <slot />

    <div
      v-if="$slots.presentation || $slots.sidebar"
      class="profile-content"
      :class="{ 'has-sidebar': $slots.presentation && $slots.sidebar }"
    >
      <div v-if="$slots.presentation" class="profile-copy">
        <slot name="presentation" />
      </div>
      <div v-if="$slots.sidebar" class="profile-sidebar">
        <slot name="sidebar" />
      </div>
    </div>

    <slot name="footer" />
  </section>
</template>

<style scoped>
.profile-page {
  padding: 22px 18px 24px;
}

.profile-page :deep(:is(a, button)) {
  scroll-margin-block-end: calc(96px + env(safe-area-inset-bottom, 0px));
}

.profile-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 16px;
  margin-bottom: 18px;
}

.profile-heading h1 {
  margin: 0;
  font-size: 1.875rem;
  font-weight: 900;
  letter-spacing: -0.035em;
  line-height: 1.15;
}

.profile-heading p {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 0.875rem;
  line-height: 1.55;
}

.profile-content {
  display: grid;
  gap: 24px;
  margin-top: 24px;
}

.profile-copy,
.profile-sidebar {
  min-width: 0;
}

.has-sidebar .profile-copy {
  padding-bottom: 18px;
  border-bottom: 1px solid var(--line);
}

@media (max-width: 359px) {
  .profile-page {
    padding-inline: 14px;
  }
}

@media (min-width: 760px) {
  .profile-page {
    padding: 30px 32px 24px;
  }

  .profile-heading h1 {
    font-size: 2.25rem;
  }

  .profile-content.has-sidebar {
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
    gap: 28px;
  }

  .has-sidebar .profile-copy {
    padding: 0 26px 0 0;
    border: 0;
    border-inline-end: 1px solid var(--line);
  }
}
</style>
