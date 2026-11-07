<script setup lang="ts">
import { reactive, shallowRef, useTemplateRef } from 'vue'
import {
  NButton,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NSelect,
  useMessage,
  type FormInst,
  type FormRules,
} from 'naive-ui'
import AppIcon from '../AppIcon.vue'
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
const photoInput = useTemplateRef<HTMLInputElement>('photo-input')
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

async function selectPhoto(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  photoError.value = ''
  input.value = ''
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
    model.image = imageData
  } catch {
    photoError.value = 'No pudimos abrir la foto. Inténtalo con otra imagen.'
  } finally {
    readingPhoto.value = false
  }
}

async function submit() {
  if (props.saving || readingPhoto.value) return
  try {
    await form.value?.validate()
  } catch {
    return
  }
  if (!model.image) {
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
    ...model,
    price: model.price,
    category: model.category,
    condition: model.condition,
    neighborhood: props.neighborhood,
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
      <input
        ref="photo-input"
        class="visually-hidden"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        aria-label="Foto del producto"
        tabindex="-1"
        @change="selectPhoto"
      />
      <button
        class="photo-upload"
        :class="{ 'has-photo': model.image }"
        type="button"
        :disabled="readingPhoto || saving"
        aria-describedby="photo-help"
        :aria-invalid="Boolean(photoError)"
        @click="photoInput?.click()"
      >
        <img
          v-if="model.image"
          :src="model.image"
          alt="Vista previa de tu producto"
        />
        <span v-else class="photo-upload-icon"
          ><AppIcon name="camera" :size="30"
        /></span>
        <span class="photo-upload-label">{{
          readingPhoto
            ? 'Abriendo foto…'
            : model.image
              ? 'Cambiar foto'
              : 'Agrega una foto del producto'
        }}</span>
      </button>
      <p id="photo-help" class="field-help">
        Una buena foto ayuda. JPG, PNG o WebP, hasta 2 MB.
      </p>
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
        ><NSelect
          v-model:value="model.category"
          :options="categoryOptions"
          placeholder="Seleccionar"
          aria-label="Categoría del producto"
      /></NFormItem>
    </div>
    <NFormItem label="Estado del producto" path="condition"
      ><NSelect
        v-model:value="model.condition"
        :options="conditionOptions"
        placeholder="¿En qué estado está?"
        aria-label="Estado del producto"
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
    <p class="marketplace-demo-note">
      Tu anuncio quedará guardado en este dispositivo.
    </p>
  </NForm>
</template>
