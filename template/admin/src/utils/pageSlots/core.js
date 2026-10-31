export const PAGE_SLOT_CHANNEL = 'admin-page-slots'
export const PAGE_SLOT_VERSION = 1

const SENSITIVE = /password|passwd|secret|token|authorization|credential|private[_-]?key|api[_-]?key|access[_-]?key|密码|口令|密钥|秘钥|私钥|凭据/i
const UNSAFE_KEYS = new Set(['__proto__', 'prototype', 'constructor'])

/** 插槽错误，只公开业务错误码和可展示的说明。 */
export class PageSlotError extends Error {
  constructor(code, message) {
    super(message)
    this.code = code
  }
}

/**
 * 判断字段是否包含敏感信息，显式禁止暴露的字段也会被排除。
 * @param {object} field 字段声明。
 * @returns {boolean} 是否禁止公开。
 */
export function isSensitiveField(field) {
  return field.expose === false || field.sensitive === true || field.inputType === 'password' ||
    SENSITIVE.test(`${field.id || ''} ${field.label || ''}`)
}

/**
 * 生成可结构化克隆的公开数据，排除内部对象、敏感键和原型键。
 * @param {*} value 待公开的值。
 * @param {string[]} secrets 当前上下文中禁止回传的敏感值。
 * @returns {*} JSON 兼容值。
 */
export function publicValue(value, secrets = [], depth = 0) {
  if (depth > 20 || value === undefined) return null
  if (value === null || typeof value === 'boolean') return value
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value === 'string') {
    return secrets.reduce((text, secret) => secret ? text.split(secret).join('[已隐藏]') : text, value)
  }
  if (Array.isArray(value)) return value.map(item => publicValue(item, secrets, depth + 1))
  if (Object.prototype.toString.call(value) !== '[object Object]') return null
  const result = {}
  Object.keys(value).forEach(key => {
    if (!UNSAFE_KEYS.has(key) && !SENSITIVE.test(key) && value[key] !== undefined && typeof value[key] !== 'function') {
      result[key] = publicValue(value[key], secrets, depth + 1)
    }
  })
  return result
}

const read = (value, fallback) => value === undefined ? fallback : (typeof value === 'function' ? value() : value)
const fail = (code, message) => { throw new PageSlotError(code, message) }

/**
 * 校验声明的值类型和约束，不做类型猜测或隐式转换。
 * @param {*} value 外部传入值。
 * @param {object} schema 字段或动作参数约束。
 * @param {string} label 错误提示名称。
 */
export function validateSlotValue(value, schema = {}, label = '值') {
  if (value === null && schema.nullable) return
  if (schema.enum && !schema.enum.some(item => item === value)) fail('INVALID_VALUE', `${label}不在允许选项中`)
  const type = schema.type
  if (type === 'string') {
    if (typeof value !== 'string') fail('INVALID_TYPE', `${label}必须是字符串`)
    if (schema.maxLength !== undefined && value.length > schema.maxLength) fail('INVALID_VALUE', `${label}超过最大长度`)
    if (schema.pattern && !new RegExp(schema.pattern).test(value)) fail('INVALID_VALUE', `${label}格式不正确`)
  } else if (type === 'number' || type === 'integer') {
    if (typeof value !== 'number' || !Number.isFinite(value) || (type === 'integer' && !Number.isInteger(value))) fail('INVALID_TYPE', `${label}必须是${type === 'integer' ? '整数' : '数字'}`)
    if ((schema.min !== undefined && value < schema.min) || (schema.max !== undefined && value > schema.max)) fail('INVALID_VALUE', `${label}超出允许范围`)
    if (schema.precision !== undefined && Math.abs(value * (10 ** schema.precision) - Math.round(value * (10 ** schema.precision))) > 0.000001) fail('INVALID_VALUE', `${label}小数位数过多`)
  } else if (type === 'boolean') {
    if (typeof value !== 'boolean') fail('INVALID_TYPE', `${label}必须是布尔值`)
  } else if (type === 'array') {
    if (!Array.isArray(value)) fail('INVALID_TYPE', `${label}必须是数组`)
    if (schema.maxItems !== undefined && value.length > schema.maxItems) fail('INVALID_VALUE', `${label}超过最大项数`)
    if (schema.minItems !== undefined && value.length < schema.minItems) fail('INVALID_VALUE', `${label}项数不足`)
    if (schema.unique && new Set(value.map(item => JSON.stringify(item))).size !== value.length) fail('INVALID_VALUE', `${label}不能包含重复项`)
    value.forEach(item => validateSlotValue(item, schema.items || {}, label))
  } else if (type === 'object') {
    if (!value || Array.isArray(value) || Object.prototype.toString.call(value) !== '[object Object]') fail('INVALID_TYPE', `${label}必须是对象`)
    const properties = schema.properties || {}
    Object.keys(value).forEach(key => {
      if (UNSAFE_KEYS.has(key) || !Object.prototype.hasOwnProperty.call(properties, key)) fail('INVALID_VALUE', `${label}包含未声明属性`)
      validateSlotValue(value[key], properties[key], label)
    })
    ;(schema.required || []).forEach(key => {
      if (!Object.prototype.hasOwnProperty.call(value, key)) fail('INVALID_VALUE', `${label}缺少必要属性`)
    })
  }
  if (!type && value && typeof value === 'object') {
    Object.keys(value).forEach(key => {
      if (UNSAFE_KEYS.has(key)) fail('INVALID_VALUE', `${label}包含禁止属性`)
      validateSlotValue(value[key], {}, label)
    })
  }
}

