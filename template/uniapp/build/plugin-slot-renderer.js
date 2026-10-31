const fs = require('fs')
const path = require('path')

const PLUGIN_CODE_PATTERN = /^[a-z][a-z0-9_-]{0,63}$/
const COMPONENT_ID_PATTERN = /^[a-z][a-z0-9-]{0,63}$/
const COMPONENT_SOURCE_PATTERN = /^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\.vue$/
const SLOT_COMPONENTS_FILE = 'slot-components.json'

/**
 * 判断目标路径是否仍位于指定根目录内。
 *
 * @param {string} root 根目录绝对路径。
 * @param {string} target 待检查绝对路径。
 * @returns {boolean} 是否位于根目录内。
 */
function isInside(root, target) {
  const relative = path.relative(root, target)
  return relative !== '' && !relative.startsWith(`..${path.sep}`) && relative !== '..' && !path.isAbsolute(relative)
}

/**
 * 读取并校验单个插件的移动端插槽组件清单。
 *
 * @param {string} pluginRoot 插件移动端源码根目录。
 * @param {string} pluginCode 插件编码。
 * @returns {Array<{id:string,source:string,importPath:string}>} 已校验组件定义。
 */
function readPluginComponents(pluginRoot, pluginCode) {
  const metadataPath = path.join(pluginRoot, SLOT_COMPONENTS_FILE)
  if (!fs.existsSync(metadataPath)) return []
  if (fs.lstatSync(metadataPath).isSymbolicLink()) {
    throw new Error(`插件插槽组件清单不允许使用软链接：${pluginCode}/${SLOT_COMPONENTS_FILE}`)
  }

  let metadata
  try {
    metadata = JSON.parse(fs.readFileSync(metadataPath, 'utf8'))
  } catch (error) {
    throw new Error(`插件插槽组件清单不是合法 JSON：${pluginCode}/${SLOT_COMPONENTS_FILE} (${error.message})`)
  }

  if (!metadata || typeof metadata !== 'object' || Array.isArray(metadata)) {
    throw new Error(`插件插槽组件清单根节点必须是对象：${pluginCode}/${SLOT_COMPONENTS_FILE}`)
  }
  if (metadata.schema_version !== '1.0') {
    throw new Error(`插件插槽组件清单 schema_version 必须为 1.0：${pluginCode}`)
  }
  if (metadata.plugin_code !== pluginCode) {
    throw new Error(`插件插槽组件清单 plugin_code 与目录不一致：${pluginCode}`)
  }
  if (!Array.isArray(metadata.components)) {
    throw new Error(`插件插槽组件清单 components 必须是数组：${pluginCode}`)
  }

  const pluginRealRoot = fs.realpathSync(pluginRoot)
  const seenIds = new Set()

  return metadata.components.map((component, index) => {
    const id = typeof component?.id === 'string' ? component.id.trim() : ''
    const source = typeof component?.source === 'string' ? component.source.trim() : ''
    if (!COMPONENT_ID_PATTERN.test(id)) {
      throw new Error(`插件插槽组件 ID 不合法：${pluginCode}.components[${index}]`)
    }
    if (!COMPONENT_SOURCE_PATTERN.test(source) || source.includes('..')) {
      throw new Error(`插件插槽组件路径不合法：${pluginCode}.components[${index}]`)
    }

    const fullId = `${pluginCode}:${id}`
    if (seenIds.has(fullId)) {
      throw new Error(`插件插槽组件 ID 重复：${fullId}`)
    }
    seenIds.add(fullId)

    const absolute = path.resolve(pluginRoot, source)
    if (!isInside(pluginRoot, absolute) || !fs.existsSync(absolute) || !fs.statSync(absolute).isFile()) {
      throw new Error(`插件插槽组件文件不存在或越界：${pluginCode}/${source}`)
    }
    if (fs.lstatSync(absolute).isSymbolicLink() || !isInside(pluginRealRoot, fs.realpathSync(absolute))) {
      throw new Error(`插件插槽组件文件不允许使用越界软链接：${pluginCode}/${source}`)
    }

    return {
      id: fullId,
      source,
      importPath: `@/plugins/${pluginCode}/${source}`
    }
  })
}

/**
 * 收集所有插件声明的移动端插槽组件，并按组件 ID 稳定排序。
 *
 * @param {string} uniappRoot UniApp 工程根目录。
 * @returns {Array<{id:string,source:string,importPath:string}>} 全部组件定义。
 */
