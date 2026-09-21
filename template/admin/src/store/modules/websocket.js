import { defineStore } from 'pinia'
import { ref, shallowRef } from 'vue'
import WebSocketClient from '@/utils/websocket'
import { getWsToken } from '@/api/live'

export const useWebsocketStore = defineStore('websocket', () => {
  const client = shallowRef(null)
  const connected = ref(false)
  const currentRoomId = ref(0)
  const messageHistory = ref([])
  const onlineUsers = ref([])
  const chatMessages = ref([])
  const consoleActions = ref([])

  function setCurrentRoomId(roomId) {
    currentRoomId.value = roomId
  }

  function addMessageHistory(message) {
    messageHistory.value.unshift(message)
    if (messageHistory.value.length > 50) {
      messageHistory.value.pop()
    }
  }

  function addChatMessage(message) {
    chatMessages.value.unshift(message)
    if (chatMessages.value.length > 200) {
      chatMessages.value.pop()
    }
  }

  function addConsoleAction(action) {
    consoleActions.value.unshift(action)
    if (consoleActions.value.length > 50) {
      consoleActions.value.pop()
    }
  }

  function clearMessages() {
    chatMessages.value = []
    consoleActions.value = []
    onlineUsers.value = []
  }

  /**
   * 初始化并连接 WebSocket
   * @param {number|string} roomId 直播间 ID
   */
  async function connectWebSocket(roomId) {
    disconnectWebSocket()
    setCurrentRoomId(roomId)

    try {
      const res = await getWsToken(roomId)
      const token = res.data.token
      const wsUrl = res.data.ws_url

      const webSocketClient = new WebSocketClient({
        url: wsUrl + '?token=' + encodeURIComponent(token),
        token,
        heartbeatInterval: 30000,
        reconnectInterval: 5000,
        maxReconnectAttempts: 10,
        onOpen: () => {
          console.log('[WebSocket] 连接成功')
          connected.value = true
        },
        onClose: () => {
          console.log('[WebSocket] 连接关闭')
          connected.value = false
        },
        onError: (error) => {
          console.error('[WebSocket] 错误:', error)
        },
        onMessage: (data) => {
          handleMessage(data)
        }
      })

      webSocketClient.connect()
      client.value = webSocketClient
    } catch (error) {
      console.error('[WebSocket] 获取 Token 失败:', error)
    }
  }

  /**
   * 断开 WebSocket 连接。
   */
  function disconnectWebSocket() {
    if (client.value) {
      client.value.destroy()
    }
    client.value = null
    connected.value = false
    clearMessages()
    setCurrentRoomId(0)
  }

  /**
   * 手动重连。
   */
  function reconnectWebSocket() {
    if (client.value) {
      client.value.reconnect()
    }
  }

  /**
   * 处理收到的 WebSocket 消息。
   */
  function handleMessage(data) {
    const { type, event } = data || {}

    if (type === 'pong') {
      console.log('[WebSocket] 收到心跳 pong')
      return
    }

    if (type === 'error') {
      console.error('[WebSocket] 收到错误:', data)
      return
    }

    switch (event) {
      case 'live_chat':
        addChatMessage(data.data)
        break

      case 'console_action':
        addConsoleAction(data.data)
        if (data.data.action === 'get_online_users') {
          onlineUsers.value = data.data.online_users || []
        }
        break

      case 'goods_change':
        console.log('[WebSocket] 商品变更:', data.data)
        break

      case 'level_upgrade':
        console.log('[WebSocket] 用户升级:', data.data)
        break

      case 'room_rank_update':
        console.log('[WebSocket] 贡献榜更新:', data.data)
        break

      default:
        console.log('[WebSocket] 未知消息类型:', event)
    }

    addMessageHistory(data)
  }

  /**
   * 发送中控台操作指令。
   * @param {object} payload 指令参数
   * @param {string} payload.action 操作类型
   * @param {object} payload.actionData 操作数据
   * @returns {Promise<void>} 发送结果
   */
  function sendConsoleAction({ action, actionData }) {
    if (!client.value || !connected.value) {
      console.error('[WebSocket] 未连接')
      return Promise.reject(new Error('WebSocket 未连接'))
    }

    client.value.send({
      event: 'console_action',
      data: {
        room_id: currentRoomId.value,
        action,
        data: actionData
      }
    })
    return Promise.resolve()
  }

  /**
   * 发送聊天消息。
   * @param {string} content 聊天内容
   * @returns {Promise<void>} 发送结果
   */
  function sendChatMessage(content) {
    if (!client.value || !connected.value) {
      console.error('[WebSocket] 未连接')
      return Promise.reject(new Error('WebSocket 未连接'))
    }

    client.value.send({
      event: 'live_chat',
      data: {
        content,
        msg_type: 'text'
      }
    })
    return Promise.resolve()
  }

  function queryOnlineUsers() {
    return sendConsoleAction({
      action: 'get_online_users',
      actionData: {}
    })
  }

  function muteUser({ userId, duration = 3600, reason = '' }) {
    return sendConsoleAction({
      action: 'mute',
      actionData: {
        user_id: userId,
        duration,
        reason
      }
    })
  }

  function unmuteUser({ userId }) {
    return sendConsoleAction({
      action: 'unmute',
      actionData: {
        user_id: userId
      }
    })
  }

  function kickUser({ userId }) {
    return sendConsoleAction({
      action: 'kick',
      actionData: {
        user_id: userId
      }
    })
  }

  function startLive() {
    return sendConsoleAction({
      action: 'start_live',
      actionData: {}
    })
  }

  function endLive() {
    return sendConsoleAction({
      action: 'end_live',
      actionData: {}
    })
  }

  function toggleHot() {
    return sendConsoleAction({
      action: 'toggle_hot',
      actionData: {}
    })
  }

  function goodsShelf({ goodsId, status }) {
    return sendConsoleAction({
      action: 'goods_shelf',
      actionData: {
        goods_id: goodsId,
        status
      }
    })
  }

  function goodsStock({ goodsId, stockChange }) {
    return sendConsoleAction({
      action: 'goods_stock',
      actionData: {
        goods_id: goodsId,
        stock_change: stockChange
      }
    })
  }

  function goodsInfo({ goodsId, info }) {
    return sendConsoleAction({
      action: 'goods_info',
      actionData: {
        goods_id: goodsId,
        ...info
      }
    })
  }

  return {
    client,
    connected,
    currentRoomId,
    messageHistory,
    onlineUsers,
    chatMessages,
    consoleActions,
    connectWebSocket,
    disconnectWebSocket,
    reconnectWebSocket,
    handleMessage,
    sendConsoleAction,
    sendChatMessage,
    queryOnlineUsers,
    muteUser,
    unmuteUser,
    kickUser,
    startLive,
    endLive,
    toggleHot,
    goodsShelf,
    goodsStock,
    goodsInfo
  }
})

export default useWebsocketStore
