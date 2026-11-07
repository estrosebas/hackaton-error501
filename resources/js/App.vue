<script setup>
import axios from 'axios';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import FaceCapture from './components/FaceCapture.vue';
import UbigeoSelect from './components/UbigeoSelect.vue';

const path = ref(window.location.pathname);
const user = ref(null);
const loadingSession = ref(true);
const busy = ref(false);
const step = ref(1);
const photo = ref(null);
const consent = ref(false);
const errors = ref({});
const notice = ref('');
const locationNotice = ref('');
const updatedPhoto = ref(Date.now());
const form = ref({ dni: '', name: '', email: '', phone: '', password: '', password_confirmation: '', ubigeo: '', neighborhood: '', latitude: null, longitude: null });
const loginForm = ref({ dni: '', password: '' });
const mode = computed(() => user.value ? (path.value === '/actualizar-foto' ? 'photo' : 'home') : (['/login', '/actualizar-foto'].includes(path.value) ? 'login' : 'register'));
const photoStep = computed(() => mode.value === 'photo' || (mode.value === 'register' && step.value === 2));

function navigate(destination) {
    history.pushState({}, '', destination); path.value = destination;
    errors.value = {}; notice.value = ''; photo.value = null; consent.value = false; step.value = 1;
}
function popstate() { path.value = window.location.pathname; step.value = 1; photo.value = null; consent.value = false; }
async function session() {
    try { user.value = (await axios.get('/api/auth/user')).data.user; }
    catch (error) { if (error.response?.status !== 401) notice.value = 'No pudimos cargar tu sesión. Recarga la página.'; }
    finally { loadingSession.value = false; }
}
function captureError(error) {
    errors.value = error.response?.data?.errors || {};
    notice.value = Object.values(errors.value).flat().join(' ') || error.response?.data?.message || 'No pudimos completar la solicitud. Vuelve a intentar.';
    if (error.response?.status === 419) notice.value = 'Tu sesión venció. Recarga la página para continuar.';
}
async function login() {
    busy.value = true; notice.value = ''; errors.value = {};
    try { user.value = (await axios.post('/api/auth/login', loginForm.value)).data.user; loginForm.value.password = ''; navigate(path.value === '/actualizar-foto' ? '/actualizar-foto' : '/'); }
    catch (error) { captureError(error); }
    finally { busy.value = false; }
}
async function submitPhoto() {
    if (!photo.value || !consent.value || busy.value) return;
    busy.value = true; notice.value = ''; errors.value = {};
    try {
        if (mode.value === 'register') {
            const { data } = await axios.post('/api/auth/register', { ...form.value, foto: photo.value, biometric_consent: true }, { timeout: 25000 });
            user.value = data.user; form.value.password = ''; form.value.password_confirmation = '';
            navigate('/'); notice.value = 'Tu cuenta está lista. Bienvenido a tu comunidad.';
        } else {
            await axios.post('/api/profile/photo', { foto: photo.value, biometric_consent: true }, { timeout: 25000 });
            updatedPhoto.value = Date.now(); navigate('/'); notice.value = 'Tu fotografía se actualizó correctamente.';
        }
    } catch (error) {
        captureError(error);
        if (mode.value === 'register' && Object.keys(errors.value).some(key => key !== 'foto' && key !== 'biometric_consent')) step.value = 1;
    } finally { busy.value = false; }
}
async function logout() {
    busy.value = true;
    try { await axios.post('/api/auth/logout'); user.value = null; navigate('/login'); }
    catch (error) { captureError(error); }
    finally { busy.value = false; }
}
function locate() {
    if (!navigator.geolocation) { locationNotice.value = 'Tu navegador no ofrece ubicación. Puedes continuar con tu distrito y barrio.'; return; }
    locationNotice.value = 'Buscando tu ubicación…';
    navigator.geolocation.getCurrentPosition(({ coords }) => {
        form.value.latitude = coords.latitude; form.value.longitude = coords.longitude;
        locationNotice.value = 'Ubicación agregada. No se mostrará tu domicilio exacto.';
    }, () => { locationNotice.value = 'No pudimos obtener tu ubicación. Puedes continuar con tu distrito y barrio.'; }, { timeout: 10000, maximumAge: 60000 });
}
onMounted(() => { window.addEventListener('popstate', popstate); session(); });
onBeforeUnmount(() => window.removeEventListener('popstate', popstate));
</script>

