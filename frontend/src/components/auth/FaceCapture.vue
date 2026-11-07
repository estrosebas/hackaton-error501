<script setup>
import { api } from '../../services/api';
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useCapturaRostro } from '../../composables/useCapturaRostro';

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
        const data = await api('/face/check', { method: 'POST', body: JSON.stringify({ foto: image }), signal: controller.signal });
        if (controller.signal.aborted || foto.value !== image) return;
        if (data.accepted !== true) throw new Error('Respuesta inesperada');
        state.value = 'accepted';
        message.value = 'Tu fotografía está lista.';
        emit('validated', image);
    } catch (error) {
        if (controller.signal.aborted || foto.value !== image) return;
        state.value = 'error';
        message.value = error.message || 'No pudimos revisar la foto. Vuelve a intentar.';
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

<style scoped>
.capture { display: grid; gap: 12px; }
.camera { position: relative; aspect-ratio: 1; overflow: hidden; background: #152e25; border-radius: 14px; display: grid; place-items: center; border: 2px solid transparent; }
.camera-ready { border-color: #638f57; }
.camera video { width: 100%; height: 100%; object-fit: contain; transform: scaleX(-1); }
.camera > img { width: 100%; height: 100%; object-fit: contain; transform: scaleX(-1); }
.camera-frame { position: absolute; top: 50%; left: 50%; width: var(--frame-width, 100%); height: var(--frame-height, 100%); transform: translate(-50%, -50%); pointer-events: none; }
.camera-oval { position: absolute; top: 8%; left: 17%; width: 66%; height: 80%; border: 2px dashed #f2ead3aa; border-radius: 50%; box-shadow: 0 0 0 200px #0a191933; }
.camera-guide { position: absolute; bottom: 6%; left: 7%; right: 7%; padding: 8px 12px; background: #18382ce6; border-radius: 8px; text-align: center; color: #fff; font-size: 12px; line-height: 1.5; }
.camera-progress { position: absolute; left: 20%; right: 20%; top: 5%; height: 4px; background: #ffffff4d; border-radius: 5px; overflow: hidden; }
.camera-progress span { height: 100%; display: block; background: #afe0a4; transition: width .12s linear; }
.camera-badge { position: absolute; bottom: 16px; right: 16px; background: #f0f8e8; color: #24543f; border-radius: 20px; padding: 9px 14px; font-size: 12px; font-weight: 700; }
.camera-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.camera-select { width: 100%; }

.hint {color:var(--muted);font-size:13px;line-height:1.6;margin:0}.notice {padding:12px;border-radius:10px;background:#ffffff0c}.notice.error{color:#ffb3c1}.notice.success{color:#a7e4c6}.button{border:1px solid #ffffff25;border-radius:10px;background:var(--surface-raised);color:var(--text);padding:10px 14px;min-height:44px;cursor:pointer}.button:disabled{opacity:.5}.camera-select{display:grid;gap:6px}.camera-select select{background:var(--surface);color:var(--text);border:1px solid #ffffff25;padding:10px;border-radius:8px}
</style>
