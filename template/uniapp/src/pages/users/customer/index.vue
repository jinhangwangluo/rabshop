<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="customer-page">
    <app-safe-top title="在线客服" background="#fff" @back="handleBack">

    </app-safe-top>

    <scroll-view
      scroll-y
      class="customer-scroll"
      :scroll-into-view="scrollIntoView"
      :scroll-top="scrollTop"
      :show-scrollbar="false"
      :scroll-with-animation="scrollWithAnimation"
    >
      <view class="customer-content">
        <view class="time-divider">
          <text class="time-divider__text">{{ currentTimeLabel }}</text>
        </view>

        <view v-for="item in messages" :id="item.anchorId" :key="item._key" class="message-block">
          <!-- AI / 客服消息（左侧白气泡 + 头像） -->
          <view v-if="item.sender_type === 'ai' || item.sender_type === 'staff'" class="message-row message-row--left">
            <image class="message-avatar" :src="getMessageAvatar(item)" mode="aspectFill"></image>
            <view class="message-bubble message-bubble--left" :class="{ 'message-bubble--media': item.message_type === 'image' }">
              <image
                v-if="item.message_type === 'image'"
                class="message-image"
                :src="item.content"
                mode="aspectFill"
                @click="handlePreviewMessageImage(item.content)"
              ></image>
              <text v-else class="message-bubble__text message-bubble__text--left">{{ item.content }}</text>
            </view>
          </view>

          <!-- 用户消息（右侧橙色气泡） -->
          <view v-else-if="item.sender_type === 'user'" class="message-row message-row--right">
            <view class="message-bubble message-bubble--right" :class="{ 'message-bubble--media': item.message_type === 'image' }">
              <image
                v-if="item.message_type === 'image'"
                class="message-image"
                :src="item.content"
                mode="aspectFill"
                @click="handlePreviewMessageImage(item.content)"
              ></image>
              <text v-else class="message-bubble__text message-bubble__text--right">{{ item.content }}</text>
            </view>
          </view>

          <!-- 系统消息（居中灰字） -->
          <view v-else-if="item.sender_type === 'system'" class="message-system">
            <text class="message-system__text">{{ item.content }}</text>
          </view>
        </view>

        <!-- 对方正在输入提示 -->
        <view v-if="isTyping" class="typing-indicator">
          <view class="typing-indicator__dots">
            <view class="typing-dot"></view>
            <view class="typing-dot"></view>
            <view class="typing-dot"></view>
          </view>
          <text class="typing-indicator__text">对方正在输入...</text>
        </view>
      </view>
    </scroll-view>

    <view class="customer-footer">
      <scroll-view scroll-x class="quick-action-scroll" :show-scrollbar="false">
        <view class="quick-action-list">
          <view
            v-for="item in quickActions"
            :key="item"
            class="quick-action"
            hover-class="quick-action--hover"
            @click="handleQuickAction(item)"
          >
            <text class="quick-action__text">{{ item }}</text>
          </view>
        </view>
      </scroll-view>

      <view class="customer-input-bar">
        <view class="customer-input">
          <input
            v-model="draftMessage"
            class="customer-input__field"
            type="text"
            confirm-type="send"
            placeholder="请输入您的问题..."
            :placeholder-style="uiColors.placeholderStyle"
            @confirm="handleSend"
          />
          <view class="customer-input__emoji" @click="handleEmoji">
            <uni-icons type="smiley" size="18" :color="uiColors.iconSecondary"></uni-icons>
          </view>
        </view>
        <view class="customer-input-bar__plus" @click="handleAttachment">
          <uni-icons type="plusempty" size="20" :color="uiColors.iconPlaceholder"></uni-icons>
        </view>
      </view>
    </view>

    <uni-popup ref="orderPopup" type="bottom" :safe-area="false">
      <view class="order-popup">
        <view class="order-popup__header">
          <view class="order-popup__title-wrap">
            <text class="order-popup__title">{{ orderPopupTitle }}</text>
            <text class="order-popup__subtitle">请选择需要咨询的订单</text>
          </view>
          <view class="order-popup__close" @click="closeOrderPopup">
            <uni-icons type="closeempty" size="22" :color="uiColors.iconSecondary"></uni-icons>
          </view>
        </view>

        <scroll-view scroll-y class="order-popup__scroll" :show-scrollbar="false">
          <view v-if="orderLoading" class="order-popup__empty">
            <text class="order-popup__empty-text">订单加载中...</text>
          </view>
          <view v-else-if="!orderList.length" class="order-popup__empty">
            <text class="order-popup__empty-text">暂无可选择订单</text>
          </view>
          <view v-else class="order-popup__list">
            <view
              v-for="order in orderList"
              :key="order.order_sn"
              class="order-card"
              hover-class="order-card--hover"
              @click="handleSelectOrder(order)"
            >
              <view class="order-card__top">
                <text class="order-card__sn">{{ order.order_sn }}</text>
                <text class="order-card__status">{{ order.status_text }}</text>
              </view>
              <text class="order-card__goods">{{ order.goods_summary }}</text>
              <view class="order-card__bottom">
                <text class="order-card__time">{{ order.create_time || '-' }}</text>
                <text class="order-card__amount">¥{{ order.pay_amount_text }}</text>
              </view>
            </view>
          </view>
        </scroll-view>
      </view>
    </uni-popup>

    <app-floating-button />
  </view>
</template>

<script setup>
import CsSocketClient from '@/utils/cs_socket.js'
import uploadUtil from '@/utils/util'
import {
  createCsSession,
  getCsHistory,
  markCsSessionRead,
  sendCsMessage as sendCsMessageHttp
} from '@/api/cs.js'
import {
  getOrderDetail,
  getOrderList
} from '@/api/order.js'
import { ref, computed, nextTick } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'