<template>
    <div class="app-shell">
        <header class="topbar"><a class="wordmark" href="/" @click.prevent="navigate('/')"><span class="brand-mark">v.</span>vecino<span class="brand-dot">●</span></a><span class="topbar-note">La confianza empieza cerca.</span><button v-if="user" type="button" class="text-button" :disabled="busy" @click="logout">Salir</button></header>
        <main v-if="loadingSession" class="loading" role="status">Preparando tu comunidad…</main>
        <main v-else-if="mode === 'home'" class="home-layout">
            <div class="eyebrow">TU ESPACIO VECINAL</div><h1>Hola, {{ user.name.split(' ')[0] }}<span class="green">.</span></h1><p class="lead">Tu comunidad comienza contigo.</p>
            <div v-if="notice" class="notice success" role="status">{{ notice }}</div>
            <section class="profile-card"><img :src="`/api/profile/photo?v=${updatedPhoto}`" alt="Tu fotografía privada" /><div><span class="eyebrow">MI PERFIL</span><h2>{{ user.name }}</h2><p>{{ user.neighborhood }} · {{ user.ubigeo }}</p><span class="status-pill">✓ Fotografía registrada</span><p class="hint">Solo tú puedes consultar esta fotografía.</p><button class="button secondary" type="button" @click="navigate('/actualizar-foto')">Actualizar mi foto</button></div></section>
            <p class="hint">Tu cuenta ya está preparada. Las publicaciones y el mapa comunitario se incorporarán en la siguiente etapa.</p>
        </main>
        <main v-else class="onboarding">
            <aside class="story"><div class="eyebrow">AQUÍ NOS DAMOS UNA MANO</div><h1>Tu barrio.<br>Tu gente.<br><span>Más cerca.</span></h1><p>Un lugar para encontrar lo que necesitas y compartir lo que puedes ofrecer.</p><div class="neighborhood-art" aria-hidden="true"><div class="house house-a"><i></i><i></i><b></b></div><div class="house house-b"><i></i><i></i><b></b></div><div class="house house-c"><i></i><i></i><b></b></div><span class="art-tree">✳</span></div><div class="story-footer"><span class="mini-avatars">● ● ●</span>Las buenas comunidades se construyen entre todos.</div></aside>
            <section class="form-panel">
                <template v-if="mode === 'login'"><span class="eyebrow">QUÉ BUENO VERTE</span><h2>Vuelve a tu comunidad</h2><p class="subheading">Ingresa con tu DNI y contraseña.</p><form class="form" @submit.prevent="login"><label>DNI<input v-model="loginForm.dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="username" required placeholder="Tus 8 dígitos" /></label><label>Contraseña<input v-model="loginForm.password" type="password" name="password" autocomplete="current-password" required /></label><p v-if="notice" class="notice error" role="alert">{{ notice }}</p><button class="button" :disabled="busy">{{ busy ? 'Ingresando…' : 'Ingresar' }}<span>→</span></button></form><p class="switch-mode">¿Primera vez por aquí? <a href="/registro" @click.prevent="navigate('/registro')">Crear mi cuenta</a></p></template>
                <template v-else>
                    <div v-if="mode === 'register'" class="steps"><span :class="{ active: step === 1 }">01 · Tu comunidad</span><span :class="{ active: step === 2 }">02 · Tu fotografía</span></div>
                    <span v-else class="eyebrow">MI PERFIL</span><h2>{{ photoStep ? 'Pongámosle rostro' : 'Todo empieza contigo' }}</h2><p class="subheading">{{ photoStep ? 'Una foto clara para completar tu perfil.' : 'Cuéntanos quién eres y a qué comunidad perteneces.' }}</p>
                    <form v-if="!photoStep" class="form" @submit.prevent="step = 2; notice = ''">
                        <div class="form-grid"><label>DNI<input v-model="form.dni" name="dni" required inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="username" placeholder="8 dígitos" /></label><label><span>Celular <span class="optional">opcional</span></span><input v-model="form.phone" name="phone" type="tel" maxlength="20" autocomplete="tel" placeholder="Tu número" /></label></div>
                        <label>Nombre completo<input v-model="form.name" name="name" required maxlength="120" autocomplete="name" placeholder="¿Cómo te llamas?" /></label><label>Correo electrónico<input v-model="form.email" name="email" type="email" required maxlength="255" autocomplete="email" placeholder="nombre@correo.com" /></label>
                        <div class="form-grid"><label>Contraseña<input v-model="form.password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" /></label><label>Repite tu contraseña<input v-model="form.password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" :aria-invalid="!!form.password_confirmation && form.password !== form.password_confirmation" /></label></div>
                        <div class="form-divider"><span>Tu comunidad</span></div><UbigeoSelect v-model="form.ubigeo" /><label>Barrio o urbanización<input v-model="form.neighborhood" name="neighborhood" required maxlength="120" placeholder="Por ejemplo, Santa Rosa" /></label><button class="text-button locate" type="button" @click="locate">⌖ Agregar mi ubicación actual <span class="optional">opcional</span></button><p v-if="locationNotice" class="hint" role="status">{{ locationNotice }}</p>
                        <p v-if="notice" class="notice error" role="alert">{{ notice }}</p><p v-if="form.password_confirmation && form.password !== form.password_confirmation" class="field-error">Las contraseñas deben coincidir.</p><button class="button" :disabled="form.password !== form.password_confirmation">Continuar a mi foto <span>→</span></button>
                    </form>
                    <form v-else class="form" @submit.prevent="submitPhoto"><FaceCapture @validated="photo = $event" /><label class="consent"><input v-model="consent" type="checkbox" required /><span>Autorizo guardar mi fotografía y plantilla facial para gestionar mi perfil. Puedo actualizar mi foto desde mi cuenta.</span></label><p v-if="notice" class="notice error" role="alert">{{ notice }}</p><button class="button" :disabled="busy || !photo || !consent">{{ busy ? 'Guardando…' : mode === 'photo' ? 'Guardar mi foto' : 'Crear mi cuenta' }}<span v-if="!busy">→</span></button><button class="text-button" type="button" :disabled="busy" @click="mode === 'photo' ? navigate('/') : (step = 1, photo = null, consent = false)">← {{ mode === 'photo' ? 'Volver a mi perfil' : 'Revisar mis datos' }}</button></form>
                    <p v-if="mode === 'register'" class="switch-mode">¿Ya eres parte? <a href="/login" @click.prevent="navigate('/login')">Ingresar</a></p>
                </template>
            </section>
        </main><footer class="site-footer">Vecino · Hecho para estar cerca.</footer>
    </div>
</template>
