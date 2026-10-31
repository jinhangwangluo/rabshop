/**
 * WebSocket 工具类（Vue2 + Element UI）
 * 功能：
 * - 自动连接/断线重连（可配置最大重试次数）
 * - 心跳保活（ping/pong）
 * - 消息队列缓存（连接未建立时暂存）
 * - 手动重连/断开接口
 */
class WebSocketClient {
  constructor(options = {}) {
    this.url = options.url || '' // WebSocket 服务器地址（ws:// 或 wss://）
    this.token = options.token || '' // JWT Token
    this.heartbeatInterval = options.heartbeatInterval || 30000 // 心跳间隔，默认 30s
    this.reconnectInterval = options.reconnectInterval || 5000 // 重连间隔，默认 5s
    this.maxReconnectAttempts = options.maxReconnectAttempts || 10 // 最大重连次数
    this.onOpen = options.onOpen || (() => {}) // 连接成功回调
    this.onClose = options.onClose || (() => {}) // 连接关闭回调
    this.onError = options.onError || (() => {}) // 错误回调
    this.onMessage = options.onMessage || (() => {}) // 收到消息回调

    this.ws = null
    this.reconnectTimer = null
    this.heartbeatTimer = null
    this.messageQueue = [] // 待发送消息队列
    this.reconnectAttempts = 0 // 当前重连次数
    this.isManualClose = false // 是否手动关闭
    this.isConnected = false
  }

  /**
   * 初始化 WebSocket 连接
   */
  connect() {
    if (!this.url) {
      console.error('[WebSocket] url is required')
      return
    }

    // 如果已有连接，先关闭
    if (this.ws) {
      this.close()
    }

    this.isManualClose = false

    try {
      this.ws = new WebSocket(this.url)

      this.ws.onopen = () => {
        console.log('[WebSocket] 连接成功')
        this.isConnected = true
        this.reconnectAttempts = 0
        this.onOpen()
        this.startHeartbeat()
        this.flushMessageQueue()
      }

      this.ws.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data)
          this.onMessage(data)
        } catch (e) {
          console.error('[WebSocket] 消息解析失败:', e)
        }
      }

      this.ws.onerror = (error) => {
        console.error('[WebSocket] 错误:', error)
        this.onError(error)
      }

      this.ws.onclose = () => {
        console.log('[WebSocket] 连接关闭')
        this.isConnected = false
        this.stopHeartbeat()
        this.onClose()

        // 如果不是手动关闭，尝试重连
        if (!this.isManualClose) {
          this.scheduleReconnect()
        }
      }
    } catch (error) {
      console.error('[WebSocket] 创建连接失败:', error)
      this.onError(error)
      this.scheduleReconnect()
    }
  }

  /**
   * 发送消息
   * @param {Object} data - 消息数据（会自动转为 JSON）
   */
  send(data) {
    const message = JSON.stringify(data)

    if (this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(message)
    } else {
      // 连接未建立，加入队列
      console.log('[WebSocket] 连接未就绪，消息加入队列:', data)
      this.messageQueue.push(message)
    }
  }

  /**
   * 刷新消息队列（连接成功后调用）
   */
  flushMessageQueue() {
    if (this.messageQueue.length > 0) {
      console.log(`[WebSocket] 刷新消息队列，共${this.messageQueue.length}条`)
      this.messageQueue.forEach(msg => {
        this.ws.send(msg)
      })
      this.messageQueue = []
    }
  }

  /**
   * 启动心跳
   */
  startHeartbeat() {
    this.stopHeartbeat() // 清除旧定时器

    this.heartbeatTimer = setInterval(() => {
      if (this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.send({ type: 'ping', timestamp: Date.now() })
        console.log('[WebSocket] 发送心跳 ping')
      }
    }, this.heartbeatInterval)
  }

  /**
   * 停止心跳
   */
  stopHeartbeat() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer)
      this.heartbeatTimer = null
    }
  }

  /**
   * 安排重连
   */
  scheduleReconnect() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('[WebSocket] 达到最大重连次数，放弃重连')
      return
    }

    this.reconnectAttempts++
    console.log(`[WebSocket] ${this.reconnectInterval / 1000}s 后尝试第${this.reconnectAttempts}次重连...`)

    this.reconnectTimer = setTimeout(() => {
      this.connect()
    }, this.reconnectInterval)
  }

  /**
   * 手动重连
   */
  reconnect() {
    console.log('[WebSocket] 手动重连')
    this.stopReconnectTimer()
    this.reconnectAttempts = 0
    this.connect()
  }

  /**
   * 手动断开连接
   */
  close() {
    this.isManualClose = true
    this.stopReconnectTimer()
    this.stopHeartbeat()

    if (this.ws) {
      this.ws.close()
      this.ws = null
    }

    this.isConnected = false
  }

  /**
   * 停止重连定时器
   */
  stopReconnectTimer() {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer)
      this.reconnectTimer = null
    }
  }

  /**
   * 销毁实例
   */
  destroy() {
    this.close()
    this.onOpen = () => {}
    this.onClose = () => {}
    this.onError = () => {}
    this.onMessage = () => {}
    this.messageQueue = []
  }
}

export default WebSocketClient
