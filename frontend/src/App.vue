<script setup lang="ts">
import {
  darkTheme,
  esAR,
  NConfigProvider,
  NGlobalStyle,
  NMessageProvider,
  type GlobalThemeOverrides,
} from 'naive-ui'
import AppShell from './components/layout/AppShell.vue'

const themeOverrides: GlobalThemeOverrides = {
  common: {
    primaryColor: '#ff6389',
    primaryColorHover: '#ff8aa6',
    primaryColorPressed: '#ed4772',
    bodyColor: '#0d1020',
    cardColor: '#1a1e35',
    modalColor: '#1a1e35',
    popoverColor: '#242940',
    textColorBase: '#f4f3ff',
    fontFamily: "'Nunito Sans', sans-serif",
    borderRadius: '14px',
  },
  Drawer: { color: '#15192d' },
}
</script>

<template>
  <NConfigProvider
    :theme="darkTheme"
    :theme-overrides="themeOverrides"
    :locale="esAR"
  >
    <NGlobalStyle />
    <NMessageProvider placement="top">
      <AppShell />
    </NMessageProvider>
  </NConfigProvider>
</template>
