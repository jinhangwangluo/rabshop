import { ElNotification } from 'element-plus'
import { defineStore } from 'pinia'
import WebSocketClient from '@/utils/websocket'
import {
  getBackendMessageUnreadCount,
  getBackendMessageWsToken
} from '@/api/backendMessage'

import { ref, shallowRef } from 'vue'

export const useBackendMessageStore = defineStore('backendMessage', () => {
  const client = shallowRef(null)
  const connected = ref(false)
  const unreadCount = ref(0)
  const latestMessage = ref(null)
  const initialized = ref(false)

  function resetInitialization() {
    initialized.value = false
  }

  function setClient(value) {
    client.value = value
  }

  /**
   * 初始化平台后台消息：先取未读数，再连接后台消息 WebSocket。
   */
  async function init() {
    if (initialized.value) {
      return
    }

    initialized.value = true
    try {
      await fetchUnreadCount()
      await connectWebSocket()
    } catch (error) {
      resetInitialization()
      unreadCount.value = 0
      console.warn('[BackendMessage] 初始化失败:', error)
    }
  }

  /**
   * 拉取平台后台消息未读数。
   */
  async function fetchUnreadCount() {
    try {
      const res = await getBackendMessageUnreadCount()
      unreadCount.value = res.data.count || 0
      return res.data.count || 0
    } catch (error) {
      unreadCount.value = 0
      return 0
    }
  }

  /**
   * 连接平台后台消息 WebSocket。
   */
  async function connectWebSocket() {
    if (client.value) {
      return
    }

    const res = await getBackendMessageWsToken()
    const token = res.data.token || ''
    const wsUrl = res.data.ws_url || ''
    if (!token || !wsUrl) {
      return
    }

    const webSocketClient = new WebSocketClient({
      url: wsUrl + '?token=' + encodeURIComponent(token),
      token,
      heartbeatInterval: 30000,
      reconnectInterval: 5000,
      maxReconnectAttempts: 10,
      onOpen: () => {
        connected.value = true
      },
      onClose: () => {
        connected.value = false
      },
      onMessage: (data) => {
        handleMessage(data)
      }
    })

    webSocketClient.connect()
    setClient(webSocketClient)
  }

  /**
   * 处理平台后台消息 WebSocket 推送。
   */
  function handleMessage(data) {
    if (!data || data.event !== 'backend_message') {
      return
    }

    const payload = data.data || {}
    const message = payload.message || {}
    latestMessage.value = message
    unreadCount.value = payload.unread_count || 0

    ElNotification({
      title: message.title || '后台消息',
      message: message.content || '您有一条新的后台消息',
      type: 'info',
      duration: 4500
    })
  }

  /**
   * 断开平台后台消息 WebSocket。
   */
  function disconnect() {
    if (client.value) {
      client.value.destroy()
    }
    client.value = null
    connected.value = false
    initialized.value = false
  }

  return {
    client,
    connected,
    unreadCount,
    latestMessage,
    initialized,
    init,
    fetchUnreadCount,
    connectWebSocket,
    handleMessage,
    disconnect
  }
})

export default useBackendMessageStore
