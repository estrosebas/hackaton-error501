<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { NTabs, NTabPane, useMessage } from 'naive-ui'
import { useRoute, useRouter } from 'vue-router'
import PostComposer from '../components/community/PostComposer.vue'
import ProductForm from '../components/marketplace/ProductForm.vue'
import { useCommunity, type ActivityDetails } from '../composables/useCommunity'
import { useMarketplace, type NewListing } from '../composables/useMarketplace'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const { neighborhood, addPost } = useCommunity()
const { addListing } = useMarketplace()
const saving = shallowRef(false)
const error = shallowRef('')
const tabs = ['feed-create', 'create-activity', 'create-product']
const kind = computed(() => String(route.name))

function changeKind(value: string) {
  if (saving.value || !tabs.includes(value)) return
  error.value = ''
  router.replace({ name: value })
}

async function publishPost(text: string, type: string, photos: Blob[], activity?: ActivityDetails, topics: string[] = []) {
  if (saving.value) return
  saving.value = true
  error.value = ''
  try {
    await addPost(text, type, photos, activity, topics)
    message.success(activity ? 'Tu actividad ya está en Inicio' : 'Tu publicación ya está en la comunidad')
    await router.push({ name: 'home' })
  } catch (e) {
    error.value =
      e instanceof Error
        ? e.message
        : 'No pudimos publicar. Tu texto se conserva para reintentar.'
  } finally {
    saving.value = false
  }
}

async function publishProduct(listing: NewListing) {
  if (saving.value) return
  saving.value = true
  error.value = ''
  try {
    addListing(listing)
    message.success('Tu producto ya está en el mercadito')
    await router.push({ name: 'market-mine' })
  } catch {
    error.value =
      'No pudimos guardar el anuncio. Prueba con una foto más pequeña y revisa que el navegador permita guardar datos.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="standard-page publish-page">
    <div class="page-heading">
      <div>
        <h1>Comparte algo bueno</h1>
        <p>Una novedad, una pregunta o una segunda vida.</p>
      </div>
    </div>
    <NTabs :value="kind" type="segment" animated @update:value="changeKind"
      ><NTabPane name="feed-create" tab="Al barrio"
        ><PostComposer
          :neighborhood="neighborhood"
          :saving="saving"
          :error="error"
          @publish="publishPost" /></NTabPane
      ><NTabPane name="create-activity" tab="Actividad"
        ><PostComposer
          activity
          :neighborhood="neighborhood"
          :saving="saving"
          :error="error"
          @publish="publishPost" /></NTabPane
      ><NTabPane name="create-product" tab="Vender algo"
        ><ProductForm
          :neighborhood="neighborhood"
          :saving="saving"
          :error="error"
          @publish="publishProduct" /></NTabPane
    ></NTabs>
  </section>
</template>