function collectPluginComponents(uniappRoot) {
  const pluginsRoot = path.resolve(uniappRoot, 'plugins')
  if (!fs.existsSync(pluginsRoot)) return []

  const components = []
  const seenIds = new Set()
  const entries = fs.readdirSync(pluginsRoot, { withFileTypes: true })
    .filter(entry => entry.isDirectory() && PLUGIN_CODE_PATTERN.test(entry.name))
    .sort((left, right) => left.name.localeCompare(right.name))

  entries.forEach((entry) => {
    const pluginRoot = path.join(pluginsRoot, entry.name)
    readPluginComponents(pluginRoot, entry.name).forEach((component) => {
      if (seenIds.has(component.id)) {
        throw new Error(`插件插槽组件 ID 重复：${component.id}`)
      }
      seenIds.add(component.id)
      components.push(component)
    })
  })

  return components.sort((left, right) => left.id.localeCompare(right.id))
}

/**
 * 生成微信小程序可编译的静态组件分支。
 *
 * @param {Array<{id:string,importPath:string}>} components 组件定义。
 * @returns {string} Vue SFC 源码。
 */
function renderComponentFile(components) {
  const imports = components.map((component, index) => (
    `import PluginSlotComponent${index} from ${JSON.stringify(component.importPath)}`
  )).join('\n')
  const branches = components.length > 0
    ? components.map((component, index) => [
      `    <PluginSlotComponent${index}`,
      `      v-if="componentId === ${JSON.stringify(component.id).replace(/"/g, '&quot;')}"`,
      '      v-bind="componentProps"',
      '    />'
    ].join('\n')).join('\n')
    : '    <view v-if="false" />'

  return `<!-- 此文件由 build/plugin-slot-renderer.js 生成，请勿手工修改。 -->
<template>
  <view class="plugin-slot-renderer">
${branches}
  </view>
</template>

<script setup>
${imports}${imports ? '\n\n' : ''}defineOptions({ name: 'GeneratedPluginSlotRenderer' })

defineProps({
  componentId: { type: String, required: true },
  componentProps: { type: Object, default: () => ({}) }
})
</script>

<style scoped>
.plugin-slot-renderer {
  display: block;
}
</style>
`
}

/**
 * 生成运行时组件 ID 白名单。
 *
 * @param {Array<{id:string}>} components 组件定义。
 * @returns {string} ES Module 源码。
 */
function renderComponentIdsFile(components) {
  const ids = JSON.stringify(components.map(component => component.id), null, 2)
  return `// 此文件由 build/plugin-slot-renderer.js 生成，请勿手工修改。\nexport const pluginSlotComponentIds = Object.freeze(${ids})\n\n/**\n * 判断组件 ID 是否已进入本次移动端构建。\n *\n * @param {string} componentId 插件组件 ID。\n * @returns {boolean} 是否允许渲染。\n */\nexport function hasPluginSlotComponent(componentId) {\n  return pluginSlotComponentIds.includes(String(componentId || ''))\n}\n`
}

/**
 * 内容变化时才写入生成文件，避免每次构建触发无意义更新时间。
 *
 * @param {string} target 目标文件。
 * @param {string} contents 文件内容。
 * @returns {void}
 */
function writeIfChanged(target, contents) {
  if (fs.existsSync(target) && fs.readFileSync(target, 'utf8') === contents) return
  fs.mkdirSync(path.dirname(target), { recursive: true })
  fs.writeFileSync(target, contents)
}

/**
 * 生成移动端插件静态插槽渲染文件。
 *
 * @param {string} uniappRoot UniApp 工程根目录。
 * @returns {Array<{id:string,source:string,importPath:string}>} 已生成组件定义。
 */
function generatePluginSlotRenderer(uniappRoot) {
  const root = path.resolve(uniappRoot)
  const components = collectPluginComponents(root)
  const outputRoot = path.join(root, 'components', 'plugin-slot')
  writeIfChanged(path.join(outputRoot, 'generated.vue'), renderComponentFile(components))
  writeIfChanged(path.join(outputRoot, 'generated-component-ids.js'), renderComponentIdsFile(components))
  return components
}

module.exports = {
  collectPluginComponents,
  generatePluginSlotRenderer
}
