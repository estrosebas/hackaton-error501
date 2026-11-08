<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, shallowRef, useTemplateRef, watch } from 'vue'
import ChatInbox from '../components/chat/ChatInbox.vue'
import ChatThread from '../components/chat/ChatThread.vue'
import ChatDialogs from '../components/chat/ChatDialogs.vue'
import { useChats } from '../composables/useChats'

const {
  roomId, rooms, room, messages, thread, draft, sending, sendError, atLatest,
  listLoading, listReady, listError, nextPage, opening, threadError, historyLoading,
  historyError, older, dialog, dialogBusy, dialogError, search, neighbors,
  directoryLoading, directoryError, blocked, blockedLoading, canInvite,
  list, openRoom, poll, trackPosition, scrollLatest, moreMessages, send, openDialog,
  loadBlocked, retryDirectory, openDirect, createGroup, invite, block, leave,
} = useChats()

const workspace = useTemplateRef<HTMLElement>('workspace')
const compact = matchMedia('(max-width: 759px)')
const viewport = window.visualViewport
const visibleHeight = shallowRef<string>()

function resize() {
  visibleHeight.value = viewport?.scale === 1 ? `${Math.round(viewport.height)}px` : undefined
  if (compact.matches && roomId.value && atLatest.value) void scrollLatest()
}

watch(roomId, async (id) => {
  if (!compact.matches) return
  await nextTick()
  workspace.value?.querySelector<HTMLElement>(id ? '.thread-back' : '#inbox-title')?.focus({ preventScroll: true })
})

onMounted(() => {
  resize()
  viewport?.addEventListener('resize', resize)
})
onBeforeUnmount(() => viewport?.removeEventListener('resize', resize))
</script>

<template>
  <div
    ref="workspace"
    class="chats-workspace"
    :class="{ 'has-thread': roomId !== null }"
    :style="{ '--chat-visible-height': visibleHeight }"
  >
    <ChatInbox
      :rooms="rooms"
      :selected="roomId"
      :loading="listLoading"
      :ready="listReady"
      :error="listError"
      :has-more="nextPage !== null"
      @create="openDialog('create')"
      @blocked="openDialog('blocked')"
      @more="list('more')"
      @retry="list('refresh')"
    />
    <ChatThread
      v-model:draft="draft"
      :room="room"
      :selected="roomId"
      :messages="messages"
      :opening="opening"
      :error="threadError"
      :history-error="historyError"
      :send-error="sendError"
      :history-loading="historyLoading"
      :has-older="older !== null"
      :at-latest="atLatest"
      :sending="sending"
      @history-element="thread = $event"
      @settings="openDialog('members')"
      @send="send"
      @older="moreMessages"
      @latest="scrollLatest"
      @scroll="trackPosition"
      @retry="room ? poll() : openRoom()"
    />
    <ChatDialogs
      v-model:search="search"
      :active="dialog"
      :room="room"
      :neighbors="neighbors"
      :blocked="blocked"
      :busy="dialogBusy"
      :error="dialogError"
      :directory-loading="directoryLoading"
      :directory-error="directoryError"
      :blocked-loading="blockedLoading"
      :can-invite="canInvite"
      @close="dialog = null"
      @direct="openDirect($event)"
      @group="createGroup"
      @invite="invite"
      @block="block"
      @leave="leave"
      @retry-blocked="loadBlocked"
      @retry-directory="retryDirectory"
    />
  </div>
</template>

<style scoped>
.chats-workspace {
  display: grid;
  flex: 1;
  min-width: 0;
  min-height: 0;
  grid-template-columns: minmax(0, 1fr);
}

@media (min-width: 760px) {
  .chats-workspace {
    grid-template-columns: clamp(290px, 33%, 350px) minmax(0, 1fr);
    min-height: 270px;
    margin: 16px 18px 12px;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 14px;
  }

  .chat-inbox {
    border-inline-end: 1px solid var(--line);
  }
}

@media (max-width: 759px) {
  .chats-workspace.has-thread {
    height: var(--chat-visible-height, 100dvh);
  }

  .chats-workspace:not(.has-thread) .chat-thread,
  .chats-workspace.has-thread .chat-inbox {
    display: none;
  }
}
</style>
