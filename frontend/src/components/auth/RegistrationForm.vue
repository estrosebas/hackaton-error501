<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useTemplateRef, watch } from 'vue'
import { NButton } from 'naive-ui'
import { Capacitor } from '@capacitor/core'
import { Geolocation } from '@capacitor/geolocation'
import FaceCapture from './FaceCapture.vue'
import ProfilePersonalization from './ProfilePersonalization.vue'
import { api, ApiError, saveSession } from '../../services/api'
import type { NeighborProfile } from '../../composables/useAuth'

interface Identity { dni: string; given_names: string; paternal_surname: string; maternal_surname: string; birth_date: string; verification_digit: number }
interface Place { code: string; name: string }
interface Position { latitude: number; longitude: number; location_accuracy: number; location_captured_at: string }
const emit = defineEmits<{ registered: [] }>()
const step = ref(1)
const steps = ['Tus datos', 'Tu foto', 'Tu perfil']
const progress = useTemplateRef<HTMLOListElement>('progress')
const dni = ref('')
const identity = ref<Identity | null>(null)
const ticket = ref('')
const ticketExpires = ref(0)
const region = ref(''), province = ref(''), district = ref('')
const regions = ref<Place[]>([]), provinces = ref<Place[]>([]), districts = ref<Place[]>([])
const position = ref<Position | null>(null)
const locating = ref(false), lookupBusy = ref(false), submitting = ref(false)
const provinceBusy = ref(false), districtBusy = ref(false)
const notice = ref(''), locationNotice = ref(''), catalogError = ref('')
const photo = ref<string | null>(null), consent = ref(false)
const reviewingPhoto = ref(false)
const profileStarted = ref(false)
const personalization = ref<NeighborProfile>({
  bio: null, skills: [], interests: [], availability: null, can_help: false,
})
const values = reactive({ email: '', phone: '', password: '', confirmation: '', neighborhood: '' })
let identityRequest: AbortController | null = null
let provinceRequest: AbortController | null = null
let districtRequest: AbortController | null = null
let disposed = false
let locationGeneration = 0
let registering = false
const canContinue = computed(() => !!identity.value && identity.value.dni === dni.value && !!district.value && !!position.value && !locating.value)
function message(error: unknown) { return error instanceof Error ? error.message : 'No pudimos completar la solicitud. Intenta nuevamente.' }