const CS_AVATAR = '/static/images/avatar_default.jpg'
const AI_CS_AVATAR = '/pages/users/static/ai-cs-avatar.png'
const QUICK_ACTIONS = ['查询订单', '物流信息', '退款政策', '人工客服']
const MAX_ATTACHMENT_IMAGE_COUNT = 6
const UI_COLORS = {
  pageBackground: 'var(--surface-page, #f9f9f9)',
  iconRegular: 'var(--text-regular, #3f3f46)',
  iconSecondary: 'var(--text-secondary, #71717a)',
  iconPlaceholder: 'var(--text-placeholder, #a1a1aa)',
  placeholderStyle: 'color: var(--text-placeholder, #a1a1aa);'
}

let globalSeq = 0

/**
 * 标准化客服消息。
 *
 * @param {Object} msg 原始消息。
 * @returns {Object} 附带列表 key 与滚动锚点的消息对象。
 */
function normalizeMessage(msg) {
  return {
    ...msg,
    _key: `cs-msg-${msg.id || 0}-${++globalSeq}`,
    anchorId: `customer-message-${msg.id || 0}-${globalSeq}`
  }
}


const orderPopup = ref(null)

defineOptions({ name: 'UserCustomerPage' })

const csAvatar = ref(CS_AVATAR)

const aiCsAvatar = ref(AI_CS_AVATAR)

const currentTimeLabel = ref(formatTimeLabel())

const draftMessage = ref('')

const quickActions = ref(QUICK_ACTIONS)

const uiColors = ref(UI_COLORS)

const messages = ref([])

const scrollIntoView = ref('')

const scrollTop = ref(0)

const scrollWithAnimation = ref(true)

const scrollTimer = ref(null)

const sessionId = ref('')

const serviceType = ref(1)

const csSocket = ref(null)

const isTyping = ref(false)

const typingTimer = ref(null)

const initialized = ref(false)

const orderPopupMode = ref('query')

const orderLoading = ref(false)

const orderList = ref([])

const pendingMessages = ref({})

const pendingAckTimers = ref({})

const reconnecting = ref(false)

const merchantId = ref(0)

const uploadingAttachment = ref(false)
onLoad(async (options = {}) => {
  initQuery(options)
  await initPage()
})
const orderPopupTitle = computed(() => {
  return orderPopupMode.value === 'logistics' ? '选择物流订单' : '选择咨询订单'
})
onUnload(() => {
  if (csSocket.value) {
    csSocket.value.destroy()
    csSocket.value = null
  }
  clearTypingTimer()
  clearScrollTimer()
  clearAllPendingAckTimers()
})
/**
 * 解析客服页入参。
 *
 * @param {Object} options 页面路由参数。
 * @returns {void}
 */
function initQuery(options = {}) {
  const rawMerchantId = Number(options.merchant_id || options.merchantId || 0)
  merchantId.value = Number.isNaN(rawMerchantId) || rawMerchantId < 0 ? 0 : rawMerchantId
}

/**
 * 初始化客服页。
 *
 * @returns {Promise<void>} 创建会话、加载历史消息并连接 WebSocket。
 */
async function initPage() {
  if (initialized.value) return
  initialized.value = true

  // 1. 创建或获取会话
  const sessionRes = await createCsSession(merchantId.value)
  if (sessionRes.code !== 200 || !sessionRes.data) {
    uni.showToast({ title: '获取会话失败', icon: 'none' })
    return
  }
  sessionId.value = sessionRes.data.session_id || ''
  serviceType.value = sessionRes.data.service_type || 1

  // 2. 加载历史消息
  await loadHistory()

  // 3. 标记已读
  if (sessionId.value) {
    markCsSessionRead(sessionId.value).catch(() => {})
  }

  // 4. 建立 WebSocket
  await connectSocket()
}

/**
 * 加载客服历史消息。
 *
 * @returns {Promise<void>} 成功时更新消息列表，失败时保持当前页面可用。
 */
async function loadHistory() {
  if (!sessionId.value) return
  try {
    const res = await getCsHistory({ session_id: sessionId.value, limit: 50, latest: 1 })
    if (res.code === 200 && res.data && Array.isArray(res.data.list)) {
      const list = res.data.list
        .filter((item) => item.sender_type !== 'user' || String(item.content || '').trim())
        .map((item) => normalizeMessage({
          id: item.id,
          sender_type: item.sender_type,
          content: item.content || '',
          message_type: item.message_type || 'text',
          create_time: item.create_time,
          extra: item.extra || null,
          msg_uuid: item.msg_uuid || '',
          pending: false
        }))
      const lastMessage = list[list.length - 1]
      scrollWithAnimation.value = false
      scrollIntoView.value = lastMessage ? lastMessage.anchorId : ''
      scrollTop.value += 100000
      messages.value = list
      scrollToBottom({ animated: false })
    }
  } catch (e) {
    // 历史消息加载失败不阻塞页面
  }
}

/**
 * 建立客服 WebSocket 连接。
 *
 * @returns {Promise<void>} 连接成功后注册消息、转人工、关闭和输入中事件。
 */
async function connectSocket() {
  if (!sessionId.value) return
  reconnecting.value = !!csSocket.value
  csSocket.value = new CsSocketClient()
  const result = await csSocket.value.connect(sessionId.value)
  if (result.code !== 200) {
    console.warn('[客服] WS 连接失败:', result.msg)
    reconnecting.value = false
    return
  }

  registerSocketEvents()
  await syncRecentHistory()
  reconnecting.value = false
}

/**
 * 注册客服 WebSocket 事件。
 *
 * @returns {void}
 */
