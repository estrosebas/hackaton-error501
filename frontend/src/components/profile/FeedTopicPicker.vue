<script setup lang="ts">
import AppIcon from '../AppIcon.vue'
import { useFeedTopics } from '../../composables/useFeedTopics'
const props=withDefaults(defineProps<{max?:number;disabled?:boolean;compact?:boolean}>(),{max:8})
const selected=defineModel<string[]>({required:true})
const {topics,error,loading,retry}=useFeedTopics()
function toggle(id:string){if(props.disabled)return;selected.value=selected.value.includes(id)?selected.value.filter(value=>value!==id):selected.value.length<props.max?[...selected.value,id]:selected.value}
</script>
<template>
  <div class="feed-topic-picker" :class="{'topic-picker-compact':compact}">
    <p v-if="loading" role="status">Cargando gustos…</p>
    <p v-if="error" role="alert">{{error}} <button type="button" class="text-link" @click="retry">Reintentar</button></p>
    <div class="topic-grid" role="group" aria-label="Categorías que te gustan">
      <button v-for="topic in topics" :key="topic.id" type="button" class="topic-tile" :aria-pressed="selected.includes(topic.id)" :aria-label="`Me gusta ${topic.label}`" :disabled="disabled||(!selected.includes(topic.id)&&selected.length>=max)" @click="toggle(topic.id)">
        <span class="topic-emoji" aria-hidden="true">{{topic.emoji}}</span><strong>{{topic.label}}</strong><AppIcon class="topic-heart" name="heart" :size="compact?14:18" />
      </button>
    </div>
    <p class="topic-counter">{{selected.length}} de {{max}} · Puedes cambiarlos cuando quieras.</p>
  </div>
</template>
<style scoped>
.topic-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.topic-tile{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding:14px;border:1px solid var(--line);border-radius:17px;background:var(--surface-soft);color:var(--lavender);text-align:left;min-height:84px}.topic-tile strong{flex:1;font-size:.8125rem;overflow-wrap:anywhere}.topic-emoji{font-size:1.55rem}.feed-topic-picker:not(.topic-picker-compact) .topic-tile strong{order:3;flex-basis:100%;overflow-wrap:normal}.feed-topic-picker:not(.topic-picker-compact) .topic-tile .topic-heart{margin-left:auto}.topic-tile[aria-pressed="true"]{background:var(--local-accent-fill);border-color:var(--local-accent-text);color:var(--local-accent-text)}.topic-tile[aria-pressed="true"] :deep(.topic-heart){fill:currentColor}.topic-tile:disabled{opacity:.45;cursor:default}.topic-counter{font-size:.75rem;color:var(--muted);margin:12px 0 0}.topic-picker-compact .topic-grid{display:flex;flex-wrap:wrap;gap:7px}.topic-picker-compact .topic-tile{min-height:36px;padding:7px 10px;border-radius:22px;flex-wrap:nowrap;gap:6px}.topic-picker-compact .topic-emoji{font-size:1rem}.topic-picker-compact .topic-tile strong{font-size:.75rem}
</style>


