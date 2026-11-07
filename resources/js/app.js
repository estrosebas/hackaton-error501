import { createApp } from 'vue';
import axios from 'axios';
import App from './App.vue';

axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Read Laravel's XSRF-TOKEN cookie on each request, including after login.
createApp(App).mount('#app');
