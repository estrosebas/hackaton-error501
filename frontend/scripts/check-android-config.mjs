import { loadEnv } from 'vite'
const env=loadEnv('android',process.cwd(),'VITE_')
if(!/^https:\/\//.test(env.VITE_API_BASE_URL||'') || /api\.example\.com/.test(env.VITE_API_BASE_URL)){
  throw new Error('Configura VITE_API_BASE_URL en .env.android.local con una API HTTPS real accesible desde Android.')
}
