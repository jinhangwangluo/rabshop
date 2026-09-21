// 此文件由 build/plugin-slot-renderer.js 生成，请勿手工修改。
export const pluginSlotComponentIds = Object.freeze([])

/**
 * 判断组件 ID 是否已进入本次移动端构建。
 *
 * @param {string} componentId 插件组件 ID。
 * @returns {boolean} 是否允许渲染。
 */
export function hasPluginSlotComponent(componentId) {
  return pluginSlotComponentIds.includes(String(componentId || ''))
}
