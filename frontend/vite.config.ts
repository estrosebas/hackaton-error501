import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
const buildId = process.env.MASI_BUILD_ID || new Date().toISOString()

// https://vite.dev/config/
export default defineConfig({
  define: { 'import.meta.env.VITE_MASI_BUILD_ID': JSON.stringify(buildId) },
  plugins: [vue(), { name: 'masi-build-version', generateBundle() { this.emitFile({ type: 'asset', fileName: 'build/version.json', source: JSON.stringify({ id: buildId }) }) } }],
  server: { host: '127.0.0.1', port: 5173, strictPort: true, proxy: { '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true } } },
})
