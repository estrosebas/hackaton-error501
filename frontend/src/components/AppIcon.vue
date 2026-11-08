<script setup lang="ts">
export type IconName =
  | 'home'
  | 'send'
  | 'bell'
  | 'chevron'
  | 'smile'
  | 'plus'
  | 'users'
  | 'shop'
  | 'more'
  | 'pin'
  | 'arrow'
  | 'check'
  | 'calendar'
  | 'bookmark'
  | 'close'
  | 'leaf'
  | 'chat'
  | 'search'
  | 'camera'
  | 'map'
  | 'list'
  | 'hand'
  | 'locate'
  | 'minus'
  | 'clock'
  | 'user'
  | 'mail'
  | 'lock'
  | 'eye'
  | 'eye-off'
  | 'refresh'
  | 'heart'
  | 'log-out'
  | 'edit'
  | 'sliders'

defineProps<{ name: IconName; size?: number }>()

const paths: Record<IconName, string[]> = {
  home: ['M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z'],
  send: ['m3 3 19 9-19 9 4-9-4-9Z', 'M7 12h15'],
  bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M10 21h4'],
  chevron: ['m7 10 5 5 5-5'],
  smile: [
    'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0',
    'M8 14s1 3 4 3 4-3 4-3',
    'M8 9h.01M16 9h.01',
  ],
  plus: ['M12 5v14M5 12h14'],
  users: [
    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
    'M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    'M18 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87',
  ],
  shop: [
    'M3 10h18l-2-7H5l-2 7Z',
    'M4 10v11h16V10M9 21v-7h6v7M8 3l-1 7M16 3l1 7',
    'M3 10a3 3 0 0 0 4.5 2.6A3 3 0 0 0 12 12a3 3 0 0 0 4.5.6A3 3 0 0 0 21 10',
  ],
  more: ['M5 12h.01M12 12h.01M19 12h.01'],
  pin: [
    'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z',
    'M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
  ],
  arrow: ['M5 12h14m-5-5 5 5-5 5'],
  check: ['m5 12 4 4L19 6'],
  calendar: ['M4 5h16v16H4ZM8 3v4M16 3v4M4 11h16M8 15h2M14 15h2'],
  bookmark: ['M6 3h12v19l-6-4-6 4Z'],
  close: ['m6 6 12 12M6 18 18 6'],
  leaf: ['M20 3C9 2 3 8 5 15s14 8 15-12Z', 'M4 21 15 10'],
  chat: [
    'M21 11.5a8.5 8.5 0 0 1-8.5 8.5 10 10 0 0 1-4-.8L3 21l1.8-5.5a10 10 0 0 1-.8-4A8.5 8.5 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5Z',
  ],
  search: ['M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0', 'm15 15 6 6'],
  camera: ['M3 7h4l2-3h6l2 3h4v14H3Z', 'M16 13a4 4 0 1 1-8 0 4 4 0 0 1 8 0'],
  map: ['m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2ZM9 3v16M15 5v16'],
  list: ['M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
  hand: [
    'M8 13V6a1.5 1.5 0 0 1 3 0v5-7a1.5 1.5 0 0 1 3 0v7-5a1.5 1.5 0 0 1 3 0v6-3a1.5 1.5 0 0 1 3 0v5a7 7 0 0 1-7 7h-1a5 5 0 0 1-4-2l-4-5a1.7 1.7 0 0 1 2.6-2.1L8 14',
  ],
  locate: [
    'M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0M12 2v3M12 19v3M2 12h3M19 12h3',
    'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
  ],
  minus: ['M5 12h14'],
  clock: ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0', 'M12 7v5l3 2'],
  user: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0'],
  mail: ['M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z', 'm2 7 10 6 10-6'],
  lock: ['M5 11h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z', 'M8 11V7a4 4 0 0 1 8 0v4'],
  eye: ['M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z', 'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0'],
  'eye-off': ['m3 3 18 18', 'M10.5 6.1c.5-.1 1-.1 1.5-.1 6.4 0 10 6 10 6a16 16 0 0 1-2.5 2.8M6.2 7.8A16 16 0 0 0 2 12s3.6 6 10 6c1.7 0 3.2-.4 4.5-1.1'],
  refresh: ['M20 7v5h-5', 'M20 12a8 8 0 1 0-2.4 5.7'],
  heart: ['M20.8 8.6c0 4.2-8.8 10-8.8 10s-8.8-5.8-8.8-10A4.5 4.5 0 0 1 12 7.1a4.5 4.5 0 0 1 8.8 1.5Z'],
  'log-out': ['M10 17v4H4V3h6v4', 'M9 12h12m-4-4 4 4-4 4'],
  edit: ['M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7', 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z'],
  sliders: ['M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6'],
}
</script>

<template>
  <svg
    :width="size ?? 24"
    :height="size ?? 24"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="name === 'more' ? 3.5 : 1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <path v-for="(path, index) in paths[name]" :key="index" :d="path" />
  </svg>
</template>
