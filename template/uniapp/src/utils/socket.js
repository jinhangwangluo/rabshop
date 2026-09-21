import { getLiveRoomWsToken } from '@/api/live'
import { DEFAULT_RUNTIME_CONFIG } from '@/utils/config'
import { normalizePublicErrorMessage } from '@/utils/error'
import { getRuntimeConfig } from '@/utils/runtime_config'

/**
 * UniappSocketClient
 * 统一管理直播间 WebSocket 连接、重连、心跳、消息派发与事件订阅。
 * 使用方式：
 * - const client = createSocketClient({ roomId, url, token })
 * - await client.connect() 或 await client.connectLiveRoom(roomId)
 * - client.on('message', handler) / client.onEvent('live_chat', handler)
 */
class UniappSocketClient {
  constructor(options = {}) {
    this.listeners = {
      open: new Set(),
      close: new Set(),
      error: new Set(),
      message: new Set(),
      status: new Set()
    }
    this.eventListeners = new Map()
    this.messageQueue = []
    this.socketTask = null
    this.connected = false
    this.manualClose = false
    this.status = 'idle'
    this.reconnectTimer = null
    this.heartbeatTimer = null
    this.pongTimeoutTimer = null
    this.lastPongTime = 0
    this.pongTimeoutMs = 15000
    this.reconnectAttempts = 0
    this.roomId = 0
    this.url = ''
    this.token = ''
    this.heartbeatInterval = 30000
    this.reconnectInterval = 5000
    this.maxReconnectAttempts = 10
    this.applyOptions(options)
  }

  applyOptions(options = {}) {
    if (options.url !== undefined) {
      this.url = String(options.url || '').trim()
    }

    if (options.token !== undefined) {
      this.token = String(options.token || '').trim()
    }

    if (options.roomId !== undefined) {
      this.roomId = Number(options.roomId || 0)
    }

    if (options.heartbeatInterval !== undefined) {
      this.heartbeatInterval = Number(options.heartbeatInterval || 30000)
    }

    if (options.reconnectInterval !== undefined) {
      this.reconnectInterval = Number(options.reconnectInterval || 5000)
    }

    if (options.maxReconnectAttempts !== undefined) {
      this.maxReconnectAttempts = Number(options.maxReconnectAttempts || 10)
    }
  }

  /**
   * 连接指定直播间（自动获取 ws_url 与 token 并建立连接）
   * @param {number|string} roomId 直播间 ID
   * @param {Object} options 可选参数：{ heartbeatInterval, reconnectInterval, maxReconnectAttempts }
   * @returns {Promise<{code:number,msg:string,data?:{ws_url:string,token:string}}>} 统一响应
   */
  async connectLiveRoom(roomId, options = {}) {
    const targetRoomId = Number(roomId || 0)
    if (targetRoomId <= 0) {
      return {
        code: 400,
        msg: '直播间 ID 无效',
        data: null
      }
    }

    const tokenRes = await getLiveRoomWsToken(targetRoomId)
    if (tokenRes.code !== 200 || !tokenRes.data) {
      return tokenRes
    }

    const runtimeConfig = getRuntimeConfig()
    const configWsUrl = String(DEFAULT_RUNTIME_CONFIG.wsBaseUrl || '').trim()
    const runtimeWsUrl = String(runtimeConfig.wsBaseUrl || '').trim()
    const optionWsUrl = String(options.url || '').trim()

    this.applyOptions({
      ...options,
      roomId: targetRoomId,
      url: optionWsUrl || configWsUrl || runtimeWsUrl || tokenRes.data.ws_url,
      token: tokenRes.data.token
    })

    await this.connect()

    return tokenRes
  }

