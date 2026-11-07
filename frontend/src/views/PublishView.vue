<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { NTabs, NTabPane, useMessage } from 'naive-ui'
import { useRoute, useRouter } from 'vue-router'
import PostComposer from '../components/community/PostComposer.vue'
import ProductForm from '../components/marketplace/ProductForm.vue'
import { useCommunity } from '../composables/useCommunity'
import { useMarketplace, type NewListing } from '../composables/useMarketplace'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const { neighborhood, addPost } = useCommunity()
const { addListing } = useMarketplace()
const saving = shallowRef(false)
const error = shallowRef('')
const kind = computed(() =>
  route.name === 'create-product' ? 'producto' : 'publicacion',
)

function changeKind(value: string) {
  router.replace({ name: value === 'producto' ? 'create-product' : 'create' })
}

function publishPost(text: string) {
  addPost(text)
  message.success('Tu publicación ya está en el muro')
  router.push({ name: 'home' })
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
      ><NTabPane name="publicacion" tab="Al barrio"
        ><PostComposer
          :neighborhood="neighborhood"
          @publish="publishPost" /></NTabPane
      ><NTabPane name="producto" tab="Vender algo"
        ><ProductForm
          :neighborhood="neighborhood"
          :saving="saving"
          :error="error"
          @publish="publishProduct" /></NTabPane
    ></NTabs>
  </section>
</template>