function registerSocketEvents() {
  if (!csSocket.value) return

  // 消息推送（含 AI 流式分片）
  csSocket.value.onCsMessage((payload) => {
    const data = (payload && payload.data) || payload || {}
    handleIncomingMessage(data)
  })

  csSocket.value.onCsMessageAck((payload) => {
    const data = (payload && payload.data) || payload || {}
    handleMessageAck(data)
  })

  csSocket.value.onErrorMessage((payload) => {
    const data = (payload && payload.data) || payload || {}
    handleMessageSendError(data)
  })

  // 转人工通知
  csSocket.value.onCsTransfer(() => {
    handleTransfer()
  })

  // 会话结束
  csSocket.value.onCsClose((payload) => {
    const data = (payload && payload.data) || payload || {}
    messages.value.push(normalizeMessage({
      id: 0,
      sender_type: 'system',
      content: data.close_time
        ? `会话已结束（${data.close_time}）`
        : '会话已结束',
      create_time: '',
      extra: null
    }))
    scrollToBottom()
  })

  // 对方输入中
  csSocket.value.onCsTyping((payload) => {
    const data = (payload && payload.data) || payload || {}
    isTyping.value = !!(data.is_typing)
    if (isTyping.value) {
      resetTypingTimer()
    }
  })

  csSocket.value.onStatus((payload) => {
    const status = payload && payload.status ? payload.status : ''
    if (status === 'disconnected' || status === 'reconnecting' || status === 'error') {
      reconnecting.value = true
      return
    }
    if (status === 'connected' && reconnecting.value) {
      recoverAfterReconnect()
      reconnecting.value = false
    }
  })
}

/**
 * 处理收到的消息（含 AI 流式分片）。
 *
 * @param {Object} data 服务端推送消息。
 * @returns {void}
 */
function handleIncomingMessage(data) {
  const senderType = data.sender_type || ''
  const content = data.content || ''
  const extra = data.extra || null
  const id = data.id || 0
  const msgUuid = data.msg_uuid || ''
  const isStreaming = !!(extra && extra.streaming)
  const isDone = !!(extra && extra.done)
  const isFinalized = !!(extra && extra.finalized)

  // 系统消息直接追加
  if (senderType === 'system') {
    messages.value.push(normalizeMessage({
      id: id || 0,
      sender_type: 'system',
      content,
      create_time: data.send_time || '',
      extra
    }))
    scrollToBottom()
    return
  }

  // AI 流式消息：同一条消息内累加
  if (senderType === 'ai' && isStreaming) {
    if (isFinalized) {
      // finalized 消息：更新真实 id 并锁定
      const streamingIdx = findLastStreamingAiIndex()
      if (streamingIdx >= 0) {
        messages.value[streamingIdx].id = id
        messages.value[streamingIdx]._key = `cs-msg-${id}-${++globalSeq}`
        messages.value[streamingIdx].anchorId = `customer-message-${id}-${globalSeq}`
        messages.value[streamingIdx].extra = extra
        // 强制触发视图更新
        messages.value = [...messages.value]
      }
      return
    }

    if (isDone) {
      // done 块：锁定消息
      const idx = findLastStreamingAiIndex()
      if (idx >= 0) {
        messages.value[idx].extra = extra
        messages.value = [...messages.value]
      }
      return
    }

    // streaming 块：累加内容
    if (content) {
      const idx = findLastStreamingAiIndex()
      if (idx >= 0) {
        messages.value[idx].content += content
        messages.value = [...messages.value]
      } else {
        // 首次 streaming 块：新建临时 AI 消息
        messages.value.push(normalizeMessage({
          id: 0,
          sender_type: 'ai',
          content,
          create_time: data.send_time || '',
          extra
        }))
      }
      scrollToBottom()
    }
    return
  }

  if (senderType === 'user' && !String(content || '').trim()) {
    return
  }

  // 普通消息（非流式）：直接追加
  const normalizedMessage = normalizeMessage({
    id: id || 0,
    sender_type: senderType,
    content,
    message_type: data.message_type || 'text',
    create_time: data.send_time || '',
    extra,
    msg_uuid: msgUuid,
    pending: false
  })

  if (mergeIncomingMessage(normalizedMessage)) {
    scrollToBottom()
    return
  }

  messages.value.push(normalizedMessage)
  scrollToBottom()
}

/**
 * 合并服务端回推消息。
 *
 * 使用场景：用户发送时页面会先乐观展示一条消息，服务端随后通过 WebSocket 回推同一条消息。
 * 优先按 msg_uuid 精确匹配；旧服务端未推送 msg_uuid 时，回退匹配最近一条同内容 pending 用户消息。
 *
 * @param {Object} incoming 服务端回推消息。
 * @returns {boolean} 成功合并返回 true，否则返回 false。
 */
function mergeIncomingMessage(incoming) {
  const index = findMergeableMessageIndex(incoming)
  if (index < 0) {
    return false
  }

  messages.value.splice(index, 1, {
    ...messages.value[index],
    ...incoming,
    _key: messages.value[index]._key,
    anchorId: messages.value[index].anchorId,
    pending: false
  })

  const msgUuid = incoming.msg_uuid || ''
  if (msgUuid && pendingMessages.value[msgUuid]) {
    clearPendingAckTimer(msgUuid)
    delete pendingMessages.value[msgUuid]
  }

  return true
}

/**
 * 查找可与服务端回推合并的本地消息。
 *
 * @param {Object} incoming 服务端回推消息。
 * @returns {number} 可合并消息索引，未找到时返回 -1。
 */
function findMergeableMessageIndex(incoming) {
  const msgUuid = incoming.msg_uuid || ''
  if (msgUuid) {
    const uuidIndex = messages.value.findIndex((item) => item.msg_uuid === msgUuid)
    if (uuidIndex >= 0) {
      return uuidIndex
    }
  }

  const incomingId = Number(incoming.id || 0)
  if (incomingId > 0) {
    const idIndex = messages.value.findIndex((item) => Number(item.id || 0) === incomingId)
    if (idIndex >= 0) {
      return idIndex
    }
  }

  if (incoming.sender_type !== 'user' || !incoming.content) {
    return -1
  }

  for (let i = messages.value.length - 1; i >= 0; i--) {
    const item = messages.value[i]
    if (
      item.pending &&
      item.sender_type === 'user' &&
      item.content === incoming.content
    ) {
      return i
    }
  }

  return -1
}

/**
 * 查找最后一条 AI 流式消息的索引。
 *
 * @returns {number} 匹配消息索引，未找到时返回 -1。
 */
