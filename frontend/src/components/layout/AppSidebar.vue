<script setup lang="ts">
import { NAvatar, NLayoutSider, NMenu, NTag, type MenuOption } from 'naive-ui'

const active = defineModel<string>('active', { required: true })

const menuOptions: MenuOption[] = [
  { label: 'Dashboard', key: 'dashboard' },
  { label: 'Reportes', key: 'reportes' },
  { label: 'Equipo', key: 'equipo' },
  { label: 'Configuración', key: 'config' },
]
</script>

<template>
  <NLayoutSider
    bordered
    :width="252"
    class="sidebar"
    content-style="display: flex; flex-direction: column; height: 100%; padding: 22px 16px 18px;"
  >
    <div class="brand">
      <span class="brand-mark">501</span>
      <div class="brand-text">
        <p class="brand-name">Error 501</p>
        <p class="brand-sub">Hackathon Console</p>
      </div>
    </div>

    <NMenu v-model:value="active" :options="menuOptions" class="sidebar-menu" />

    <div class="sidebar-footer">
      <div class="team">
        <NAvatar round size="small" class="team-avatar">NM</NAvatar>
        <div class="team-text">
          <p class="team-name">Equipo 501</p>
          <p class="team-role">3 colaboradores</p>
        </div>
        <NTag size="tiny" round :bordered="false" type="success">online</NTag>
      </div>
      <p class="version">Vue 3 · Naive UI · Vite</p>
    </div>
  </NLayoutSider>
</template>

<style scoped>
.sidebar {
  background: linear-gradient(180deg, rgba(99, 102, 241, 0.12), transparent 42%);
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 2px 6px 22px;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 13px;
  background: linear-gradient(135deg, #6366f1, #a855f7);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: -0.02em;
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
}

.brand-text {
  display: flex;
  flex-direction: column;
}

.brand-name {
  margin: 0;
  font-size: 15px;
  font-weight: 650;
  line-height: 1.2;
}

.brand-sub {
  margin: 0;
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.55;
}

.sidebar-menu {
  flex: 1;
}

.sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid rgba(148, 163, 184, 0.18);
}

.team {
  display: flex;
  align-items: center;
  gap: 9px;
}

.team-avatar {
  background: linear-gradient(135deg, #6366f1, #22d3ee);
  font-size: 11px;
  font-weight: 700;
}

.team-text {
  flex: 1;
  min-width: 0;
}

.team-name {
  margin: 0;
  font-size: 12.5px;
  font-weight: 600;
}

.team-role {
  margin: 0;
  font-size: 11px;
  opacity: 0.55;
}

.version {
  margin: 0;
  font-size: 10.5px;
  letter-spacing: 0.04em;
  opacity: 0.4;
}
</style>