watch(dni, () => {
  identityRequest?.abort(); identity.value = null; ticket.value = ''; ticketExpires.value = 0
  lookupBusy.value = false; notice.value = ''; photo.value = null; consent.value = false
})
watch(step, async () => {
  await nextTick()
  progress.value?.focus()
})
async function lookup() {
  if (!/^[0-9]{8}$/.test(dni.value) || lookupBusy.value) return
  identityRequest?.abort()
  const controller = new AbortController(); identityRequest = controller
  const requested = dni.value
  lookupBusy.value = true; notice.value = ''; identity.value = null; ticket.value = ''
  try {
    const result = await api<{ data: Identity; identity_token: string; expires_in: number }>('/identity/dni', { method: 'POST', body: JSON.stringify({ dni: requested }), signal: controller.signal })
    if (controller.signal.aborted || dni.value !== requested || disposed) return
    identity.value = result.data; ticket.value = result.identity_token
    ticketExpires.value = Date.now() + result.expires_in * 1000
  } catch (error) { if (!controller.signal.aborted && !disposed) notice.value = message(error) }
  finally { if (identityRequest === controller) lookupBusy.value = false }
}
async function loadRegions() {
  catalogError.value = ''
  try { const result = await api<{ data: Place[] }>('/locations/regions'); if (!disposed) regions.value = result.data }
  catch { if (!disposed) catalogError.value = 'No pudimos cargar las regiones. Reintenta para continuar.' }
}
watch(region, async (code) => {
  provinceRequest?.abort(); province.value = ''; district.value = ''; provinces.value = []; districts.value = []; catalogError.value = ''
  provinceBusy.value = false
  if (!code) return
  const controller = new AbortController(); provinceRequest = controller; provinceBusy.value = true
  try { const result = await api<{ data: Place[] }>(`/locations/regions/${code}/provinces`, { signal: controller.signal }); if (!controller.signal.aborted) provinces.value = result.data }
  catch { if (!controller.signal.aborted) catalogError.value = 'No pudimos cargar las provincias. Vuelve a seleccionar la región.' }
  finally { if (provinceRequest === controller) provinceBusy.value = false }
})
watch(province, async (code) => {
  districtRequest?.abort(); district.value = ''; districts.value = []; catalogError.value = ''; districtBusy.value = false
  if (!code) return
  const controller = new AbortController(); districtRequest = controller; districtBusy.value = true
  try { const result = await api<{ data: Place[] }>(`/locations/provinces/${code}/districts`, { signal: controller.signal }); if (!controller.signal.aborted) districts.value = result.data }
  catch { if (!controller.signal.aborted) catalogError.value = 'No pudimos cargar los distritos. Vuelve a seleccionar la provincia.' }
  finally { if (districtRequest === controller) districtBusy.value = false }
})
async function locate() {
  if (locating.value) return
  const generation = ++locationGeneration
  locating.value = true; locationNotice.value = ''; position.value = null
  try {
    if (Capacitor.isNativePlatform()) await Geolocation.requestPermissions()
    const result = await Geolocation.getCurrentPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
    if (disposed || generation !== locationGeneration) return
    position.value = { latitude: result.coords.latitude, longitude: result.coords.longitude, location_accuracy: result.coords.accuracy, location_captured_at: new Date(result.timestamp).toISOString() }
    locationNotice.value = 'Ubicación activada. Ya puedes continuar.'
  } catch {
    if (!disposed && generation === locationGeneration) locationNotice.value = 'No pudimos acceder a tu ubicación. Activa el permiso de ubicación del navegador o dispositivo y vuelve a intentar.'
  } finally { if (!disposed && generation === locationGeneration) locating.value = false }
}
async function next() {
  notice.value = ''
  if (!canContinue.value) { notice.value = 'Consulta tu DNI, selecciona tu distrito y activa tu ubicación.'; return }
  if (ticketExpires.value <= Date.now()) { notice.value = 'La consulta de DNI venció. Vuelve a consultar tu DNI.'; identity.value = null; ticket.value = ''; return }
  if (values.password !== values.confirmation) { notice.value = 'Las contraseñas deben coincidir.'; return }
  photo.value = null; consent.value = false; reviewingPhoto.value = false; step.value = 2
}

function verifyFreshness() {
  if (ticketExpires.value <= Date.now()) {
    step.value = 1; identity.value = null; ticket.value = ''; notice.value = 'La consulta de DNI venció. Consulta nuevamente tu DNI.'
    return false
  }
  if (!position.value || Date.now() - Date.parse(position.value.location_captured_at) >= 30 * 60 * 1000) {
    step.value = 1; position.value = null; notice.value = 'Actualiza tu ubicación antes de crear la cuenta.'
    return false
  }
  return true
}

function personalize() {
  notice.value = ''
  if (!photo.value || !consent.value || !verifyFreshness()) return
  profileStarted.value = true
  step.value = 3
}

function reviewPhoto() {
  reviewingPhoto.value = Boolean(photo.value)
  notice.value = ''
  step.value = 2
}