/**
 * 创建页面消息桥接。业务注册表与浏览器监听器分离，便于独立验证。
 * @param {Window|null} hostWindow 接收 message 的宿主窗口。
 * @returns {object} 页面注册、对端绑定、变更通知与释放接口。
 */
export function createPageSlotBridge(hostWindow = typeof window === 'undefined' ? null : window) {
  const entries = new Map()
  let peer = null
  let sequence = 0
  let executing = false
  let lastSnapshot = null
  let lastEntry = null
  let started = false

  function current() {
    return [...entries.values()].sort((a, b) => (b.priority - a.priority) || (b.order - a.order))[0] || null
  }

  function descriptors(entry) {
    if (!entry) return []
    try {
      const fields = read(entry.fields, []) || []
      if (!Array.isArray(fields)) throw new Error('字段声明必须是数组')
      return fields
    } catch (error) {
      entry.registrationError = true
      return []
    }
  }

  function secrets(entry) {
    const values = []
    descriptors(entry).filter(isSensitiveField).forEach(field => {
      const collect = value => {
        if (typeof value === 'string' && value) values.push(value)
        else if (value && typeof value === 'object') Object.values(value).forEach(collect)
      }
      try {
        collect(field.get?.())
      } catch (error) {
        entry.registrationError = true
      }
    })
    return values
  }

  function snapshot() {
    const entry = current()
    if (!entry) return null
    entry.registrationError = false
    const hidden = secrets(entry)
    const fields = []
    const values = {}
    descriptors(entry).filter(field => !isSensitiveField(field)).forEach(field => {
      fields.push({
        id: field.id,
        label: field.label,
        description: field.description || '',
        type: field.type || field.schema?.type || 'string',
        schema: read(field.schema, {}),
        options: read(field.options, []),
        required: !!read(field.required, false),
        available: !!read(field.available, true),
        writable: typeof field.set === 'function' && !!read(field.available, true)
      })
      if (typeof field.get === 'function') {
        try {
          values[field.id] = field.get()
        } catch (error) {
          entry.registrationError = true
        }
      }
    })
    const actions = Object.entries(read(entry.actions, {}) || {}).map(([id, action]) => ({
      id,
      description: action.description || '',
      schema: read(action.schema, { type: 'object', properties: {}}),
      available: !!read(action.available, true)
    }))
    return publicValue({
      instanceId: entry.instanceId,
      page: read(entry.page, {}),
      ready: !entry.registrationError && !!read(entry.ready, true),
      busy: executing || !!read(entry.busy, false),
      fields,
      values,
      actions
    }, hidden)
  }

  function post(type, payload, requestId = '', instanceId = '', destination = peer) {
    if (!destination || peer !== destination) return
    destination.targetWindow.postMessage({
      channel: PAGE_SLOT_CHANNEL, version: PAGE_SLOT_VERSION, type, requestId, instanceId,
      payload: publicValue(payload, secrets(current()))
    }, destination.origin)
  }

  function refresh() {
    const entry = current()
    if (entry !== lastEntry) {
      // 关闭顶层弹窗后，父表单也获得新上下文，不能接受弹窗打开前的旧写消息。
      if (entry) entry.instanceId = `page-${Date.now()}-${++sequence}`
      lastEntry = entry
    }
    const next = snapshot()
    const previous = lastSnapshot
    lastSnapshot = next
    if (JSON.stringify(next) === JSON.stringify(previous)) return
    // 元数据和数值分开比较，输入只推送变化值，避免每次输入重复发送完整定义。
    const metadata = value => value && { ...value, values: undefined }
    if (JSON.stringify(metadata(next)) !== JSON.stringify(metadata(previous))) post('context.changed', next, '', next?.instanceId || '')
    if (next && previous?.instanceId === next.instanceId) {
      const changes = Object.keys(next.values).filter(key => JSON.stringify(next.values[key]) !== JSON.stringify(previous.values[key]))
        .map(field => ({ field, value: next.values[field] }))
      if (changes.length) post('fields.changed', { changes }, '', next.instanceId)
    }
  }

  function assertCurrent(entry, instanceId) {
    if (!entry || current() !== entry || entry.instanceId !== instanceId) fail('STALE_CONTEXT', '页面上下文已失效，请重新获取')
    if (entry.registrationError || !read(entry.ready, true)) fail('NOT_READY', '页面正在加载或字段尚未准备好')
  }

  function errorResult(error) {
    return {
      ok: false,
      code: error instanceof PageSlotError ? error.code : 'OPERATION_FAILED',
      message: error instanceof PageSlotError ? error.message : '操作失败，请检查页面提示'
    }
  }

  async function dispatch(message, destination) {
    if (message.type === 'context.get') return { ok: true, context: snapshot() }
    const entry = current()
    if (!entry || entry.instanceId !== message.instanceId) fail('STALE_CONTEXT', '页面上下文已失效，请重新获取')
    const instanceId = entry.instanceId
    assertCurrent(entry, instanceId)
    if (executing || read(entry.busy, false)) fail('BUSY', '页面正在执行操作，请等待完成')
    const hidden = secrets(entry)
    const context = {
      isCurrent: () => current() === entry && entry.instanceId === instanceId && peer === destination,
      assertCurrent: () => {
        assertCurrent(entry, instanceId)
        if (peer !== destination) fail('PEER_DISCONNECTED', '消息会话已解绑')
      }
    }
    executing = true
    refresh()
    try {
      if (message.type === 'fields.patch') {
        const changes = message.payload?.changes
        if (!Array.isArray(changes) || !changes.length || changes.length > 200) fail('INVALID_REQUEST', '修改项数必须为 1 至 200')
        const results = []
        for (const change of changes) {
          try {
            context.assertCurrent()
            if (read(entry.busy, false)) fail('BUSY', '页面正在执行操作')
            const field = descriptors(entry).find(item => item.id === change?.field && !isSensitiveField(item))
            if (!field || UNSAFE_KEYS.has(change.field)) fail('UNKNOWN_FIELD', '字段未开放')
            if (!field.set || !read(field.available, true)) fail('FIELD_UNAVAILABLE', '字段当前不可编辑')
            validateSlotValue(change.value, read(field.schema, { type: field.type }), field.label)
            await field.set(change.value, context)
            context.assertCurrent()
            results.push({ field: field.id, ok: true, code: 'OK', message: '已修改' })
          } catch (error) {
            results.push({ field: typeof change?.field === 'string' ? change.field : '', ...errorResult(error) })
            if (!context.isCurrent()) break
          }
          refresh()
        }
        const successCount = results.filter(item => item.ok).length
        return publicValue({
          ok: successCount === changes.length,
          status: successCount === changes.length ? 'success' : (successCount ? 'partial' : 'failed'),
          results,
          skipped: changes.length - results.length
        }, hidden)
      }
      if (message.type === 'action.invoke') {
        const id = message.payload?.action
        const actions = read(entry.actions, {}) || {}
        const action = typeof id === 'string' && Object.prototype.hasOwnProperty.call(actions, id) && !UNSAFE_KEYS.has(id) ? actions[id] : null
        if (!action || typeof action.handler !== 'function') fail('UNKNOWN_ACTION', '动作未开放')
        if (!read(action.available, true)) fail('ACTION_UNAVAILABLE', '动作当前不可执行')
        const args = message.payload.args === undefined ? {} : message.payload.args
        validateSlotValue(args, read(action.schema, { type: 'object', properties: {}}), '动作参数')
        post('action.started', { action: id }, message.requestId, instanceId, destination)
        let result
        try {
          result = await action.handler(args, context)
          if (!result || typeof result.ok !== 'boolean') fail('INVALID_RESULT', '动作没有返回明确的执行结果')
        } catch (error) {
          result = errorResult(error)
        }
        result = publicValue(result, hidden)
        // 保存或关闭可能主动销毁上下文，仍应向原请求反馈真实结果。
        post('action.finished', { action: id, ...result }, message.requestId, instanceId, destination)
        return result
      }
      fail('UNKNOWN_MESSAGE', '消息类型不支持')
    } finally {
      // 唯一同步门禁覆盖整个异步任务，只有持有者可以释放。
      // eslint-disable-next-line require-atomic-updates
      executing = false
      refresh()
    }
  }

  async function receive(event) {
    const destination = peer
    if (!destination || event.source !== destination.targetWindow || event.origin !== destination.origin) return
    const message = event.data
    if (!message || message.channel !== PAGE_SLOT_CHANNEL) return
    if (typeof message.requestId !== 'string' || !message.requestId || message.requestId.length > 120) return
    if (message.version !== PAGE_SLOT_VERSION) {
      post('response', { ok: false, code: 'UNSUPPORTED_VERSION', message: '协议版本不支持' }, message.requestId, '', destination)
      return
    }
    const previous = destination.requests.get(message.requestId)
    if (previous) {
      const result = await previous
      if (peer === destination) post('response', result, message.requestId, message.instanceId || '', destination)
      return
    }
    // 同一绑定最多保留 2048 个请求，超过后要求重新绑定，不能淘汰记录后重复执行旧写操作。
    if (destination.requests.size >= 2048) {
      post('response', { ok: false, code: 'SESSION_LIMIT', message: '请重新绑定消息会话' }, message.requestId, '', destination)
      return
    }
    const task = Promise.resolve().then(() => {
      if (peer !== destination) fail('PEER_DISCONNECTED', '消息会话已解绑')
      if (JSON.stringify(message).length > 2 * 1024 * 1024) fail('INVALID_REQUEST', '消息过大')
      return dispatch(message, destination)
    }).catch(errorResult).then(result => ({ ...result, context: snapshot() }))
    destination.requests.set(message.requestId, task)
    const result = await task
    if (peer === destination) post('response', result, message.requestId, message.instanceId || '', destination)
  }

  return {
    /** 启动全局监听；重复调用不会重复添加监听器。 */
    start() {
      if (!started && hostWindow) hostWindow.addEventListener('message', receive)
      started = true
    },
    /** 绑定可信窗口和精确来源；解绑函数只释放本次绑定。 */
    bindPeer({ targetWindow, origin }) {
      if (!targetWindow || typeof targetWindow.postMessage !== 'function' || !/^https?:\/\//.test(origin) || new URL(origin).origin !== origin) {
        fail('INVALID_PEER', '必须绑定有效窗口和精确的 HTTP(S) origin')
      }
      const binding = { targetWindow, origin, requests: new Map() }
      peer = binding
      this.start()
      post('context.changed', snapshot())
      return () => { if (peer === binding) peer = null }
    },
    /** 注册一个已激活的页面或弹窗，返回释放函数。 */
    register(descriptor) {
      const order = ++sequence
      const entry = {
        ...descriptor,
        priority: descriptor.priority || 0,
        order,
        instanceId: `page-${Date.now()}-${order}`
      }
      entries.set(order, entry)
      refresh()
      return () => {
        entries.delete(order)
        refresh()
      }
    },
    refresh,
    getContext: snapshot,
    /** 手动按钮与消息共用动作通知；消息分发中的调用避免重复通知。 */
    async runAction(action, handler, nested = false) {
      const entry = current()
      const instanceId = entry?.instanceId
      const destination = peer
      const hidden = secrets(entry)
      const context = { isCurrent: () => current() === entry && entry?.instanceId === instanceId, assertCurrent: () => assertCurrent(entry, instanceId) }
      if (executing && !nested) return { ok: false, code: 'BUSY', message: '页面正在执行操作，请等待完成' }
      if (!entry || executing) return handler(context)
      executing = true
      refresh()
      post('action.started', { action }, '', instanceId, destination)
      let result
      try {
        result = await handler(context)
        return result
      } catch (error) {
        result = errorResult(error)
        return result
      } finally {
        post('action.finished', { action, ...publicValue(result, hidden) }, '', instanceId, destination)
        // runAction 的同步门禁保证此处释放的是本次持有的执行锁。
        // eslint-disable-next-line require-atomic-updates
        executing = false
        refresh()
      }
    },
    /** 释放宿主监听、对端及全部注册，供应用销毁与测试清理使用。 */
    dispose() {
      if (started && hostWindow) hostWindow.removeEventListener('message', receive)
      started = false
      peer = null
      entries.clear()
      lastSnapshot = null
      lastEntry = null
    }
  }
}