function findLastStreamingAiIndex() {
  for (let i = messages.value.length - 1; i >= 0; i--) {
    const msg = messages.value[i]
    if (msg.sender_type === 'ai' && msg.extra && msg.extra.streaming) {
      return i
    }
  }
  return -1
}

/**
 * 处理转人工通知。
 *
 * @returns {void}
 */
function handleTransfer() {
  serviceType.value = 2
}

/**
 * 获取客服消息头像。
 *
 * @param {Object} message 消息对象。
 * @returns {string} AI 消息返回 AI 客服图标，人工客服返回默认客服头像。
 */
function getMessageAvatar(message = {}) {
  return message.sender_type === 'ai' ? aiCsAvatar.value : csAvatar.value
}

/**
 * 发送消息。
 *
 * @returns {Promise<void>} 优先通过 WebSocket 发送，失败时回退 HTTP。
 */
async function handleSend() {
  const content = String(draftMessage.value || '').trim()
  if (!content) {
    uni.showToast({ title: '请输入咨询内容', icon: 'none' })
    return
  }

  await sendMessageContent(content)
}

/**
 * 发送指定客服消息。
 *
 * @param {string} content 消息正文。
 * @param {Object} options 消息选项。
 * @param {string} [options.message_type] 消息类型。
 * @param {Object} [options.extra] 需要随历史消息保存的业务上下文。
 * @returns {Promise<void>} 优先通过 WebSocket 发送，失败时回退 HTTP。
 */
async function sendMessageContent(content = '', options = {}) {
  const normalizedContent = String(content || '').trim()
  if (!normalizedContent) {
    return
  }

  if (!sessionId.value) {
    uni.showToast({ title: '会话未建立', icon: 'none' })
    return
  }

  const messageType = String(options.message_type || 'text')
  const extra = options.extra && typeof options.extra === 'object' ? options.extra : {}
  const msgUuid = generateUuid()
  const sendTime = formatTimeLabel()

  // 乐观更新：先显示用户消息
  messages.value.push(normalizeMessage({
    id: 0,
    sender_type: 'user',
    content: normalizedContent,
    message_type: messageType,
    create_time: sendTime,
    extra,
    msg_uuid: msgUuid,
    pending: true
  }))
  registerPendingMessage({
    msg_uuid: msgUuid,
    content: normalizedContent,
    message_type: messageType,
    extra,
    create_time: sendTime
  })
  draftMessage.value = ''
  scrollToBottom()

  // 通过 WebSocket 发送
  if (csSocket.value) {
    const sent = await csSocket.value.sendMessage({
      content: normalizedContent,
      msg_uuid: msgUuid,
      msg_type: messageType,
      extra
    })
    if (!sent) {
      // WS 失败，HTTP 回退
      await sendMessageByHttp({
        session_id: sessionId.value,
        content: normalizedContent,
        message_type: messageType,
        extra,
        msg_uuid: msgUuid
      })
    }
  } else {
    // 无 WS 连接，直接 HTTP
    await sendMessageByHttp({
      session_id: sessionId.value,
      content: normalizedContent,
      message_type: messageType,
      extra,
      msg_uuid: msgUuid
    })
  }
}

/**
 * 快捷操作。
 *
 * @param {string} text 快捷问题文本。
 * @returns {Promise<void>} 需要直接发送文本时等待消息进入统一发送链路。
 */
async function handleQuickAction(text = '') {
  const action = String(text || '').trim()
  if (action === '查询订单') {
    openOrderSelector('query')
    return
  }
  if (action === '物流信息') {
    openOrderSelector('logistics')
    return
  }
  if (action === '退款政策') {
    await sendQuickText('退款政策')
    return
  }
  if (action === '人工客服') {
    await sendQuickText('人工客服')
    return
  }
  await sendQuickText(action)
}

/**
 * 发送快捷文本。
 *
 * @param {string} text 需要发送给 AI 或人工客服的文本。
 * @returns {Promise<void>} 消息进入统一发送链路后完成。
 */
async function sendQuickText(text = '') {
  await sendMessageContent(text)
}

/**
 * 打开订单选择弹窗。
 *
 * @param {string} mode query 表示查询订单，logistics 表示物流信息。
 * @returns {Promise<void>} 成功时加载订单列表并打开弹窗。
 */
async function openOrderSelector(mode = 'query') {
  orderPopupMode.value = mode === 'logistics' ? 'logistics' : 'query'
  openOrderPopup()
  await fetchOrderList()
}

/**
 * 打开订单弹窗。
 *
 * @returns {void}
 */
function openOrderPopup() {
  if (orderPopup.value) {
    orderPopup.value.open()
  }
}

/**
 * 关闭订单弹窗。
 *
 * @returns {void}
 */
function closeOrderPopup() {
  if (orderPopup.value) {
    orderPopup.value.close()
  }
}

/**
 * 获取当前用户订单列表。
 *
 * @returns {Promise<void>} 成功时更新弹窗订单列表。
 */
async function fetchOrderList() {
  if (orderLoading.value) return
  orderLoading.value = true
  try {
    const params = { page: 1, limit: 20 }
    if (merchantId.value > 0) {
      params.merchant_id = merchantId.value
    }
    const res = await getOrderList(params)
    const data = res && res.data ? res.data : {}
    const list = Array.isArray(data.list) ? data.list : []
    orderList.value = list.map((order) => normalizeOrder(order))
  } catch (error) {
    uni.showToast({ title: '订单加载失败', icon: 'none' })
  } finally {
    orderLoading.value = false
  }
}

/**
 * 选择订单。
 *
 * @param {Object} order 订单列表项。
 * @returns {Promise<void>} 根据当前弹窗模式展示订单摘要或物流信息。
 */
async function handleSelectOrder(order) {
  closeOrderPopup()
  const detail = await fetchOrderDetail(order)
  if (orderPopupMode.value === 'logistics') {
    await sendMessageContent(
      buildLogisticsMessage(detail),
      { extra: buildOrderMessageExtra(detail, 'logistics_query') }
    )
    return
  }
  await sendMessageContent(
    buildOrderSummaryMessage(detail),
    { extra: buildOrderMessageExtra(detail, 'order_query') }
  )
}

