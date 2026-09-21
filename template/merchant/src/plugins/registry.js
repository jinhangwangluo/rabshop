/**
 * 商户端插件构建期注册表。
 */
import { shallowReactive } from 'vue'

const pluginEntries = import.meta.glob('./*/index.js', { eager: true })
const routeEntries = import.meta.glob('./*/routes.js', { eager: true })

const manifests = new Map()
const slots = shallowReactive(new Map())
const routes = []
const pluginStates = shallowReactive(new Map())
const registeredPlugins = new Set()
let runtimeContext = {}
let runtimePromise = null
let runtimeLoadedAt = 0

/** Read the public persistent install state once per concurrent refresh. */
export async function syncPluginRuntime(force = false) {
  if (runtimePromise) return runtimePromise
  if (!force && runtimeLoadedAt && Date.now() - runtimeLoadedAt < 5000) return { plugins: [...pluginStates.values()] }
  runtimePromise = import('@/api/pluginRuntime').then(module => module.getPluginRuntime()).then(response => {
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
    register({ ...runtimeContext, registerPluginManifest, registerPluginSlot, registerPluginRoute, setPluginState })
  })
}
let initialized = false
let staticBootstrapped = false

function clone(value) {
  if (value === undefined || value === null) return value
  if (typeof window.structuredClone === 'function') return window.structuredClone(value)
  return JSON.parse(JSON.stringify(value))
}

function normalizeManifest(manifest = {}) {
  const code = String(manifest.code || manifest.pluginCode || '').trim()
  if (!/^[a-z][a-z0-9_-]{0,63}$/.test(code)) throw new Error('插件清单缺少合法 code')
  return {
    ...clone(manifest),
    code,
    events: manifest.events && typeof manifest.events === 'object' ? manifest.events : {},
    surfaces: Array.isArray(manifest.surfaces) ? manifest.surfaces.map(String) : ['merchant']
  }
}

function declaredEvents(manifest, eventName) {
  const frontend = manifest.events?.frontend_consumes?.merchant || []
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

export function isPluginEnabled(pluginCode) {
  return pluginStates.get(String(pluginCode || '').trim())?.enabled === true
}

export function getPluginManifest(pluginCode) {
  return manifests.get(String(pluginCode || '').trim()) || null
}

export function canRegisterPluginEvent(pluginCode, eventName) {
  const manifest = getPluginManifest(pluginCode)
  return !!manifest && isPluginEnabled(pluginCode) && declaredEvents(manifest, eventName)
}

export function registerPluginSlot({ slot, pluginCode, priority = 100, component, props = {}}) {
  const name = String(slot || '').trim()
  const code = String(pluginCode || '').trim()
  if (!name || !code || !component) throw new Error('插件插槽注册项不完整')
  const manifest = getPluginManifest(code)
  if (!manifest || !isPluginEnabled(code)) return () => {}
  const declared = manifest.slots?.merchant || manifest.frontend_slots?.merchant || []
  if (declared.length > 0 && !declared.map(String).includes(name)) throw new Error(`插件未声明商户端插槽：${code}:${name}`)
  const list = slots.get(name) || []
  if (list.some(item => item.pluginCode === code)) throw new Error(`插件插槽重复注册：${code}:${name}`)
  const item = { slot: name, pluginCode: code, priority: Number(priority) || 100, component, props }
  list.push(item)
  list.sort((a, b) => a.priority - b.priority || a.pluginCode.localeCompare(b.pluginCode))
  slots.set(name, list.slice())
  return () => unregisterPluginSlot(name, code)
}

export function unregisterPluginSlot(slot, pluginCode) {
  slots.set(slot, (slots.get(slot) || []).filter(item => item.pluginCode !== pluginCode))
}

export function getPluginSlotEntries(slot, context = {}) {
  return (slots.get(String(slot || '').trim()) || []).filter(item => isPluginEnabled(item.pluginCode)).map(item => ({ ...item, props: { ...item.props, context: clone(context) }}))
}

export function registerPluginRoute(pluginCode, route) {
  const code = String(pluginCode || '').trim()
  if (!getPluginManifest(code) || !route?.path) return () => {}
  if (routes.some(item => item.name && item.name === route.name)) throw new Error(`插件路由重复注册：${code}:${route.name}`)
  const previousGuard = route.beforeEnter
  const entry = { ...route, meta: { ...(route.meta || {}), pluginCode: code }, beforeEnter: async(to, from) => {
    await syncPluginRuntime(true)
    const state = getPluginState(code)
    const installedEvidence = code === 'hotel' && to.meta?.hotelEvidence === true &&
      state.lifecycle_status === 'disabled' && state.enabled === false &&
      /^[a-f0-9]{32}$/.test(String(state.install_generation || ''))
    if (!isPluginEnabled(code) && !installedEvidence) return false
    return typeof previousGuard === 'function' ? previousGuard(to, from) : true
  } }
  routes.push(entry)
  return () => {
    const index = routes.indexOf(entry)
    if (index >= 0) routes.splice(index, 1)
  }
}

export function getPluginRoutes() {
  return routes.slice()
}

export function initializePluginRegistry(runtime = {}) {
  bootstrapStaticRegistry()
  if (initialized) return
  initialized = true
  runtimeContext = runtime
  void syncPluginRuntime()
}

function bootstrapStaticRegistry() {
  if (staticBootstrapped) return
  staticBootstrapped = true
  Object.values(pluginEntries).forEach(module => {
    const manifest = module.manifest || module.default?.manifest
    if (manifest) registerPluginManifest(manifest)
  })
  Object.values(routeEntries).forEach(module => {
    const routeList = Array.isArray(module.default) ? module.default : (module.default ? [module.default] : [])
    routeList.forEach(route => registerPluginRoute(route.pluginCode || route.meta?.pluginCode, route))
  })
}

bootstrapStaticRegistry()

export function resetPluginRegistry() {
  manifests.clear()
  slots.clear()
  routes.splice(0, routes.length)
  pluginStates.clear()
  initialized = false
  staticBootstrapped = false
  registeredPlugins.clear()
  runtimeLoadedAt = 0
}

export default { registerPluginManifest, registerPluginSlot, registerPluginRoute, getPluginRoutes, getPluginSlotEntries, initializePluginRegistry, isPluginEnabled, setPluginState }
