<script setup lang="ts">
import { reactive, shallowRef, useTemplateRef } from 'vue'
import {
  NButton,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  useMessage,
  type FormInst,
  type FormRules,
} from 'naive-ui'
import AppIcon from '../AppIcon.vue'
import AppSelect from '../AppSelect.vue'
import PhotoPicker from '../PhotoPicker.vue'
import {
  productCategories,
  productConditions,
  type NewListing,
} from '../../composables/useMarketplace'

const props = defineProps<{
  neighborhood: string
  saving: boolean
  error: string
}>()
const emit = defineEmits<{ publish: [listing: NewListing] }>()
const message = useMessage()
const form = useTemplateRef<FormInst>('product-form')
const model = reactive({
  title: '',
  price: null as number | null,
  category: null as string | null,
  condition: null as string | null,
  description: '',
  image: '',
})
const readingPhoto = shallowRef(false)
const photoError = shallowRef('')
const photo = shallowRef<File | null>(null)
const requestId = crypto.randomUUID()
const categoryOptions = productCategories.map((value) => ({
  label: value,
  value,
}))
const conditionOptions = productConditions.map((value) => ({
  label: value,
  value,
}))
const rules: FormRules = {
  title: {
    required: true,
    validator: (_rule, value: string) => value.trim().length >= 3,
    message: 'Escribe un título de al menos 3 caracteres.',
    trigger: ['input', 'blur'],
  },
  price: {
    required: true,
    type: 'number',
    min: 0,
    max: 999999,
    message: 'Ingresa un precio entre S/ 0 y S/ 999 999.',
    trigger: ['change', 'blur'],
  },
  category: {
    required: true,
    message: 'Elige una categoría.',
    trigger: 'change',
  },
  condition: {
    required: true,
    message: 'Cuéntanos en qué estado está.',
    trigger: 'change',
  },
  description: {
    required: true,
    validator: (_rule, value: string) => value.trim().length >= 10,
    message: 'Describe el producto con al menos 10 caracteres.',
    trigger: ['input', 'blur'],
  },
}

async function selectPhoto([file]: File[]) {
  if (!file || readingPhoto.value || props.saving) return
  photoError.value = ''
  if (
    !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
    file.size > 2 * 1024 * 1024
  ) {
    photoError.value = 'Elige una foto JPG, PNG o WebP de hasta 2 MB.'
    return
  }
  readingPhoto.value = true
  try {
    const imageData = await new Promise<string>((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(String(reader.result))
      reader.onerror = () => reject(new Error('No se pudo leer la foto.'))
      reader.readAsDataURL(file)
    })
    const image = new Image()
    image.src = imageData
    await image.decode()
    if (image.naturalWidth > 2400 || image.naturalHeight > 2400) {
      photoError.value = 'Usa una foto de hasta 2400 × 2400 píxeles.'
      return
    }
    photo.value = file
    model.image = imageData
  } catch {
    photoError.value = 'No pudimos abrir la foto. Inténtalo con otra imagen.'
  } finally {
    readingPhoto.value = false
  }
}

function removePhoto() {
  photo.value = null
  model.image = ''
  photoError.value = ''
}

async function submit() {
  if (props.saving || readingPhoto.value) return
  try {
    await form.value?.validate()
  } catch {
    return
  }
  if (!photo.value) {
    photoError.value = 'Agrega una foto para que tus vecinos vean el producto.'
    message.warning(photoError.value)
    return
  }
  if (
    model.price === null ||
    model.category === null ||
    model.condition === null
  )
    return
  emit('publish', {
    title: model.title,
    price: model.price,
    category: model.category,
    condition: model.condition,
    description: model.description,
    photo: photo.value,
    requestId,
  })
}
</script>

<template>
  <NForm
    ref="product-form"
    :model="model"
    :rules="rules"
    label-placement="top"
    class="product-form"
    @submit.prevent="submit"
  >
    <div class="photo-field">
      <PhotoPicker
        :previews="model.image ? [model.image] : []"
        input-label="Foto del producto"
        preview-alt="Vista previa de tu producto"
        :disabled="saving"
        :loading="readingPhoto"
        described-by="photo-help"
        :invalid="Boolean(photoError)"
        @select="selectPhoto"
        @remove="removePhoto"
      >
        <span id="photo-help">Foto del producto · JPG, PNG o WebP, hasta 2 MB.</span>
      </PhotoPicker>
      <p v-if="photoError" role="alert" class="form-error">{{ photoError }}</p>
    </div>
    <NFormItem label="¿Qué vas a vender?" path="title"
      ><NInput
        v-model:value="model.title"
        placeholder="Ej. Bicicleta de paseo en buen estado"
        :maxlength="100"
        :input-props="{ 'aria-label': 'Título del producto' }"
    /></NFormItem>
    <div class="form-columns">
      <NFormItem label="Precio en soles" path="price"
        ><NInputNumber
          v-model:value="model.price"
          :min="0"
          :max="999999"
          :precision="2"
          :show-button="false"
          placeholder="0.00"
          :input-props="{
            'aria-label': 'Precio en soles',
            inputmode: 'decimal',
          }"
          ><template #prefix>S/</template></NInputNumber
        ></NFormItem
      ><NFormItem label="Categoría" path="category"
        ><AppSelect
          v-model:value="model.category"
          :options="categoryOptions"
          placeholder="Seleccionar"
          label="Categoría del producto"
      /></NFormItem>
    </div>
    <NFormItem label="Estado del producto" path="condition"
      ><AppSelect
        v-model:value="model.condition"
        :options="conditionOptions"
        placeholder="¿En qué estado está?"
        label="Estado del producto"
    /></NFormItem>
    <NFormItem label="Cuéntanos un poco más" path="description"
      ><NInput
        v-model:value="model.description"
        type="textarea"
        :autosize="{ minRows: 4, maxRows: 8 }"
        :maxlength="2000"
        show-count
        placeholder="Describe el producto, sus detalles y cómo se puede recoger."
        :input-props="{ 'aria-label': 'Descripción del producto' }"
    /></NFormItem>
    <div class="publish-location">
      <AppIcon name="pin" :size="18" /><span
        >Se publicará en <strong>{{ neighborhood }}</strong></span
      >
    </div>
    <p v-if="error" role="alert" class="form-error">{{ error }}</p>
    <NButton
      type="primary"
      block
      size="large"
      attr-type="submit"
      :loading="saving"
      :disabled="readingPhoto"
      >Publicar producto</NButton
    >
  </NForm>
</template>