  /**
   * 建立 WebSocket 连接
   * @param {Object} options { url?:string, token?:string, roomId?:number }
   * @returns {Promise<{code:number,msg:string,data?:{url:string,room_id:number}}>} 统一响应
   */
  async connect(options = {}) {
    this.applyOptions(options)

    if (!this.url) {
      this.updateStatus('error')
      this.emit('error', { message: '缺少 WebSocket 地址' })
      return {
        code: 400,
        msg: '缺少 WebSocket 地址',
        data: null
      }
    }

    this.manualClose = false
    this.clearReconnectTimer()
    this.clearHeartbeat()
    this.disposeSocketTask()
    this.updateStatus('connecting')

    const socketUrl = this.buildSocketUrl()

    return new Promise((resolve) => {
      let settled = false
      const socketTask = uni.connectSocket({
        url: socketUrl,
        complete: () => {}
      })

      this.socketTask = socketTask

      socketTask.onOpen((event) => {
        if (socketTask !== this.socketTask) {
          return
        }

        this.connected = true
        this.reconnectAttempts = 0
        this.updateStatus('connected')
        this.startHeartbeat()
        this.flushMessageQueue()
        this.emit('open', event)

        if (!settled) {
          settled = true
          resolve({
            code: 200,
            msg: '连接成功',
            data: { url: socketUrl, room_id: this.roomId }
          })
        }
      })

      socketTask.onMessage((event) => {
        if (socketTask !== this.socketTask) {
          return
        }

        const payload = this.parseMessage(event.data)
        if (payload && payload.type === 'pong' && payload.event === 'heartbeat') {
          this.lastPongTime = Date.now()
          this.clearPongTimeout()
          this.updateStatus('connected')
        }

        this.emit('message', payload)
        this.emitEvent(payload)
      })

      socketTask.onError((error) => {
        if (socketTask !== this.socketTask) {
          return
        }

        this.connected = false
        this.updateStatus('error')
        const errorMessage = normalizePublicErrorMessage(
          error && (error.errMsg || error.message),
          'WebSocket 连接失败'
        )
        this.emit('error', {
          ...(error || {}),
          message: errorMessage,
          msg: errorMessage,
          errMsg: errorMessage
        })

        if (!settled) {
          settled = true
          resolve({
            code: 500,
            msg: errorMessage,
            data: null
          })
        }
      })

      socketTask.onClose((event) => {
        if (socketTask !== this.socketTask) {
          return
        }

        this.connected = false
        this.clearHeartbeat()
        this.updateStatus(this.manualClose ? 'closed' : 'disconnected')
        this.emit('close', event)

        if (!this.manualClose) {
          this.scheduleReconnect()
        }

        if (!settled) {
          settled = true
          resolve({
            code: 500,
            msg: 'WebSocket 连接已关闭',
            data: null
          })
        }
      })
    })
  }

  buildSocketUrl() {
    if (!this.token) {
      return this.url
    }

    const separator = this.url.includes('?') ? '&' : '?'
    return `${this.url}${separator}token=${encodeURIComponent(this.token)}`
  }

  parseMessage(rawData) {
    if (typeof rawData === 'string') {
      try {
        return JSON.parse(rawData)
      } catch (error) {
        return {
          event: 'raw_message',
          type: 'raw_message',
          raw: rawData
        }
      }
    }

    if (rawData && typeof rawData === 'object') {
      return rawData
    }

    return {
      event: 'raw_message',
      type: 'raw_message',
      raw: rawData
    }
  }

  /**
   * 发送消息（未连接时可入队等待）
   * @param {Object|string} data 任意可序列化的数据
   * @param {{queue?:boolean}} options queue=false 时不入队
   * @returns {Promise<boolean>|boolean} 发送结果
   */
  async send(data, options = {}) {
    const payload = typeof data === 'string' ? data : JSON.stringify(data)
    const allowQueue = options.queue !== false

    if (!this.socketTask || !this.connected) {
      if (allowQueue) {
        this.messageQueue.push(payload)
      }
      return false
    }

    return new Promise((resolve) => {
      this.socketTask.send({
        data: payload,
        success: () => {
          resolve(true)
        },
        fail: (error) => {
          if (allowQueue) {
            this.messageQueue.push(payload)
          }
          this.emit('error', error)
          resolve(false)
        }
      })
    })
  }

  flushMessageQueue() {
    if (!this.connected || !this.socketTask || this.messageQueue.length === 0) {
      return
    }

    const queue = [...this.messageQueue]
    this.messageQueue = []
    queue.forEach((message) => {
      this.send(message, { queue: false })
    })
  }

  startHeartbeat() {
    this.clearHeartbeat()
    this.lastPongTime = Date.now()

    this.heartbeatTimer = setInterval(() => {
      if (!this.connected) {
        return
      }

      this.send({
        event: 'heartbeat',
        data: {
          room_id: this.roomId || 0
        },
        timestamp: Date.now()
      }, { queue: false })

      this.startPongTimeout()
    }, this.heartbeatInterval)
  }

