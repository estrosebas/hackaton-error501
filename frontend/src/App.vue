<script setup lang="ts">
import { computed, shallowRef, watchEffect } from 'vue'
import {
  NConfigProvider,
  NDialogProvider,
  NGlobalStyle,
  NLayout,
  NLayoutContent,
  NMessageProvider,
  NNotificationProvider,
  darkTheme,
  type GlobalThemeOverrides,
} from 'naive-ui'
import AppHeader from './components/layout/AppHeader.vue'
import AppSidebar from './components/layout/AppSidebar.vue'
import ComingSoon from './views/ComingSoon.vue'
import DashboardView from './views/DashboardView.vue'

type ThemeMode = 'light' | 'dark'

const navLabels: Record<string, string> = {
  dashboard: 'Dashboard',
  reportes: 'Reportes',
  equipo: 'Equipo',
  config: 'Configuración',
}

const storedTheme = localStorage.getItem('error501-theme')
const themeMode = shallowRef<ThemeMode>(storedTheme === 'light' ? 'light' : 'dark')
const activeNav = shallowRef('dashboard')

const theme = computed(() => (themeMode.value === 'dark' ? darkTheme : null))
const pageTitle = computed(() => navLabels[activeNav.value] ?? 'Dashboard')

const themeOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#6366f1',
    primaryColorHover: '#818cf8',
    primaryColorPressed: '#4f46e5',
    primaryColorSuppl: '#818cf8',
    borderRadius: '10px',
    fontFamily:
      "'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
  },
}

watchEffect(() => {
  localStorage.setItem('error501-theme', themeMode.value)
  document.documentElement.dataset.theme = themeMode.value
})

function toggleTheme() {
  themeMode.value = themeMode.value === 'dark' ? 'light' : 'dark'
}
</script>

<template>
  <NConfigProvider :theme="theme" :theme-overrides="themeOverrides">
    <NGlobalStyle />
    <NMessageProvider>
      <NDialogProvider>
        <NNotificationProvider>
          <NLayout has-sider class="app-shell">
            <AppSidebar v-model:active="activeNav" />

            <NLayout class="app-body">
              <AppHeader :title="pageTitle" :theme-mode="themeMode" @toggle-theme="toggleTheme" />

              <NLayoutContent
                class="app-content"
                :native-scrollbar="false"
                content-style="padding: 26px 32px 56px;"
              >
                <DashboardView v-if="activeNav === 'dashboard'" />
                <ComingSoon v-else :module-name="pageTitle" @back="activeNav = 'dashboard'" />
              </NLayoutContent>
            </NLayout>
          </NLayout>
        </NNotificationProvider>
      </NDialogProvider>
    </NMessageProvider>
  </NConfigProvider>
</template>

<style scoped>
.app-shell {
  height: 100vh;
}

.app-body {
  height: 100%;
}

.app-content {
  height: calc(100vh - 72px);
}
</style>
