<script setup lang="ts">
import {
  darkTheme,
  esAR,
  NConfigProvider,
  NGlobalStyle,
  NMessageProvider,
  type GlobalThemeOverrides,
} from 'naive-ui'
import { RouterView } from 'vue-router'
import { onMounted, onUnmounted } from 'vue'

let layoutObserver: ResizeObserver | undefined
onMounted(() => {
  const root = document.documentElement
  const syncLayoutWidth = () => {
    // Fixed overlays use the viewport even when a drawer locks scrolling and
    // preserves the scrollbar gutter on the document.
    root.style.setProperty('--app-layout-width', `${root.getBoundingClientRect().width}px`)
  }
  syncLayoutWidth()
  layoutObserver = new ResizeObserver(syncLayoutWidth)
  layoutObserver.observe(root)
})
onUnmounted(() => {
  layoutObserver?.disconnect()
  document.documentElement.style.removeProperty('--app-layout-width')
})

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
    fontFamily: 'var(--font)',
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
      <RouterView />
    </NMessageProvider>
  </NConfigProvider>
</template>
