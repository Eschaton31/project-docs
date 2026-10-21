import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { URL, fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/main.js'],
      refresh: true,
    }),
    vue({
      template: {
        // keep absolute asset URLs (e.g. /img/...) pointing at Laravel's public/ dir
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    watch: {
      ignored: ['**/storage/framework/views/**', '**/vendor/**'],
    },
  },
})