  clearHeartbeat() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer)
      this.heartbeatTimer = null
    }
    this.clearPongTimeout()
  }

  startPongTimeout() {
    this.clearPongTimeout()
    this.pongTimeoutTimer = setTimeout(() => {
      this.emit('error', { message: '心跳超时，未收到 pong 响应' })
      this.connected = false
      this.clearHeartbeat()
      this.updateStatus('disconnected')
      this.disposeSocketTask()
      this.scheduleReconnect()
    }, this.pongTimeoutMs)
  }

  clearPongTimeout() {
    if (this.pongTimeoutTimer) {
      clearTimeout(this.pongTimeoutTimer)
      this.pongTimeoutTimer = null
    }
  }

  scheduleReconnect() {
    if (this.manualClose) {
      return
    }

    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      this.updateStatus('exhausted')
      return
    }

    this.reconnectAttempts += 1
    this.updateStatus('reconnecting', {
      reconnectAttempts: this.reconnectAttempts
    })

    this.clearReconnectTimer()
    this.reconnectTimer = setTimeout(() => {
      this.connect()
    }, this.reconnectInterval)
  }

  clearReconnectTimer() {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer)
      this.reconnectTimer = null
    }
  }

  reconnect() {
    this.manualClose = false
    this.reconnectAttempts = 0
    return this.connect()
  }

  close(options = {}) {
    this.manualClose = options.manual !== false
    this.clearReconnectTimer()
    this.clearHeartbeat()
    this.connected = false
    this.updateStatus('closed')
    this.disposeSocketTask(options)
  }

  disposeSocketTask(options = {}) {
    const currentTask = this.socketTask
    this.socketTask = null

    if (!currentTask) {
      return
    }

    try {
      currentTask.close({
        code: options.code || 1000,
        reason: options.reason || 'socket_closed'
      })
    } catch (error) {
      this.emit('error', error)
    }
  }

  destroy() {
    this.close({ manual: true, reason: 'socket_destroyed' })
    this.messageQueue = []
    this.listeners = {
      open: new Set(),
      close: new Set(),
      error: new Set(),
      message: new Set(),
      status: new Set()
    }
    this.eventListeners = new Map()
  }

  sendChatMessage(content, extraData = {}) {
    return this.send({
      event: 'live_chat',
      data: {
        content,
        msg_type: 'text',
        ...extraData
      }
    })
  }

  sendConsoleAction(action, actionData = {}) {
    return this.send({
      event: 'console_action',
      data: {
        room_id: this.roomId || 0,
        action,
        data: actionData
      }
    })
  }

  on(type, handler) {
    if (!this.listeners[type] || typeof handler !== 'function') {
      return () => {}
    }

    this.listeners[type].add(handler)
    return () => this.off(type, handler)
  }

  off(type, handler) {
    if (!this.listeners[type]) {
      return
    }

    this.listeners[type].delete(handler)
  }

  onEvent(event, handler) {
    if (!event || typeof handler !== 'function') {
      return () => {}
    }

    const handlers = this.eventListeners.get(event) || new Set()
    handlers.add(handler)
    this.eventListeners.set(event, handlers)

    return () => this.offEvent(event, handler)
  }

  offEvent(event, handler) {
    const handlers = this.eventListeners.get(event)
    if (!handlers) {
      return
    }

    handlers.delete(handler)
    if (handlers.size === 0) {
      this.eventListeners.delete(event)
    }
  }

  emit(type, payload) {
    const handlers = this.listeners[type]
    if (!handlers) {
      return
    }

    handlers.forEach((handler) => {
      try {
        handler(payload, this.getState())
      } catch (error) {
        console.error('[Socket] 事件处理失败:', error)
      }
    })
  }

  emitEvent(payload) {
    const eventName = payload?.event || payload?.type
    if (!eventName) {
      return
    }

    const handlers = this.eventListeners.get(eventName)
    if (!handlers) {
      return
    }

    handlers.forEach((handler) => {
      try {
        handler(payload, this.getState())
      } catch (error) {
        console.error('[Socket] 消息处理失败:', error)
      }
    })
  }

  updateStatus(status, extra = {}) {
    this.status = status
    this.emit('status', {
      status,
      ...extra
    })
  }

  getState() {
    return {
      connected: this.connected,
      status: this.status,
      roomId: this.roomId,
      url: this.url,
      reconnectAttempts: this.reconnectAttempts,
      queuedMessages: this.messageQueue.length
    }
  }
}

/**
 * 工厂方法：创建 Socket 客户端实例
 * @param {Object} options 初始配置
 * @returns {UniappSocketClient}
 */
export function createSocketClient(options = {}) {
  return new UniappSocketClient(options)
}

/**
 * 默认单例：满足大多数场景的一体化客户端
 */
const socketClient = createSocketClient()

export default socketClient
