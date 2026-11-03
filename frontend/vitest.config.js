import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      // Mismo alias que resuelve @quasar/app-vite en la app. Si los tests no
      // lo replican, cualquier import '@/components/...' falla sólo en test.
      '@': fileURLToPath(new URL('./src', import.meta.url)),

      // Node resuelve el paquete `quasar` al build de SSR, que al instalarse
      // sin ssrContext tira "The SSR server build was installed without an
      // ssrContext". En la app esto lo arregla @quasar/vite-plugin; acá lo
      // apuntamos a mano al build de browser.
      quasar: 'quasar/dist/quasar.client.js',

      // '#q-app' también lo inyecta el plugin de Quasar (defineBoot,
      // defineRouter...). Sin esto los boot files no se pueden importar.
      '#q-app': '@quasar/app-vite'
    }
  },

  test: {
    environment: 'happy-dom',
    globals: true,
    setupFiles: ['./test/setup.js'],
    include: ['src/**/*.spec.js']
  }
})
