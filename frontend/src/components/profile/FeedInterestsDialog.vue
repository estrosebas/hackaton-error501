<script setup lang="ts">
import { ref, watch } from 'vue'
import { NModal,NButton } from 'naive-ui'
import FeedTopicPicker from './FeedTopicPicker.vue'
import { currentUser,updateFeedInterests } from '../../composables/useAuth'
const show=defineModel<boolean>('show',{required:true})
const selected=ref<string[]>([]),saving=ref(false),error=ref('')
watch(show,open=>{if(open){selected.value=[...(currentUser.value?.feed_interests??[])];error.value=''}},{immediate:true})
async function save(skip=false){if(saving.value)return;saving.value=true;error.value='';try{if(await updateFeedInterests(skip?[]:selected.value))show.value=false}catch(e){error.value=e instanceof Error?e.message:'No pudimos guardar tus gustos.'}finally{saving.value=false}}
</script>
<template>
  <NModal v-model:show="show" preset="card" title="Dale corazón a lo que te gusta" class="interests-modal" :mask-closable="!saving" :closable="!saving" :close-on-esc="!saving">
    <p class="interests-intro">Un barrio, muchos gustos. Elige tus favoritos y descubre primero publicaciones recientes de tu distrito que van contigo.</p>
    <FeedTopicPicker v-model="selected" :disabled="saving" />
    <p class="interests-hint">Tus gustos son privados. Las emergencias siempre conservan su prioridad.</p>
    <p v-if="error" role="alert" class="interests-error">{{error}}</p>
    <div class="interests-actions"><NButton secondary round :disabled="saving" @click="save(true)">Ahora no</NButton><NButton type="primary" round :loading="saving" @click="save(false)">Ver mi feed</NButton></div>
  </NModal>
</template>
<style scoped>
.interests-intro,.interests-hint{font-size:.875rem;line-height:1.6;color:var(--muted)}.interests-hint{font-size:.75rem;margin-top:18px}.interests-error{color:var(--local-accent-text);font-size:.8125rem}.interests-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}:global(.interests-modal){width:min(94vw,560px)!important;max-height:90dvh;overflow-y:auto;background:var(--surface)!important;border:1px solid var(--line)!important;border-radius:22px!important}
</style>
