/**
 * UniApp 构建期插件注册表。
 */
import { hasPluginSlotComponent } from '@/components/plugin-slot/generated-component-ids.js'

import { shallowReactive } from 'vue'

const pluginEntries = import.meta.glob('./*/index.js', { eager: true })
const manifests = new Map()
const slots = shallowReactive(new Map())
const pluginStates = shallowReactive(new Map())
const registeredPlugins = new Set()
let runtimeContext = {}
let runtimePromise = null
let runtimeLoadedAt = 0

/** Read the public persistent install state once per concurrent refresh. */
export async function syncPluginRuntime(force = false) {
  if (runtimePromise) return runtimePromise
  if (!force && runtimeLoadedAt && Date.now() - runtimeLoadedAt < 5000) return { plugins: [...pluginStates.values()] }
  runtimePromise = import('@/api/pluginRuntime.js').then(module => module.getPluginRuntime()).then(response => {
    const data = response?.data || response
    if (!Array.isArray(data?.plugins)) throw new Error('插件运行状态无效')
    manifests.forEach((_, code) => setPluginState(code, { enabled: false, lifecycle_status: 'uninstalled', installation_id: '', install_generation: '' }))
    data.plugins.forEach(state => {
      if (manifests.has(state.code)) setPluginState(state.code, { ...state, enabled: state.enabled === true && state.lifecycle_status === 'active' })
    })
    runtimeLoadedAt = Date.now()
    registerEnabledPlugins()
    return data
  }).catch(() => {
    manifests.forEach((_, code) => setPluginState(code, { enabled: false, lifecycle_status: 'unavailable' }))
    runtimeLoadedAt = 0
    return { plugins: [] }
  }).finally(() => { runtimePromise = null })
  return runtimePromise
}

/** @returns {Object} Current server-confirmed state, initially disabled. */
export function getPluginState(pluginCode) {
  return { ...(pluginStates.get(String(pluginCode || '').trim()) || { enabled: false, lifecycle_status: 'uninstalled' }) }
}

/** Refresh on entry; an unavailable runtime never permits a compiled page to run. */
export async function requirePluginEnabled(pluginCode) {
  await syncPluginRuntime(true)
  return isPluginEnabled(pluginCode)
}

function registerEnabledPlugins() {
  Object.values(pluginEntries).forEach(module => {
    const manifest = module.manifest || module.default?.manifest
    const register = module.register || module.default?.register
    if (!manifest || registeredPlugins.has(manifest.code) || !isPluginEnabled(manifest.code) || typeof register !== 'function') return
    registeredPlugins.add(manifest.code)
    register({ ...runtimeContext, registerPluginManifest, registerPluginSlot, setPluginState })
  })
}
let initialized = false
const slotAliases = {
  'mobile.order.sections': 'mobile.order.confirm.sections'
}

function normalizeSlotName(slot) {
  const name = String(slot || '').trim()
  return slotAliases[name] || name
}

function normalizeSlotComponent(pluginCode, component) {
  const componentName = String(component || '').trim()
  if (!/^[a-z][a-z0-9-]{0,63}$/.test(componentName)) {
    throw new Error(`插件插槽组件 ID 不合法：${pluginCode}`)
  }
  const componentId = `${pluginCode}:${componentName}`
  if (!hasPluginSlotComponent(componentId)) {
    throw new Error(`插件插槽组件未进入移动端构建：${componentId}`)
  }
  return componentId
}

function clone(value) {
  if (value === undefined || value === null) return value
  if (typeof value === 'function') return value
  if (Array.isArray(value)) return value.map(clone)
  if (typeof value === 'object') return Object.keys(value).reduce((result, key) => {
    result[key] = clone(value[key])
    return result
  }, {})
  return value
}

function normalizeManifest(manifest = {}) {
  const code = String(manifest.code || manifest.pluginCode || '').trim()
  if (!/^[a-z][a-z0-9_-]{0,63}$/.test(code)) throw new Error('插件清单缺少合法 code')
  return { ...clone(manifest), code, events: manifest.events && typeof manifest.events === 'object' ? manifest.events : {}, surfaces: Array.isArray(manifest.surfaces) ? manifest.surfaces.map(String) : ['mobile'] }
}