/**
 * 获取订单详情。
 *
 * @param {Object} order 订单列表项。
 * @returns {Promise<Object>} 成功时返回详情，失败时回退列表项。
 */
async function fetchOrderDetail(order) {
  const orderSn = order && order.order_sn ? order.order_sn : ''
  if (!orderSn) {
    return order
  }
  try {
    const res = await getOrderDetail({ order_sn: orderSn })
    if (res.code === 200 && res.data) {
      return normalizeOrder(res.data)
    }
  } catch (error) {
    // 详情失败时回退列表数据
  }
  return order
}

/**
 * 标准化订单列表项。
 *
 * @param {Object} order 原始订单。
 * @returns {Object} 页面可直接展示的订单对象。
 */
function normalizeOrder(order = {}) {
  const goodsList = Array.isArray(order.goods)
    ? order.goods
    : (Array.isArray(order.goods_preview) ? order.goods_preview : [])
  return {
    ...order,
    goods_summary: resolveOrderGoodsSummary(goodsList),
    pay_amount_text: formatAmount(order.pay_amount),
    delivery_name: String(order.delivery_name || ''),
    delivery_id: String(order.delivery_id || ''),
    delivery_code: String(order.delivery_code || '')
  }
}

/**
 * 解析订单商品摘要。
 *
 * @param {Array} goodsList 订单商品列表。
 * @returns {string} 商品摘要。
 */
function resolveOrderGoodsSummary(goodsList = []) {
  if (!Array.isArray(goodsList) || !goodsList.length) {
    return '订单商品'
  }
  const names = goodsList
    .map((goods) => String(goods.goods_name || '').trim())
    .filter((name) => name)
  if (!names.length) {
    return '订单商品'
  }
  const summary = names.slice(0, 2).join('、')
  return names.length > 2 ? `${summary} 等${names.length}件` : summary
}

/**
 * 生成订单摘要消息。
 *
 * @param {Object} order 订单详情。
 * @returns {string} 订单摘要文案。
 */
function buildOrderSummaryMessage(order) {
  return [
    '我想咨询以下订单：',
    `订单号：${order.order_sn || '-'}`,
    `商品：${order.goods_summary || '订单商品'}`,
    `状态：${order.status_text || '-'}`,
    `实付金额：¥${formatAmount(order.pay_amount)}`,
    `下单时间：${order.create_time || '-'}`
  ].join('\n')
}

/**
 * 生成物流信息消息。
 *
 * @param {Object} order 订单详情。
 * @returns {string} 物流信息文案。
 */
function buildLogisticsMessage(order) {
  return [
    '我想查询以下订单的物流信息：',
    `订单号：${order.order_sn || '-'}`,
    `商品：${order.goods_summary || '订单商品'}`,
    `订单状态：${order.status_text || '-'}`,
    `下单时间：${order.create_time || '-'}`
  ].join('\n')
}

/**
 * 构建订单咨询消息上下文。
 *
 * @param {Object} order 订单详情。
 * @param {string} scene 业务场景。
 * @returns {Object} 可写入客服消息 extra 的订单快照。
 */
function buildOrderMessageExtra(order = {}, scene = 'order_query') {
  return {
    scene,
    intent: 'request_human',
    order: {
      order_id: Number(order.order_id || 0),
      order_sn: String(order.order_sn || ''),
      merchant_id: Number(order.merchant_id || 0),
      merchant_name: String(order.merchant_name || ''),
      status_text: String(order.status_text || ''),
      order_type: Number(order.order_type || 0),
      order_type_text: String(order.order_type_text || ''),
      goods_summary: String(order.goods_summary || ''),
      pay_amount: Number(order.pay_amount || 0),
      create_time: order.create_time || '',
      delivery_name: String(order.delivery_name || ''),
      delivery_id: String(order.delivery_id || ''),
      delivery_code: String(order.delivery_code || ''),
      delivery_time: order.delivery_time || ''
    }
  }
}

/**
 * 格式化金额。
 *
 * @param {number|string} amount 金额。
 * @returns {string} 两位小数字符串。
 */
function formatAmount(amount) {
  const value = Number(amount || 0)
  return value.toFixed(2)
}

/**
 * 返回上一页。
 *
 * @returns {void} 无上一页时回到用户中心 tab。
 */
function handleBack() {
  const pages = getCurrentPages()
  if (Array.isArray(pages) && pages.length > 1) {
    uni.navigateBack({
      fail: () => {
        uni.switchTab({ url: '/pages/user/index' })
      }
    })
    return
  }
  uni.switchTab({ url: '/pages/user/index' })
}

/**
 * 处理更多按钮点击。
 *
 * @returns {void}
 */
function handleMore() {
  uni.showToast({ title: '更多功能待接入', icon: 'none' })
}

/**
 * 处理表情按钮点击。
 *
 * @returns {void}
 */
function handleEmoji() {
  uni.showToast({ title: '表情面板待接入', icon: 'none' })
}

/**
 * 处理附件按钮点击。
 *
 * @returns {void}
 */
function handleAttachment() {
  if (uploadingAttachment.value) {
    return
  }
  if (!sessionId.value) {
    uni.showToast({ title: '会话未建立', icon: 'none' })
    return
  }

  uploadingAttachment.value = true
  const finishUpload = () => {
    uploadingAttachment.value = false
  }

  uploadUtil.uploadImageChange(
    {
      url: 'file/upload',
      name: 'file',
      count: MAX_ATTACHMENT_IMAGE_COUNT,
      is_load: true
    },
    (res) => handleAttachmentUploadSuccess(res),
    () => {
      uni.showToast({ title: '图片上传失败', icon: 'none' })
    }
  ).then(finishUpload).catch(finishUpload)
}

/**
 * 处理附件图片上传成功。
 *
 * @param {Object} res 上传接口响应。
 * @returns {void} 上传成功后发送图片消息。
 */
