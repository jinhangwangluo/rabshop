import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const prefix = 'merchant'

export default defineConfig({
  base: `/${prefix}/`,
  plugins: [vue()],
  resolve: {
    extensions: ['.mjs', '.js', '.ts', '.jsx', '.tsx', '.json', '.vue'],
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      vue: 'vue/dist/vue.esm-bundler.js'
    }
  },
  server: {
    host: '0.0.0.0',
    port: Number(process.env.npm_config_port || process.env.PORT || 9587),
    open: false
  },
  build: {
    outDir: 'dist',
    assetsDir: 'static',
    sourcemap: false
  }
})
