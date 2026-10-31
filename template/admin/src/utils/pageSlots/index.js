import { onActivated, onBeforeUnmount, onDeactivated, onMounted, ref, unref, watch, watchEffect } from 'vue'
import { createPageSlotBridge } from './core.js'

export { PageSlotError, isSensitiveField, validateSlotValue } from './core.js'
export { modelField, slotAction, validateForm, operationResult } from './helpers.js'

export const pageSlotBridge = createPageSlotBridge()

/**
 * 绑定未来 iframe 容器的窗口和来源。
 * @param {{targetWindow: Window, origin: string}} peer 可信对端。
 * @returns {Function} 本次绑定的释放函数。
 */
export function bindPeer(peer) {
  return pageSlotBridge.bindPeer(peer)
}

/**
 * 包装页面动作，让手动按钮和消息调用使用相同的执行结果及通知。
 * @param {string} action 注册的动作标识。
 * @param {Function} handler 返回明确执行结果的页面方法。
 * @returns {Function} 接收动作参数和可选上下文的处理方法。
 */
export function trackPageSlotAction(action, handler) {
  return (args, context) => pageSlotBridge.runAction(action, currentContext => handler(args, context || currentContext), !!context)
}

const resolve = (value, fallback) => value === undefined ? fallback : unref(typeof value === 'function' ? value() : value)

/**
 * 注册页面数据与事件插槽；自动处理缓存、弹窗和业务对象切换。
 * @param {object} options 页面信息、身份、激活/就绪/忙碌状态、字段和动作声明。
 * @returns {{notify: Function}} 显式通知方法，普通响应式变更会自动通知。
 */
export function usePageSlots(options) {
  const mounted = ref(false)
  const activated = ref(true)
  let release = null

  const descriptor = {
    page: () => resolve(options.page, {}),
    ready: () => !!resolve(options.ready, true),
    busy: () => !!resolve(options.busy, false),
    fields: () => resolve(options.fields, []),
    actions: () => resolve(options.actions, {}),
    priority: options.kind === 'dialog' ? 100 : 0
  }

  const stopRegistration = watch(() => [
    mounted.value && activated.value && !!resolve(options.active, true),
    resolve(options.identity, '')
  ], ([active]) => {
    if (release) release()
    release = active ? pageSlotBridge.register(descriptor) : null
  }, { flush: 'sync' })

  // 页面 getters、选项、约束和动作状态都在此收集依赖；停用时不读取旧页面数据。
  const stopNotifications = watchEffect(() => {
    if (mounted.value && activated.value && resolve(options.active, true)) {
      pageSlotBridge.refresh()
    }
  }, { flush: 'post' })

  onMounted(() => { mounted.value = true })
  onActivated(() => { activated.value = true })
  onDeactivated(() => { activated.value = false })
  onBeforeUnmount(() => {
    stopRegistration()
    stopNotifications()
    if (release) release()
  })

  return { notify: () => pageSlotBridge.refresh() }
}