function handleAttachmentUploadSuccess(res = {}) {
  const data = res && res.data && typeof res.data === 'object' ? res.data : {}
  const filePath = String(data.filePath || '').trim()
  if (!filePath) {
    uni.showToast({ title: '上传返回缺少文件地址', icon: 'none' })
    return
  }

  sendMessageContent(filePath, {
    message_type: 'image',
    extra: {
      attachment_type: 'image',
      filePath,
      name: String(data.name || ''),
      realName: String(data.realName || '')
    }
  }).catch(() => {
    uni.showToast({ title: '图片发送失败', icon: 'none' })
  })
}

/**
 * 预览聊天图片。
 *
 * @param {string} url 图片地址。
 * @returns {void}
 */
function handlePreviewMessageImage(url = '') {
  const imageUrl = String(url || '').trim()
  if (!imageUrl) {
    return
  }

  uni.previewImage({
    urls: [imageUrl],
    current: imageUrl
  })
}

/**
 * 滚动到最新消息。
 *
 * @param {Object} options 滚动选项。
 * @param {boolean} [options.animated=true] 是否启用滚动动画。
 * @returns {void}
 */
function scrollToBottom(options = {}) {
  const lastMessage = messages.value[messages.value.length - 1]
  const anchorId = lastMessage ? lastMessage.anchorId : ''
  const animated = options.animated !== false
  clearScrollTimer()
  scrollWithAnimation.value = animated

  nextTick(() => {
    if (!animated) {
      scrollIntoView.value = anchorId
      scrollTop.value += 100000
      scrollTimer.value = setTimeout(() => {
        scrollWithAnimation.value = true
        scrollTimer.value = null
      }, 80)
      return
    }

    scrollIntoView.value = ''
    scrollTop.value += 100000
    scrollTimer.value = setTimeout(() => {
      scrollIntoView.value = anchorId
      scrollTop.value += 100000
      scrollTimer.value = null
    }, 30)
  })
}

/**
 * 清理滚动定时器。
 *
 * @returns {void}
 */
function clearScrollTimer() {
  if (scrollTimer.value) {
    clearTimeout(scrollTimer.value)
    scrollTimer.value = null
  }
}

/**
 * 重置输入中状态自动隐藏定时器。
 *
 * @returns {void}
 */
function resetTypingTimer() {
  clearTypingTimer()
  typingTimer.value = setTimeout(() => {
    isTyping.value = false
  }, 5000)
}

/**
 * 清理输入中状态定时器。
 *
 * @returns {void}
 */
function clearTypingTimer() {
  if (typingTimer.value) {
    clearTimeout(typingTimer.value)
    typingTimer.value = null
  }
}

/**
 * 格式化当前时间。
 *
 * @returns {string} HH:mm 格式时间。
 */
function formatTimeLabel() {
  const now = new Date()
  const h = String(now.getHours()).padStart(2, '0')
  const m = String(now.getMinutes()).padStart(2, '0')
  return `${h}:${m}`
}

/**
 * 生成消息去重 UUID。
 *
 * @returns {string} 本地唯一消息标识。
 */
function generateUuid() {
  const timestamp = Date.now().toString(36)
  const random = Math.random().toString(36).substring(2, 10)
  return `${timestamp}-${random}`
}

/**
 * 注册待确认消息。
 *
 * @param {Object} message 待确认消息。
 * @returns {void}
 */
function registerPendingMessage(message) {
  const msgUuid = String(message.msg_uuid || '')
  if (!msgUuid) {
    return
  }

  pendingMessages.value[msgUuid] = {
    ...message,
    retryCount: 0,
    confirmed: false
  }
  startPendingAckTimer(msgUuid)
}

/**
 * 启动消息 ACK 超时计时器。
 *
 * @param {string} msgUuid 消息 UUID。
 * @returns {void}
 */
function startPendingAckTimer(msgUuid) {
  clearPendingAckTimer(msgUuid)

  pendingAckTimers.value[msgUuid] = setTimeout(async () => {
    delete pendingAckTimers.value[msgUuid]
    const pending = pendingMessages.value[msgUuid]
    if (!pending || pending.confirmed) {
      return
    }

    const nextRetryCount = (pending.retryCount || 0) + 1
    pendingMessages.value[msgUuid] = {
      ...pending,
      retryCount: nextRetryCount
    }

    await sendMessageByHttp({
      session_id: sessionId.value,
      content: pending.content,
      message_type: pending.message_type || 'text',
      extra: pending.extra || {},
      msg_uuid: msgUuid
    })
  }, 5000)
}

/**
 * 清理指定消息的 ACK 计时器。
 *
 * @param {string} msgUuid 消息 UUID。
 * @returns {void}
 */
function clearPendingAckTimer(msgUuid) {
  const timer = pendingAckTimers.value[msgUuid]
  if (timer) {
    clearTimeout(timer)
    delete pendingAckTimers.value[msgUuid]
  }
}

/**
 * 清理全部 ACK 计时器。
 *
 * @returns {void}
 */
function clearAllPendingAckTimers() {
  Object.keys(pendingAckTimers.value).forEach((msgUuid) => {
    clearPendingAckTimer(msgUuid)
  })
}

/**
 * 处理消息 ACK。
 *
 * @param {Object} data ACK 数据。
 * @returns {void}
 */
function handleMessageAck(data) {
  const msgUuid = String(data.msg_uuid || '')
  if (!msgUuid) {
    return
  }

  const pending = pendingMessages.value[msgUuid]
  if (!pending) {
    return
  }

  const serverTime = data.send_time || data.create_time || ''
  clearPendingAckTimer(msgUuid)
  pendingMessages.value[msgUuid] = {
    ...pending,
    confirmed: true,
    server_id: data.id || pending.server_id || 0,
    send_time: serverTime || pending.send_time || '',
    create_time: serverTime || pending.create_time || ''
  }

  messages.value = messages.value.map((item) => {
    if (item.msg_uuid !== msgUuid) {
      return item
    }

    return {
      ...item,
      id: data.id || item.id || 0,
      content: data.content || item.content,
      create_time: serverTime || item.create_time,
      pending: false,
      extra: item.extra || null
    }
  })
  delete pendingMessages.value[msgUuid]
}