function declaredEvents(manifest, eventName) {
  const frontend = manifest.events?.frontend_consumes?.mobile || []
  const backend = manifest.events?.consumes || []
  return [...frontend, ...backend].map(String).includes(eventName)
}

export function registerPluginManifest(manifest) {
  const normalized = normalizeManifest(manifest)
  const existing = manifests.get(normalized.code)
  if (existing && JSON.stringify(existing) !== JSON.stringify(normalized)) throw new Error(`插件清单重复：${normalized.code}`)
  manifests.set(normalized.code, normalized)
  if (!pluginStates.has(normalized.code)) {
    pluginStates.set(normalized.code, { enabled: false, lifecycle_status: 'uninstalled' })
  }
  return normalized
}

export function setPluginState(pluginCode, state = {}) {
  const code = String(pluginCode || '').trim()
  pluginStates.set(code, { ...(pluginStates.get(code) || { enabled: false }), ...state })
}

export function isPluginEnabled(pluginCode) { return pluginStates.get(String(pluginCode || '').trim())?.enabled === true }
export function getPluginManifest(pluginCode) { return manifests.get(String(pluginCode || '').trim()) || null }
export function canRegisterPluginEvent(pluginCode, eventName) {
  const manifest = getPluginManifest(pluginCode)
  return !!manifest && isPluginEnabled(pluginCode) && declaredEvents(manifest, eventName)
}

export function registerPluginSlot({ slot, pluginCode, priority = 100, component, props = {} }) {
  const name = normalizeSlotName(slot)
  const code = String(pluginCode || '').trim()
  if (!name || !code || !component) throw new Error('插件插槽注册项不完整')
  const manifest = getPluginManifest(code)
  if (!manifest || !isPluginEnabled(code)) return () => {}
  const declared = manifest.slots?.mobile || manifest.frontend_slots?.mobile || []
  // 旧版清单仍可能声明 mobile.order.sections，注册和渲染统一使用新名称后
  // 先规范化声明列表，确保兼容别名不会被误判为越权插槽。
  const normalizedDeclared = declared.map(value => normalizeSlotName(String(value)))
  if (normalizedDeclared.length > 0 && !normalizedDeclared.includes(name)) throw new Error(`插件未声明移动端插槽：${code}:${name}`)
  const list = slots.get(name) || []
  if (list.some(item => item.pluginCode === code)) throw new Error(`插件插槽重复注册：${code}:${name}`)
  const componentId = normalizeSlotComponent(code, component)
  const item = { slot: name, pluginCode: code, priority: Number(priority) || 100, componentId, props }
  list.push(item)
  list.sort((a, b) => a.priority - b.priority || a.pluginCode.localeCompare(b.pluginCode))
  slots.set(name, list.slice())
  return () => slots.set(name, list.filter(value => value.pluginCode !== code))
}

export function getPluginSlotEntries(slot, context = {}) {
  const name = normalizeSlotName(slot)
  return (slots.get(name) || []).filter(item => isPluginEnabled(item.pluginCode)).map(item => ({
    ...item,
    key: `${item.pluginCode}:${name}:${item.componentId}`,
    props: { ...item.props, context: clone(context) }
  }))
}

export function initializePluginRegistry(runtime = {}) {
  if (initialized) return
  initialized = true
  Object.values(pluginEntries).forEach(module => {
    const manifest = module.manifest || module.default?.manifest
    if (manifest) registerPluginManifest(manifest)
  })
  runtimeContext = runtime
  void syncPluginRuntime()
}

export function resetPluginRegistry() {
  manifests.clear(); slots.clear(); pluginStates.clear(); registeredPlugins.clear(); runtimeLoadedAt = 0; initialized = false
}

export default { registerPluginManifest, registerPluginSlot, getPluginSlotEntries, initializePluginRegistry, isPluginEnabled, setPluginState }