async function register() {
  if (registering || !photo.value || !consent.value || !verifyFreshness()) return
  notice.value = ''
  const profile = { ...personalization.value, bio: personalization.value.bio?.trim() || null }
  const hasProfile = profile.bio || profile.skills.length || profile.interests.length || profile.availability || profile.can_help
  registering = true; submitting.value = true
  try {
    const result = await api<{ token: string; user: unknown }>('/auth/register', { method: 'POST', body: JSON.stringify({
      dni: dni.value, identity_token: ticket.value, email: values.email, phone: values.phone || null,
      password: values.password, password_confirmation: values.confirmation,
      ubigeo: district.value, neighborhood: values.neighborhood, ...position.value,
      foto: photo.value, biometric_consent: true,
      ...(hasProfile ? { neighbor_profile: profile } : {}),
    }) })
    saveSession(result.token, result.user); values.password = ''; values.confirmation = ''; photo.value = null
    emit('registered')
  } catch (error) {
    notice.value = message(error)
    if (error instanceof ApiError) {
      const keys = Object.keys(error.errors)
      if (keys.some(key => key !== 'foto' && key !== 'biometric_consent' && !key.startsWith('neighbor_profile'))) {
        step.value = 1
      } else if (keys.includes('foto') || keys.includes('biometric_consent')) {
        photo.value = null
        reviewingPhoto.value = false
        step.value = 2
      }
    }
  } finally { registering = false; submitting.value = false }
}
onMounted(loadRegions)
onBeforeUnmount(() => { disposed = true; locationGeneration++; identityRequest?.abort(); provinceRequest?.abort(); districtRequest?.abort() })
</script>

<template>
  <div class="registration">
    <ol ref="progress" class="register-steps" aria-label="Pasos del registro" tabindex="-1">
      <li v-for="(label, index) in steps" :key="label" :aria-current="step === index + 1 ? 'step' : undefined" :class="{ active: step === index + 1 }">
        <span>{{ index + 1 }}</span>{{ label }}
      </li>
    </ol>
    <form v-if="step === 1" class="register-form" @submit.prevent="next">
      <fieldset class="register-section"><legend>Primero, tu DNI</legend>
        <div class="dni-row">
          <label class="dni-field" for="register-dni">Número de DNI
            <input id="register-dni" v-model="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required autocomplete="off" placeholder="Tus 8 dígitos" />
          </label>
          <NButton
            class="dni-lookup"
            type="primary"
            text
            :loading="lookupBusy"
            :disabled="!/^[0-9]{8}$/.test(dni) || lookupBusy"
            @click="lookup"
          >
            Consultar DNI
          </NButton>
        </div>
        <p class="register-hint">Completaremos tus datos al consultar tu DNI. Para cambiarlos, ingresa otro DNI.</p>
        <div v-if="identity" class="identity-data" role="group" aria-label="Datos de tu DNI"><span class="identity-status">✓ Datos encontrados</span><label>Nombres<input :value="identity.given_names" readonly aria-readonly="true" /></label><div class="register-grid"><label>Apellido paterno<input :value="identity.paternal_surname" readonly aria-readonly="true" /></label><label>Apellido materno<input :value="identity.maternal_surname" readonly aria-readonly="true" /></label></div><div class="register-grid"><label>Fecha de nacimiento<input :value="identity.birth_date" type="date" readonly aria-readonly="true" /></label><label>Dígito verificador<input :value="identity.verification_digit" readonly aria-readonly="true" /></label></div></div>
      </fieldset>
      <template v-if="identity">
        <fieldset class="register-section register-details">
          <legend>Tu cuenta</legend>
          <label>Correo electrónico
            <input v-model="values.email" name="email" type="email" autocomplete="email" maxlength="255" required placeholder="vecina@ejemplo.com" />
          </label>
          <label>
            <span>Celular <small>opcional</small></span>
            <input v-model="values.phone" type="tel" autocomplete="tel" maxlength="20" />
          </label>
          <div class="register-passwords">
            <label>Contraseña
              <input v-model="values.password" type="password" name="password" autocomplete="new-password" minlength="8" required placeholder="Mínimo 8 caracteres" />
            </label>
            <label>Repite tu contraseña
              <input v-model="values.confirmation" type="password" autocomplete="new-password" minlength="8" required />
            </label>
          </div>
        </fieldset>
        <fieldset class="register-section register-details">
          <legend>¿Cuál es tu barrio?</legend>
          <label>Región
            <select v-model="region" required>
              <option value="" disabled>Selecciona tu región</option>
              <option v-for="place in regions" :key="place.code" :value="place.code">{{ place.name }}</option>
            </select>
          </label>
          <div class="register-grid">
            <label>Provincia
              <select v-model="province" :disabled="!region || provinceBusy" required>
                <option value="" disabled>{{ provinceBusy ? 'Cargando…' : 'Selecciona' }}</option>
                <option v-for="place in provinces" :key="place.code" :value="place.code">{{ place.name }}</option>
              </select>
            </label>
            <label>Distrito
              <select v-model="district" :disabled="!province || districtBusy" required>
                <option value="" disabled>{{ districtBusy ? 'Cargando…' : 'Selecciona' }}</option>
                <option v-for="place in districts" :key="place.code" :value="place.code">{{ place.name }}</option>
              </select>
            </label>
          </div>
          <p v-if="catalogError" class="register-error" role="alert">{{ catalogError }} <button v-if="!regions.length" type="button" class="register-link" @click="loadRegions">Reintentar</button></p>
          <label>Barrio o urbanización
            <input v-model="values.neighborhood" name="neighborhood" maxlength="120" required placeholder="Por ejemplo, Santa Rosa" />
          </label>
        </fieldset>
        <section class="location-card" aria-labelledby="location-title"><h2 id="location-title">Activa tu ubicación</h2><p>Nos ayudará a acercarte a servicios y vecinos de tu zona.</p><NButton block :loading="locating" :disabled="locating" @click="locate">{{ position ? 'Actualizar mi ubicación' : 'Activar ubicación actual' }}</NButton><p v-if="locationNotice" role="status" :class="position ? 'register-success' : 'register-error'">{{ locationNotice }}</p></section>
      </template>
      <p v-if="notice" class="register-error" role="alert">{{ notice }}</p><NButton attr-type="submit" type="primary" size="large" block :disabled="!canContinue">Siguiente: mi fotografía →</NButton>
    </form>
    <form v-else-if="step === 2" class="register-form" @submit.prevent="personalize">
      <div class="photo-intro">
        <h2>Una foto para tu perfil</h2>
        <p>Te guiaremos para tomarla con tu cámara.</p>
      </div>
      <div v-if="reviewingPhoto && photo" class="register-photo-review">
        <img :src="photo" alt="Tu fotografía capturada" />
        <NButton quaternary @click="photo = null; reviewingPhoto = false">Tomar otra foto</NButton>
      </div>
      <FaceCapture v-else @validated="(value: string | null) => photo = value" />
      <label class="register-consent">
        <input v-model="consent" type="checkbox" required />
        <span>Autorizo guardar mi fotografía y plantilla facial para gestionar mi perfil.</span>
      </label>
      <p v-if="notice" class="register-error" role="alert">{{ notice }}</p>
      <NButton attr-type="submit" type="primary" size="large" block :disabled="!photo || !consent">
        Siguiente: mi perfil →
      </NButton>
      <NButton quaternary @click="step = 1; photo = null; consent = false; notice = ''">← Revisar mis datos</NButton>
    </form>
    <form v-if="profileStarted" v-show="step === 3" class="register-form" @submit.prevent="register">
      <ProfilePersonalization v-model="personalization" :disabled="submitting" />
      <p v-if="notice" class="register-error" role="alert">{{ notice }}</p>
      <NButton attr-type="submit" type="primary" size="large" block :loading="submitting" :disabled="submitting">
        Crear mi cuenta
      </NButton>
      <NButton quaternary :disabled="submitting" @click="reviewPhoto">← Revisar mi foto</NButton>
    </form>
  </div>