/**
 * 处理消息发送业务错误。
 *
 * @param {Object} data 服务端错误数据，可能包含 msg_uuid/message。
 * @returns {void} 撤销本地 pending 气泡并提示失败原因。
 */
function handleMessageSendError(data = {}) {
  const msgUuid = String(data.msg_uuid || data.msgUuid || data.uuid || '')
  const message = String(data.message || data.msg || '消息发送失败').trim()
  handleMessageSendFailure(msgUuid, message)
}

/**
 * 处理消息发送失败。
 *
 * @param {string} msgUuid 消息 UUID。
 * @param {string} message 失败提示。
 * @returns {void}
 */
function handleMessageSendFailure(msgUuid = '', message = '消息发送失败') {
  revokePendingMessage(msgUuid)
  uni.showToast({
    title: String(message || '消息发送失败').trim(),
    icon: 'none'
  })
}

/**
 * 撤销一条本地待确认消息。
 *
 * 优先按 msg_uuid 精确撤销；服务端未回传 UUID 时，回退撤销最近一条 pending 用户消息。
 *
 * @param {string} msgUuid 消息 UUID。
 * @returns {boolean} 成功撤销返回 true。
 */
function revokePendingMessage(msgUuid = '') {
  let targetUuid = String(msgUuid || '')
  let index = -1

  if (targetUuid) {
    index = messages.value.findIndex((item) => item.pending && item.msg_uuid === targetUuid)
  }

  if (index < 0) {
    for (let i = messages.value.length - 1; i >= 0; i--) {
      const item = messages.value[i]
      if (item.pending && item.sender_type === 'user') {
        index = i
        targetUuid = item.msg_uuid || targetUuid
        break
      }
    }
  }

  if (index < 0) {
    return false
  }

  messages.value.splice(index, 1)
  if (targetUuid) {
    clearPendingAckTimer(targetUuid)
    delete pendingMessages.value[targetUuid]
  }
  return true
}

/**
 * 通过 HTTP 发送客服消息。
 *
 * @param {Object} data 请求参数。
 * @returns {Promise<void>} 成功时确认本地消息，失败时保留 pending 等待补偿。
 */
async function sendMessageByHttp(data) {
  try {
    const res = await sendCsMessageHttp(data, { showError: false })
    if (res && res.code === 200 && res.data) {
      handleMessageAck(res.data)
      return
    }
    if (res && !res.error) {
      handleMessageSendFailure(data.msg_uuid || '', res.msg || '消息发送失败')
    }
  } catch (error) {
    // 保持本地 pending，等待 ACK 或下次重试
  }
}

/**
 * 连接恢复后的补偿处理。
 *
 * @returns {Promise<void>} 先补发仍未确认的本地消息，再拉取服务端缺口。
 */
async function recoverAfterReconnect() {
  await retryPendingMessages()
  await syncRecentHistory()
}

/**
 * 补发仍未确认的本地消息。
 *
 * @returns {Promise<void>} 使用原 msg_uuid 发送，依赖后端幂等去重。
 */
async function retryPendingMessages() {
  const pendingList = Object.keys(pendingMessages.value)
    .map((msgUuid) => pendingMessages.value[msgUuid])
    .filter((item) => item && !item.confirmed && item.msg_uuid && item.content)

  if (!pendingList.length) {
    return
  }

  await Promise.all(pendingList.map((item) => sendMessageByHttp({
    session_id: sessionId.value,
    content: item.content,
    message_type: item.message_type || 'text',
    extra: item.extra || {},
    msg_uuid: item.msg_uuid
  })))
}

/**
 * 拉取最近历史并与当前列表去重合并。
 *
 * @returns {Promise<void>} 断线重连后用于补偿缺失消息。
 */
async function syncRecentHistory() {
  if (!sessionId.value) {
    return
  }

  const lastId = getLatestMessageId()
  try {
    const res = await getCsHistory({
      session_id: sessionId.value,
      limit: 50,
      after_id: lastId > 0 ? lastId : 0,
      latest: lastId > 0 ? 0 : 1
    })
    const data = res && res.data ? res.data : {}
    const list = Array.isArray(data.list) ? data.list : []
    if (!list.length) {
      return
    }

    list.forEach((item) => {
      if (item.sender_type === 'user' && !String(item.content || '').trim()) {
        return
      }

      const normalized = normalizeMessage({
        id: item.id,
        sender_type: item.sender_type,
        content: item.content || '',
        message_type: item.message_type || 'text',
        create_time: item.create_time,
        extra: item.extra || null,
        msg_uuid: item.msg_uuid || '',
        pending: false
      })
      if (!mergeIncomingMessage(normalized)) {
        messages.value.push(normalized)
      }
    })
    scrollToBottom()
  } catch (error) {
    // 补偿失败不阻塞页面
  }
}

/**
 * 获取当前列表中最大的消息 ID。
 *
 * @returns {number} 最大消息 ID。
 */
function getLatestMessageId() {
  return messages.value.reduce((maxId, item) => {
    const currentId = Number(item.id || 0)
    return currentId > maxId ? currentId : maxId
  }, 0)
}
</script>

<style lang="scss">
page {
  height: 100%;
  background: $surface-page;
}
</style>

<style lang="scss" scoped>
.customer-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: $surface-page;
}

