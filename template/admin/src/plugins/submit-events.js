import {
  canRegisterPluginEvent,
  getPluginManifest,
  isPluginEnabled
} from './registry'

const handlers = new Map()
const DEFAULT_TIMEOUT = 2000

function clone(value) {
  if (value === undefined || value === null) return value
  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(value)
    } catch (error) {
      // 代理对象（如 Vue reactive）不可结构化克隆，降级为 JSON 深拷贝
    }
  }
  return JSON.parse(JSON.stringify(value))
}

function sortHandlers(eventName) {
  return (handlers.get(eventName) || []).slice().sort((a, b) => (
    a.priority - b.priority || a.pluginCode.localeCompare(b.pluginCode) || a.handlerName.localeCompare(b.handlerName)
  ))
}

function validateDescriptor(descriptor = {}) {
  const event = String(descriptor.event || '').trim()
  const pluginCode = String(descriptor.pluginCode || '').trim()
  if (!event || !pluginCode || typeof descriptor.handler !== 'function') {
    throw new Error('插件提交事件注册项不完整')
  }
  if (!canRegisterPluginEvent(pluginCode, event)) {
    throw new Error(`插件未声明或未启用事件：${pluginCode}:${event}`)
  }
  const manifest = getPluginManifest(pluginCode)
  if (!manifest?.surfaces?.includes('admin')) {
    throw new Error(`插件不支持平台端：${pluginCode}`)
  }
  return {
    event,
    pluginCode,
    priority: Number.isFinite(Number(descriptor.priority)) ? Number(descriptor.priority) : 100,
    handler: descriptor.handler,
    handlerName: String(descriptor.handlerName || descriptor.handler.name || 'anonymous')
  }
}

export function registerSubmitHandler(descriptor) {
  const item = validateDescriptor(descriptor)
  const list = handlers.get(item.event) || []
  if (list.some(handler => handler.pluginCode === item.pluginCode && handler.handlerName === item.handlerName)) {
    throw new Error(`提交事件重复注册：${item.pluginCode}:${item.event}:${item.handlerName}`)
  }
  list.push(item)
  handlers.set(item.event, list)
  return () => unregisterSubmitHandler(item.event, item.pluginCode, item.handlerName)
}

export function unregisterSubmitHandler(event, pluginCode, handlerName = '') {
  const list = handlers.get(event) || []
  handlers.set(event, list.filter(item => !(item.pluginCode === pluginCode && (!handlerName || item.handlerName === handlerName))))
}

function assertNamespacedPayload(original, next, pluginCode) {
  const before = { ...clone(original) }
  const after = { ...clone(next) }
  const allowed = `plugin_payload.${pluginCode}`
  const beforePluginPayload = before.plugin_payload || {}
  const afterPluginPayload = after.plugin_payload || {}
  Object.keys(after).forEach(key => {
    if (key !== 'plugin_payload' && JSON.stringify(after[key]) !== JSON.stringify(before[key])) {
      throw new Error(`插件只能修改自己的命名空间：${pluginCode}`)
    }
  })
  Object.keys(afterPluginPayload).forEach(key => {
    if (key !== pluginCode && JSON.stringify(afterPluginPayload[key]) !== JSON.stringify(beforePluginPayload[key])) {
      throw new Error(`插件只能修改自己的命名空间：${allowed}`)
    }
  })
}

function normalizeBeforeResult(result, payload, pluginCode) {
  if (!result || typeof result !== 'object') throw new Error('插件提交事件返回结构无效')
  const action = String(result.action || 'continue')
  if (action === 'continue') return { allowed: true, payload }
  if (action === 'reject') {
    return {
      allowed: false,
      payload,
      message: String(result.message || '插件校验失败，请稍后重试'),
      fieldErrors: result.fieldErrors && typeof result.fieldErrors === 'object' ? result.fieldErrors : {}
    }
  }
  if (action !== 'replace' || !result.payload || typeof result.payload !== 'object') {
    throw new Error('插件提交事件返回结构无效')
  }
  assertNamespacedPayload(payload, result.payload, pluginCode)
  return { allowed: true, payload: clone(result.payload) }
}

function withTimeout(promise, timeout) {
  return Promise.race([
    promise,
    new Promise((_, reject) => setTimeout(() => reject(new Error('插件处理超时')), timeout))
  ])
}

export async function runPluginSubmitBefore(event, context = {}, options = {}) {
  let payload = clone(context.payload || {})
  const timeout = Math.max(1, Number(options.timeout || DEFAULT_TIMEOUT))
  for (const item of sortHandlers(event)) {
    if (!isPluginEnabled(item.pluginCode)) continue
    try {
      const result = await withTimeout(Promise.resolve(item.handler({
        ...clone(context),
        event_name: event,
        payload: clone(payload)
      })), timeout)
      const normalized = normalizeBeforeResult(result, payload, item.pluginCode)
      if (!normalized.allowed) return { ...normalized, pluginCode: item.pluginCode }
      payload = normalized.payload
    } catch (error) {
      return {
        allowed: false,
        payload,
        pluginCode: item.pluginCode,
        message: '插件校验失败，请稍后重试',
        error
      }
    }
  }
  return { allowed: true, payload }
}

export async function emitPluginSubmitAfter(event, context = {}, options = {}) {
  const results = []
  const timeout = Math.max(1, Number(options.timeout || DEFAULT_TIMEOUT))
  for (const item of sortHandlers(event)) {
    if (!isPluginEnabled(item.pluginCode)) continue
    try {
      const result = await withTimeout(Promise.resolve(item.handler({
        ...clone(context),
        event_name: event,
        payload: clone(context.payload || {})
      })), timeout)
      results.push({ pluginCode: item.pluginCode, status: 'succeeded', result })
    } catch (error) {
      results.push({ pluginCode: item.pluginCode, status: 'failed', error })
    }
  }
  return results
}

export function createRequestId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  return `plugin-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export function resetSubmitHandlers() {
  handlers.clear()
}

export default {
  registerSubmitHandler,
  unregisterSubmitHandler,
  runPluginSubmitBefore,
  emitPluginSubmitAfter,
  createRequestId
}