</template>

<style scoped>
.registration,.register-form,.register-section,.identity-data{display:grid;gap:16px}
.register-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));list-style:none;padding:0;margin:0 0 4px;gap:8px;font-size:12px;color:var(--muted);scroll-margin-top:16px}
.register-steps li{display:flex;align-items:center;gap:6px;min-width:0}
.register-steps span{display:grid;place-items:center;width:24px;height:24px;border:1px solid #ffffff30;border-radius:50%;flex-shrink:0}
.register-steps .active{color:#ff9bb4;font-weight:800}
.active span{background:#ff638922;border-color:var(--coral)}
.register-section{border:0;padding:0;margin:0;min-width:0}
legend{font-weight:800;font-size:16px;margin-bottom:10px}
label{display:grid;gap:7px;font-size:12px;font-weight:800;min-width:0}
input:not([type=checkbox]),select{width:100%;min-width:0;min-height:46px;border:1px solid #ffffff25;border-radius:10px;background:#10142699;color:var(--text);padding:10px 12px;font:inherit;font-size:15px}
input:focus,select:focus{outline:2px solid var(--coral);outline-offset:1px}
select:disabled{opacity:.45}
select option{background:#1a1e35}
.dni-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end}
.register-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
.register-hint,.photo-intro p{font-size:12px;color:var(--muted);line-height:1.6;margin:0}
.identity-status,.register-success{color:#a7e4c6;font-size:12px}
.location-card{padding:16px;border-radius:12px;background:#b4a1f810;border:1px solid #b4a1f830}
.location-card h2,.photo-intro h2{font-size:17px;margin:0 0 5px}
.location-card p{font-size:13px;line-height:1.6;color:var(--muted);margin:4px 0 12px}
.location-card .register-error{color:#ffb0c0}
.location-card .register-success{color:#a7e4c6}
.register-error{color:#ffb0c0;font-size:13px;line-height:1.6;margin:0}
.register-link{color:#ff9bb4;text-decoration:underline;border:0;background:none;cursor:pointer}
.register-consent{display:flex;align-items:flex-start;font-size:12px;line-height:1.6;font-weight:400;gap:10px}
.register-consent input{accent-color:var(--coral);width:18px;height:18px;flex-shrink:0}
.register-form :deep(.n-button){min-height:44px;font-weight:800}
.register-photo-review{display:grid;gap:8px}
.register-photo-review img{display:block;width:100%;max-height:300px;object-fit:contain;border-radius:14px;background:var(--surface)}
@media(max-width:360px){.register-grid{grid-template-columns:1fr}
.dni-row{grid-template-columns:1fr}}

.identity-data {
  gap: 12px;
  padding-block: 6px 10px;
}
.identity-status {
  font-weight: 700;
}
.identity-data label {
  gap: 1px;
  color: var(--muted);
  font-weight: 650;
}
.identity-data input[readonly] {
  min-height: 30px;
  padding: 2px 0 0;
  border: 0;
  border-bottom: 2px solid transparent;
  border-radius: 0;
  background: transparent;
  color: var(--text);
  font-size: 16px;
  font-weight: 700;
  cursor: text;
}
.identity-data input[type='date']::-webkit-datetime-edit {
  padding: 0;
}
.register-details {
  margin-block-start: 10px;
}
.register-details legend {
  margin-block-end: 12px;
  color: var(--text);
  font-size: 17px;
}
.register-details label,
.dni-field {
  gap: 3px;
  font-weight: 700;
}
.register-details small {
  margin-inline-start: 4px;
  color: var(--muted);
  font-size: inherit;
  font-weight: 400;
}
.register-passwords {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 164px), 1fr));
  gap: 16px 12px;
}
.register-details :is(input, select),
.dni-field input {
  padding: 8px 2px;
  border: 0;
  border-bottom: 1px solid #a5acc78f;
  border-radius: 0;
  background: transparent;
  font-size: 16px;
  font-weight: 500;
}
.register-details input::placeholder,
.dni-field input::placeholder,
.register-details select:invalid {
  color: var(--muted);
  opacity: 1;
}
.register-details select:disabled {
  border-bottom-color: #ffffff26;
  color: var(--muted);
  opacity: 0.65;
}
.register-details :is(input, select):focus,
.dni-field input:focus {
  padding-block-end: 7px;
}
.register-details :is(input, select):focus,
.dni-field input:focus,
.identity-data input[readonly]:focus {
  border-bottom-width: 2px;
  border-bottom-color: var(--coral);
  outline: none;
}
.dni-lookup.n-button {
  justify-self: start;
  padding-inline: 4px;
}
.dni-lookup.n-button:not(:disabled) {
  text-decoration: underline;
  text-underline-offset: 4px;
}
@media (forced-colors: active) {
  .register-details :is(input, select):focus,
  .dni-field input:focus,
  .identity-data input[readonly]:focus {
    outline: 2px solid Highlight;
    outline-offset: 1px;
  }
}
</style>
