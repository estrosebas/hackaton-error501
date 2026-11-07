<script setup>
import axios from 'axios';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useCapturaRostro } from '../composables/useCapturaRostro';

const emit = defineEmits(['validated']);
const { video: videoRef, foto, encendida, detectando, errorDetector, detalleErrorDetector,
    error: errorCamara, guia, progreso, cuenta, encender, reintentar, detener,
    camaras, camaraSeleccionada, iniciando, cambiarCamara } = useCapturaRostro();
const state = ref('pending');
const message = ref('');
const format = ref({ width: 1, height: 1 });
let validation = null;
const frameStyle = computed(() => ({
    '--frame-width': `${Math.min(1, format.value.height / format.value.width) * 100}%`,
    '--frame-height': `${Math.min(1, format.value.width / format.value.height) * 100}%`,
}));
function setFormat(event) {
    format.value = { width: event.target.videoWidth || 1, height: event.target.videoHeight || 1 };
}
async function validate() {
    if (!foto.value) return;
    validation?.abort();
    const controller = new AbortController();
    validation = controller;
    const image = foto.value;
    state.value = 'checking';
    message.value = 'Revisando tu fotografía…';
    emit('validated', null);
    try {
        const { data } = await axios.post('/api/face/check', { foto: image }, { signal: controller.signal, timeout: 20000 });
        if (controller.signal.aborted || foto.value !== image) return;
        if (data.accepted !== true) throw new Error('Respuesta inesperada');
        state.value = 'accepted';
        message.value = 'Tu fotografía está lista.';
        emit('validated', image);
    } catch (error) {
        if (controller.signal.aborted || foto.value !== image) return;
        state.value = 'error';
        message.value = error.response?.data?.errors?.foto?.join(' ') || error.response?.data?.message || 'No pudimos revisar la foto. Vuelve a intentar.';
    }
}
watch(foto, (image) => {
    validation?.abort(); emit('validated', null); state.value = 'pending'; message.value = '';
    if (image) validate();
});
onMounted(encender);
onBeforeUnmount(() => { validation?.abort(); detener(); });
</script>

<template>
    <div class="capture">
        <div class="camera" :class="{ 'camera-ready': state === 'accepted' }">
            <video v-if="!foto" ref="videoRef" autoplay muted playsinline @loadedmetadata="setFormat" />
            <img v-else :src="foto" alt="Tu fotografía capturada" />
            <div v-if="!foto" class="camera-frame" :style="frameStyle"><div class="camera-oval"></div></div>
            <div v-if="!foto" class="camera-guide" role="status" aria-live="polite">{{ iniciando ? 'Permite el acceso a tu cámara' : errorCamara ? 'Revisa el permiso de la cámara' : !encendida ? 'Preparando la cámara…' : !detectando && !errorDetector ? 'Preparando la revisión del rostro…' : guia.mensaje }}</div>
            <div v-if="cuenta > 0 && !foto" class="camera-progress" role="progressbar" aria-label="Estabilidad de la captura" :aria-valuenow="Math.round(progreso * 100)" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${progreso * 100}%` }"></span></div>
            <span v-if="state === 'accepted'" class="camera-badge">✓ Foto lista</span>
        </div>
        <p class="hint">Mira de frente, con buena luz y sin lentes ni mascarilla. La foto se toma automáticamente cuando el encuadre está listo.</p>
        <p v-if="errorCamara" class="notice error" role="alert">{{ errorCamara }}</p>
        <p v-if="message" class="notice" :class="{ error: state === 'error', success: state === 'accepted' }" role="status">{{ message }}</p>
        <div class="camera-actions">
            <label v-if="camaras.length > 1" class="camera-select">Cámara<select :value="camaraSeleccionada" :disabled="iniciando || state === 'checking'" @change="cambiarCamara($event.target.value)"><option v-for="camera in camaras" :key="camera.id" :value="camera.id">{{ camera.nombre }}</option></select></label>
            <button v-if="foto" class="button secondary" type="button" :disabled="state === 'checking'" @click="reintentar">Tomar otra foto</button>
            <button v-if="state === 'error' && foto" class="button" type="button" @click="validate">Reintentar validación</button>
            <button v-if="!foto && (errorCamara || errorDetector)" class="button secondary" type="button" :disabled="iniciando" @click="encender()">Reintentar cámara</button>
        </div>
        <details v-if="errorDetector && detalleErrorDetector" class="hint"><summary>Detalle del problema de cámara</summary>{{ detalleErrorDetector }}</details>
    </div>
</template>
