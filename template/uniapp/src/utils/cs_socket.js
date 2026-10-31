import { createSocketClient } from '@/utils/socket'
import { getRuntimeConfig } from '@/utils/runtime_config'
import { getCsWsToken } from '@/api/cs'

/**
 * 客服 WebSocket 客户端。
 *
 * 基于 UniappSocketClient 封装，处理 CS 场景的连接、收发、事件监听。
 * 使用方式：
 *   const client = new CsSocketClient()
 *   await client.connect(sessionId)
 *   client.onCsMessage(handler)
 *   client.sendMessage({ content, msg_uuid, extra })
 *   client.destroy()
 */
class CsSocketClient {
  constructor() {
    /** @type {import('@/utils/socket').UniappSocketClient|null} */
    this.socket = null
    this.sessionId = ''
    this.userId = 0
    this._disposers = []
  }

  /**
   * 建立客服 WebSocket 连接。
   *
   * 流程：调用 `getCsWsToken()` 获取 token → 从 `getRuntimeConfig()` 获取 ws_url → 连接。
   *
   * @param {string} sessionId 会话 ID。
   * @returns {Promise<{code:number, msg:string}>}
   */
  async connect(sessionId) {
    if (!sessionId) {
      return { code: 400, msg: '会话 ID 不能为空' }
    }

    this.sessionId = sessionId

    const tokenRes = await getCsWsToken()
    if (tokenRes.code !== 200 || !tokenRes.data || !tokenRes.data.token) {
      return { code: tokenRes.code || 500, msg: tokenRes.msg || '获取 WS token 失败' }
    }

    const { token, user_id } = tokenRes.data
    this.userId = user_id || 0

    const { wsBaseUrl } = getRuntimeConfig()

    this.socket = createSocketClient()
    const result = await this.socket.connect({
      url: wsBaseUrl,
      token
    })

    return result
  }

  /**
   * 发送客服消息。
   *
   * @param {{ content: string, msg_uuid?: string, msg_type?: string, extra?: Object }} params
   * @returns {Promise<boolean>}
   */
  async sendMessage({ content, msg_uuid = '', msg_type = 'text', extra = {} } = {}) {
    if (!this.socket || !this.sessionId) {
      return false
    }

    return this.socket.send({
      event: 'cs_message',
      data: {
        session_id: this.sessionId,
        content,
        msg_type,
        msg_uuid: msg_uuid || this._generateUuid(),
        extra: extra && typeof extra === 'object' ? extra : {}
      }
    })
  }

  /**
   * 发送输入中状态。
   *
   * @param {boolean} isTyping 是否正在输入。
   * @returns {Promise<boolean>}
   */
  async sendTyping(isTyping = true) {
    if (!this.socket || !this.sessionId) {
      return false
    }

    return this.socket.send({
      event: 'cs_typing',
      data: {
        session_id: this.sessionId,
        is_typing: isTyping
      }
    })
  }

  /**
   * 关闭会话。
   *
   * @param {string} [remark] 关闭备注。
   * @returns {Promise<boolean>}
   */
  async closeSession(remark = '') {
    if (!this.socket || !this.sessionId) {
      return false
    }

    return this.socket.send({
      event: 'cs_close',
      data: {
        session_id: this.sessionId,
        remark
      }
    })
  }

  /**
   * 监听客服消息推送（含 AI 流式分片）。
   *
   * 回调参数：`(payload: Object) => void`
   * payload.data 包含：`id, sender_type, sender_id, content, message_type, send_time, extra`
   *
   * @param {Function} handler
   * @returns {Function} 取消监听函数。
   */
  onCsMessage(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.onEvent('cs_message', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 监听消息落库确认。
   *
   * 服务端在收到 `cs_message` 并成功落库后推送 `cs_message_ack`，用于客户端清理待确认状态。
   *
   * @param {Function} handler 回调函数，参数为服务端 ACK payload。
   * @returns {Function} 取消监听函数。
   */
  onCsMessageAck(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.onEvent('cs_message_ack', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 监听服务端业务错误消息。
   *
   * 服务端可能通过 `{ type: 'error', code, message }` 返回消息发送失败原因。
   *
   * @param {Function} handler 回调函数，参数为服务端错误 payload。
   * @returns {Function} 取消监听函数。
   */
  onErrorMessage(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.onEvent('error', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 监听底层连接状态。
   *
   * @param {Function} handler 回调函数，参数为状态 payload。
   * @returns {Function} 取消监听函数。
   */
  onStatus(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.on('status', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 监听转人工通知。
   *
   * @param {Function} handler
   * @returns {Function}
   */
  onCsTransfer(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.onEvent('cs_transfer', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 监听会话结束通知。
   *
   * @param {Function} handler
   * @returns {Function}
   */
  onCsClose(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.onEvent('cs_close', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 监听对方输入中状态。
   *
   * @param {Function} handler
   * @returns {Function}
   */
  onCsTyping(handler) {
    if (!this.socket || typeof handler !== 'function') {
      return () => {}
    }

    const disposer = this.socket.onEvent('cs_typing', handler)
    this._disposers.push(disposer)
    return disposer
  }

  /**
   * 获取连接状态。
   *
   * @returns {{ connected: boolean, status: string }}
   */
  getState() {
    if (!this.socket) {
      return { connected: false, status: 'idle' }
    }

    return this.socket.getState()
  }

  /**
   * 销毁客户端，关闭连接并移除所有监听。
   */
  destroy() {
    if (this.socket) {
      this.socket.destroy()
      this.socket = null
    }

    this._disposers = []
    this.sessionId = ''
    this.userId = 0
  }

  /**
   * @returns {string}
   * @private
   */
  _generateUuid() {
    const timestamp = Date.now().toString(36)
    const random = Math.random().toString(36).substring(2, 10)
    return `${timestamp}-${random}`
  }
}

export default CsSocketClient
