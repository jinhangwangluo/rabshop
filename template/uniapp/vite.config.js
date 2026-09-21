const path = require('path')
const { defineConfig } = require('vite')
const uni = require('@dcloudio/vite-plugin-uni')
const { generatePluginSlotRenderer } = require('./build/plugin-slot-renderer')
const { pluginPages } = require('./build/plugin-pages')

const sourceRoot = path.resolve(__dirname, 'src')

generatePluginSlotRenderer(sourceRoot)

module.exports = defineConfig({
  plugins: [pluginPages(sourceRoot), uni.default ? uni.default() : uni()],
  resolve: {
    alias: {
      '@': sourceRoot
    }
  }
})
