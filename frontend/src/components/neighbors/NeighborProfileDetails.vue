<script setup lang="ts">
import { NTag } from 'naive-ui'
import AppIcon from '../AppIcon.vue'

defineProps<{
  bio?: string | null
  skills?: string[]
  interests?: string[]
  availability?: string | null
  canHelp?: boolean
}>()
</script>

<template>
  <div v-if="bio || skills?.length || interests?.length || availability || canHelp" class="neighbor-profile-details">
    <section v-if="bio">
      <h3>Un poquito sobre mí</h3>
      <p>{{ bio }}</p>
    </section>
    <section v-if="skills?.length">
      <h3><AppIcon name="hand" :size="18" /> Puedo echarte una mano con</h3>
      <div class="profile-tags">
        <NTag v-for="skill in skills" :key="skill" round :bordered="false" type="primary">{{ skill }}</NTag>
      </div>
    </section>
    <section v-if="interests?.length">
      <h3><AppIcon name="calendar" :size="18" /> Me apunto a</h3>
      <div class="profile-tags">
        <NTag v-for="interest in interests" :key="interest" round :bordered="false">{{ interest }}</NTag>
      </div>
    </section>
    <section v-if="availability || canHelp" class="profile-availability">
      <div v-if="availability">
        <h3><AppIcon name="clock" :size="18" /> Disponibilidad</h3>
        <p>{{ availability }}</p>
      </div>
      <NTag v-if="canHelp" round :bordered="false" type="success">Puede ayudar</NTag>
    </section>
  </div>
</template>

<style scoped>
.neighbor-profile-details {
  display: grid;
  gap: 18px;
  margin-top: 18px;
  text-align: start;
  overflow-wrap: anywhere;
}
.neighbor-profile-details h3 {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 7px;
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 900;
}
.neighbor-profile-details h3 svg {
  color: var(--coral);
}
.neighbor-profile-details p {
  margin: 0;
  color: var(--muted);
  font-size: 0.875rem;
  line-height: 1.6;
  white-space: pre-wrap;
}
.profile-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}
.profile-availability {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px;
  border-radius: 12px;
  background: var(--surface-raised);
}
</style>