.customer-page__more {
  width: 72rpx;
  height: 72rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.customer-scroll {
  flex: 1;
  min-height: 0;
}

.customer-content {
  padding: 44rpx 32rpx 280rpx;
  box-sizing: border-box;
}

.time-divider {
  display: flex;
  justify-content: center;
  margin-bottom: 36rpx;
}

.time-divider__text {
  padding: 8rpx 20rpx;
  border-radius: 999rpx;
  background: rgba(226, 232, 240, 0.8);
  font-size: 20rpx;
  line-height: 30rpx;
  color: $text-placeholder;
}

.message-block {
  margin-bottom: 28rpx;
}

.message-row {
  display: flex;
  align-items: flex-start;
}

.message-row--right {
  justify-content: flex-end;
}

.message-avatar {
  width: 80rpx;
  height: 80rpx;
  margin-right: 16rpx;
  border-radius: 50%;
  background: $surface-card;
  flex-shrink: 0;
}

.message-bubble {
  max-width: 80%;
  padding: 32rpx;
  box-sizing: border-box;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.message-bubble--left {
  background: $surface-card;
  border-radius: 8rpx 48rpx 48rpx 48rpx;
}

.message-bubble--right {
  background: $brand-primary;
  border-radius: 48rpx 8rpx 48rpx 48rpx;
}

.message-bubble--media {
  padding: 0;
  background: transparent;
  box-shadow: none;
  overflow: hidden;
}

.message-bubble__text {
  font-size: 28rpx;
  line-height: 44rpx;
}

.message-bubble__text--left {
  color: $text-primary;
}

.message-bubble__text--right {
  color: $text-inverse;
}

.message-image {
  display: block;
  width: 240rpx;
  height: 240rpx;
  border-radius: 24rpx;
  background: $surface-card-muted;
}

.message-system {
  display: flex;
  justify-content: center;
}

.message-system__text {
  font-size: 20rpx;
  line-height: 28rpx;
  color: $uni-text-color-disable;
}

/* 对方正在输入 */
.typing-indicator {
  display: flex;
  align-items: center;
  padding: 0 0 20rpx 128rpx;
}

.typing-indicator__dots {
  display: flex;
  align-items: center;
  margin-right: 12rpx;
}

.typing-dot {
  width: 12rpx;
  height: 12rpx;
  margin-right: 6rpx;
  border-radius: 50%;
  background: $text-placeholder;
  animation: typingBounce 1.4s ease-in-out infinite both;
}

.typing-dot:nth-child(1) {
  animation-delay: 0s;
}

.typing-dot:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
  animation-delay: 0.4s;
  margin-right: 0;
}

@keyframes typingBounce {
  0%, 80%, 100% {
    transform: scale(0.6);
    opacity: 0.3;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}

.typing-indicator__text {
  font-size: 20rpx;
  color: $text-placeholder;
}

.customer-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  background: rgba(255, 255, 255, 0.96);
  border-top: 2rpx solid $border-light;
  box-sizing: border-box;
  padding-top: 20rpx;
  padding-bottom: 48rpx;
}

.quick-action-scroll {
  white-space: nowrap;
  padding: 4rpx 0 10rpx;
  box-sizing: border-box;
}

.quick-action-list {
  display: inline-flex;
  padding: 0 24rpx;
}

.quick-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 136rpx;
  height: 56rpx;
  margin-right: 18rpx;
  padding: 0 24rpx;
  border-radius: 999rpx;
  background: $surface-card;
  border: 1rpx solid $border-light;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.04);
  box-sizing: border-box;
}

.quick-action:last-child {
  margin-right: 0;
}

.quick-action--hover {
  background: $surface-card-muted;
}

.quick-action__text {
  font-size: 20rpx;
  line-height: 30rpx;
  color: $text-subtitle;
  font-weight: 700;
}

.customer-input-bar {
  display: flex;
  align-items: center;
  padding: 18rpx 24rpx 0;
}

.customer-input {
  display: flex;
  align-items: center;
  flex: 1;
  min-height: 76rpx;
  padding: 0 26rpx;
  border-radius: 999rpx;
  background: $surface-card-muted;
  box-sizing: border-box;
}

.customer-input__field {
  flex: 1;
  min-width: 0;
  font-size: 24rpx;
  color: $text-primary;
}

.customer-input__emoji {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rpx;
  height: 36rpx;
  margin-left: 16rpx;
}

.customer-input-bar__plus {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  margin-left: 18rpx;
}

.order-popup {
  padding: 30rpx 28rpx 36rpx;
  border-radius: 32rpx 32rpx 0 0;
  background: $surface-card;
  box-sizing: border-box;
}

.order-popup__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
}

.order-popup__title-wrap {
  display: flex;
  flex-direction: column;
}

.order-popup__title {
  font-size: 34rpx;
  line-height: 44rpx;
  color: $text-primary;
  font-weight: 700;
}

.order-popup__subtitle {
  margin-top: 6rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: $text-secondary;
}

.order-popup__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  margin-left: 24rpx;
  border-radius: 50%;
  background: $surface-card-muted;
  flex-shrink: 0;
}

.order-popup__scroll {
  height: 660rpx;
}

.order-popup__list {
  padding-bottom: 12rpx;
}

.order-popup__empty {
  min-height: 240rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.order-popup__empty-text {
  font-size: 24rpx;
  line-height: 36rpx;
  color: $text-placeholder;
}

.order-card {
  padding: 26rpx;
  margin-bottom: 18rpx;
  border-radius: 20rpx;
  background: $surface-card-muted;
  border: 2rpx solid $border-light;
  box-sizing: border-box;
}

.order-card--hover {
  background: $surface-card;
}

.order-card__top,
.order-card__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.order-card__sn {
  max-width: 420rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: $text-primary;
  font-weight: 700;
}

.order-card__status {
  margin-left: 18rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: $brand-primary;
  flex-shrink: 0;
}

.order-card__goods {
  display: block;
  margin-top: 14rpx;
  font-size: 26rpx;
  line-height: 38rpx;
  color: $text-subtitle;
}

.order-card__bottom {
  margin-top: 16rpx;
}

.order-card__time {
  font-size: 22rpx;
  line-height: 32rpx;
  color: $text-placeholder;
}

.order-card__amount {
  margin-left: 18rpx;
  font-size: 26rpx;
  line-height: 36rpx;
  color: $text-primary;
  font-weight: 700;
}

/* #ifdef H5 */
.customer-footer {
  bottom: var(--window-bottom);
  padding-bottom: 24rpx;
}
/* #endif */
</style>
