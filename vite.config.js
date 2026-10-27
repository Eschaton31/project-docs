import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { URL, fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  // the dev server lives under /vite-dev/ so the cloudflared tunnel can route that path to it (app/Support/DevServerVite.php)
  base: command === 'serve' ? '/vite-dev/' : undefined,
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
    // pages opened on any address load scripts from here; IPs and localhost are allowed hosts by default
    cors: true,
    allowedHosts: ['.bene-server.com'],
    watch: {
      ignored: ['**/storage/framework/views/**', '**/vendor/**'],
    },
  },
}))
