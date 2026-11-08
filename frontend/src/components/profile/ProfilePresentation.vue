<script setup lang="ts">
import { computed, useId } from 'vue'
import NeighborProfileDetails from '../neighbors/NeighborProfileDetails.vue'
import type { NeighborProfile } from '../../composables/useAuth'

const props = defineProps<{
  title: string
  description: string
  emptyMessage: string
  profile?: Partial<NeighborProfile> | null
}>()
const headingId = useId()
const bio = computed(() => props.profile?.bio?.trim())
const availability = computed(() => props.profile?.availability?.trim())
const hasDetails = computed(() => Boolean(
  bio.value || props.profile?.skills?.length || props.profile?.interests?.length ||
  availability.value || props.profile?.can_help,
))
</script>

<template>
  <section :aria-labelledby="headingId">
    <div class="presentation-heading">
      <h2 :id="headingId">{{ title }}</h2>
      <slot name="action" />
    </div>
    <p class="profile-hint">{{ description }}</p>
    <slot>
      <NeighborProfileDetails
        v-if="hasDetails"
        :bio="bio"
        :skills="profile?.skills"
        :interests="profile?.interests"
        :availability="availability"
        :can-help="profile?.can_help"
      />
      <p v-else class="profile-empty">{{ emptyMessage }}</p>
    </slot>
  </section>
</template>

<style scoped>
.presentation-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 6px 12px;
}

h2 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 850;
}

.profile-hint,
.profile-empty {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 0.875rem;
  line-height: 1.55;
}

.profile-empty {
  margin-top: 14px;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-soft);
}
</style>
