<template>
  <div class="cs-session-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">客服会话管理</h1>
        <p class="page-curator-subtitle">
          实时查看会话状态、快速处理客户消息，并统一会话工作台操作。
        </p>
      </div>
      <div class="page-curator-actions">
        <el-tag :type="wsConnected ? 'success' : 'info'">
          {{ wsConnected ? '实时已连接' : '实时未连接' }}
        </el-tag>
        <el-button
          type="primary"
          class="btn-curator-primary"
          icon="el-icon-refresh"
          @click="refreshSessions(false)"
        >
          刷新会话
        </el-button>
      </div>
    </header>

    <div class="session-work-surface">
      <div class="cs-workbench">
        <div class="session-pane">
          <div class="session-header">
            <div class="title">客服工作台</div>
            <el-input
              v-model="keyword"
              clearable
              placeholder="搜索会话ID/用户ID"
              @input="onKeywordChange"
            >
              <template #prefix>
                <LegacyIcon name="el-icon-search" class="el-input__icon" />
              </template>
            </el-input>
          </div>

          <el-tabs v-model="activeTab" class="session-tabs" @tab-click="onTabChange">
            <el-tab-pane name="all">
              <template #label>
                <span>全部（{{ sessions.length }}）</span>
              </template>
            </el-tab-pane>
            <el-tab-pane name="0">
              <template #label>
                <span>待接入（{{ tabCount(0) }}）</span>
              </template>
            </el-tab-pane>
            <el-tab-pane name="1">
              <template #label>
                <span>服务中（{{ tabCount(1) }}）</span>
              </template>
            </el-tab-pane>
            <el-tab-pane name="2">
              <template #label>
                <span>已关闭（{{ tabCount(2) }}）</span>
              </template>
            </el-tab-pane>
          </el-tabs>

          <div v-loading="sessionLoading" class="session-list">
            <div
              v-for="item in filteredSessions"
              :key="item.session_id"
              :class="[
                'session-item',
                {
                  active: activeSession && activeSession.session_id === item.session_id,
                  unread: Number(item.unread_count || 0) > 0
                }
              ]"
              @click="selectSession(item)"
            >
              <div class="session-row">
                <span class="user-text">用户#{{ item.user_id || 0 }}</span>
                <span class="time-text">{{ formatTime(item.last_message_time || item.create_time) }}</span>
              </div>
              <div class="session-row second">
                <span class="msg-text">{{ item.last_message || '暂无消息' }}</span>
                <el-badge v-if="Number(item.unread_count || 0) > 0" :value="Number(item.unread_count)" :max="99" class="unread-badge" />
              </div>
            </div>
            <el-empty v-if="!filteredSessions.length && !sessionLoading" :image-size="64" description="暂无会话" />
          </div>
        </div>

        <div class="chat-pane">
          <div class="chat-header">
            <div class="header-main">
              <div class="header-title">
                {{ activeSession ? `会话 ${activeSession.session_id}` : '请选择会话' }}
              </div>
              <div v-if="activeSession" class="header-sub">
                用户#{{ activeSession.user_id || 0 }} · {{ statusText(activeSession.status) }}
              </div>
            </div>
            <div v-if="activeSession" class="header-actions">
              <el-button
                v-if="Number(activeSession.status) === 0"
                type="primary"
                size="mini"
                @click="handleAccept(activeSession)"
              >接入</el-button>
              <el-button
                v-if="Number(activeSession.status) !== 2"
                size="mini"
                @click="handleTransfer(activeSession)"
              >转接</el-button>
              <el-button
                v-if="Number(activeSession.status) !== 2"
                type="danger"
                plain
                size="mini"
                @click="handleClose(activeSession)"
              >结束会话</el-button>
            </div>
          </div>

          <div ref="messageScrollRef" v-loading="messageLoading" class="chat-body">
            <div v-if="!messages.length" class="empty-message">暂无消息</div>
            <div
              v-for="item in messages"
              :key="item._key"
              :class="['msg-line', bubbleRole(item)]"
            >
              <div v-if="bubbleRole(item) === 'system'" class="system-tip">
                {{ item.content }}
              </div>
              <template v-else>
                <div class="meta">{{ senderText(item.sender_type, item.sender_id) }} · {{ item.send_time }}</div>
                <div class="bubble">{{ item.content }}</div>
              </template>
            </div>
          </div>

          <div v-if="activeSession && Number(activeSession.status) !== 2" class="chat-footer">
            <div class="chat-tools">
              <div class="quick-replies">
                <el-tag
                  v-for="text in quickReplies"
                  :key="text"
                  size="mini"
                  type="info"
                  class="quick-item"
                  @click="appendQuickReply(text)"
                >{{ text }}</el-tag>
              </div>
              <el-popover
                v-model="scriptPopoverVisible"
                placement="top-end"
                width="560"
                trigger="click"
                popper-class="cs-script-popover"
                :append-to-body="false"
                @show="handleScriptPopoverShow"
              >
                <div class="script-panel">
                  <div class="script-panel__header">
                    <span class="script-panel__title">快捷话术</span>
                    <el-input
                      v-model="scriptKeyword"
                      size="mini"
                      clearable
                      class="script-search"
                      placeholder="搜索话术标题"
                      @input="onScriptKeywordInput"
                    >
                      <template #prefix>
                        <LegacyIcon name="el-icon-search" class="el-input__icon" />
                      </template>
                    </el-input>
                  </div>
                  <div class="script-panel__body">
                    <div v-loading="scriptCategoryLoading" class="script-categories">
                      <button
                        type="button"
                        :class="['script-category', { active: Number(activeScriptCategoryId) === 0 }]"
                        @click="handleScriptCategorySelect(0)"
                      >
                        全部话术
                      </button>
                      <button
                        v-for="item in scriptCategories"
                        :key="item.id"
                        type="button"
                        :class="['script-category', { active: Number(activeScriptCategoryId) === Number(item.id) }]"
                        @click="handleScriptCategorySelect(item.id)"
                      >
                        {{ item.name }}
                      </button>
                    </div>
                    <div v-loading="scriptListLoading" class="script-list">
                      <button
                        v-for="item in scriptList"
                        :key="item.id"
                        type="button"
                        class="script-item"
                        @click="handleScriptSelect(item)"
                      >
                        <span class="script-item__title">{{ item.title || '未命名话术' }}</span>
                        <span class="script-item__content">{{ item.content || '暂无内容' }}</span>
                      </button>
                      <el-empty
                        v-if="!scriptListLoading && scriptList.length === 0"
                        :image-size="56"
                        description="暂无话术"
                      />
                    </div>
                  </div>
                </div>
                <template #reference>
                  <el-button size="mini" icon="el-icon-chat-line-square" class="script-trigger">
                    话术
                  </el-button>
                </template>
              </el-popover>
            </div>
            <el-input
              ref="messageInputRef"
              v-model="messageForm.content"
              type="textarea"
              :rows="3"
              resize="none"
              placeholder="输入消息，Enter 发送，Shift+Enter 换行"
              @keydown="onInputKeydown"
            />
            <div class="send-wrap">
              <el-button
                :loading="sending"
                :disabled="!wsConnected"
                type="primary"
                @click="handleSend"
              >发送</el-button>
            </div>
          </div>
        </div>

        <div class="detail-pane">
          <div class="detail-title">会话信息</div>
          <div v-if="activeSession" class="detail-content">
            <div class="detail-item"><span>会话ID</span><span>{{ activeSession.session_id }}</span></div>
            <div class="detail-item"><span>用户ID</span><span>{{ activeSession.user_id || '-' }}</span></div>
            <div class="detail-item"><span>客服ID</span><span>{{ activeSession.staff_id || '-' }}</span></div>
            <div class="detail-item"><span>状态</span><span>{{ statusText(activeSession.status) }}</span></div>
            <div class="detail-item"><span>服务方式</span><span>{{ Number(activeSession.service_type) === 1 ? 'AI' : '人工' }}</span></div>
            <div class="detail-item"><span>最后消息</span><span class="last-message">{{ activeSession.last_message || '-' }}</span></div>
            <div class="detail-item"><span>创建时间</span><span>{{ activeSession.create_time || '-' }}</span></div>
          </div>
          <el-empty v-else :image-size="64" description="未选择会话" />
        </div>
      </div>
    </div>

    <el-dialog
      v-model="staffDialogVisible"
      :title="staffActionType === 'transfer' ? '转接会话' : '接入会话'"
      width="420px"
      append-to-body
      :close-on-click-modal="!staffSubmitting"
      @closed="resetStaffDialog"
    >
      <el-form class="staff-action-form" label-width="80px">
        <el-form-item label="目标客服">
          <el-select
            v-model="selectedStaffId"
            :loading="staffLoading"
            filterable
            clearable
            placeholder="请选择客服"
            class="staff-select"
          >
            <el-option
              v-for="item in staffOptions"
              :key="item.id"
              :label="staffOptionLabel(item)"
              :value="Number(item.id)"
              :disabled="isCurrentSessionStaff(item)"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button :disabled="staffSubmitting" @click="staffDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="staffSubmitting" @click="submitStaffAction">确定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeMount, onBeforeUnmount, reactive, ref } from 'vue'
import {
  acceptCsSession,
  closeCsSession,
  getCsOnlineStaff,
  getCsSessionList,
  getCsSessionMessages,
  getCsScriptCategoryList,
  getCsScriptList,
  getCsWsToken,
  markCsSessionRead,
  transferCsSession
} from '@/api/cs'
import WebSocketClient from '@/utils/websocket'

defineOptions({
  name: 'CsSessionWorkbench'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const sessionLoading = ref(false)
const messageLoading = ref(false)
const sending = ref(false)
const activeTab = ref('all')
const keyword = ref('')
const keywordTimer = ref(null)
const sessions = ref([])
const activeSession = ref(null)
const messages = ref([])
const wsClient = ref(null)
const wsConnected = ref(false)
const wsConnecting = ref(false)
const pendingUnreadSessions = reactive({})
const queryLimit = ref(100)
const sessionRequestId = ref(0)
const messageRequestId = ref(0)
const scriptPopoverVisible = ref(false)
const scriptCategoryLoading = ref(false)
const scriptListLoading = ref(false)
const scriptCategoryLoaded = ref(false)
const scriptCategories = ref([])
const activeScriptCategoryId = ref(0)
const scriptKeyword = ref('')
const scriptKeywordTimer = ref(null)
const scriptList = ref([])
const staffDialogVisible = ref(false)
const staffActionType = ref('')
const staffTargetSession = ref(null)
const staffLoading = ref(false)
const staffSubmitting = ref(false)
const staffOptions = ref([])
const selectedStaffId = ref('')
const messageScrollRef = ref(null)
const messageInputRef = ref(null)
const messageForm = reactive({ content: '' })
const quickReplies = ['您好，我是客服，请问有什么可以帮您？', '请稍等，我正在为您核实。', '问题已记录，我们会尽快处理。']

function setWsClient(client) {
  wsClient.value = client
}

function setWsConnecting(value) {
  wsConnecting.value = value
}

function setWsConnected(value) {
  wsConnected.value = value
}

const filteredSessions = computed(() => {
  const searchText = String(keyword.value || '').trim().toLowerCase()
  const list = activeTab.value === 'all'
    ? sessions.value
    : sessions.value.filter(item => Number(item.status) === Number(activeTab.value))

  if (!searchText) {
    return list
  }

  return list.filter(item => {
    return String(item.session_id || '').toLowerCase().includes(searchText) ||
      String(item.user_id || '').toLowerCase().includes(searchText) ||
      String(item.last_message || '').toLowerCase().includes(searchText)
  })
})

async function initWorkbench() {
  await refreshSessions(false)
  await connectSocket()
}

async function refreshSessions(silent = false) {
  const requestId = sessionRequestId.value + 1
  sessionRequestId.value = requestId
  if (!silent) {
    sessionLoading.value = true
  }

  try {
    const payload = await fetchSessionPayload()
    if (requestId !== sessionRequestId.value) {
      return
    }
    const nextList = payload.list || []
    sessions.value = Array.isArray(nextList) ? nextList : []
    syncActiveSession()
  } catch (error) {
    console.error(error)
    if (!silent) {
      message.error('会话加载失败，请稍后重试')
    }
  } finally {
    if (!silent && requestId === sessionRequestId.value) {
      sessionLoading.value = false
    }
  }
}

async function fetchSessionPayload() {
  const response = await getCsSessionList({
    page: 1,
    limit: queryLimit.value,
    keyword: keyword.value
  })

  const payload = response.data || response || {}
  return payload.data || payload || {}
}

function syncActiveSession() {
  if (!activeSession.value) {
    if (filteredSessions.value.length > 0) {
      selectSession(filteredSessions.value[0])
      return
    }
    if (sessions.value.length > 0) {
      activeTab.value = String(sessions.value[0].status)
      selectSession(sessions.value[0])
    }
    return
  }

  const latest = sessions.value.find(item => item.session_id === activeSession.value.session_id)
  if (!latest) {
    activeSession.value = null
    messages.value = []
    if (filteredSessions.value.length > 0) {
      selectSession(filteredSessions.value[0])
      return
    }
    if (sessions.value.length > 0) {
      activeTab.value = String(sessions.value[0].status)
      selectSession(sessions.value[0])
    }
    return
  }

  activeSession.value = latest
}

function onTabChange() {
  syncActiveSession()
}

function onKeywordChange() {
  if (keywordTimer.value) {
    clearTimeout(keywordTimer.value)
  }
  keywordTimer.value = setTimeout(() => {
    syncActiveSession()
  }, 400)
}

async function selectSession(session) {
  activeSession.value = session
  await refreshMessages(false)
  markCurrentSessionRead()
}

async function refreshMessages(silent = true) {
  if (!activeSession.value || !activeSession.value.session_id) {
    messages.value = []
    return
  }

  const requestId = messageRequestId.value + 1
  messageRequestId.value = requestId
  if (!silent) {
    messageLoading.value = true
  }

  try {
    const response = await getCsSessionMessages({
      session_id: activeSession.value.session_id,
      page: 1,
      limit: 50
    })
    if (requestId !== messageRequestId.value) {
      return
    }
    const payload = response.data || response || {}
    const data = payload.data || payload || {}
    messages.value = Array.isArray(data.list)
      ? data.list.map(item => normalizeMessage(item))
      : []
    nextTick(scrollToBottom)
  } catch (error) {
    console.error(error)
    if (!silent) {
      message.error('会话消息加载失败，请稍后重试')
    }
  } finally {
    if (!silent && requestId === messageRequestId.value) {
      messageLoading.value = false
    }
  }
}

async function markCurrentSessionRead() {
  if (!activeSession.value || !activeSession.value.session_id) {
    return
  }

  try {
    await markCsSessionRead({
      session_id: activeSession.value.session_id,
      sender_type: 'user'
    })
    delete pendingUnreadSessions[activeSession.value.session_id]
    upsertSession({
      ...activeSession.value,
      unread_count: 0
    })
  } catch (error) {
    // 忽略已读失败，不影响主流程
  }
}

async function handleSend() {
  if (!activeSession.value || !activeSession.value.session_id) {
    message.warning('请先选择会话')
    return
  }

  if (!wsConnected.value || !wsClient.value) {
    message.warning('连接未建立，请稍后重试或刷新页面')
    return
  }

  const content = String(messageForm.content || '').trim()
  if (!content) {
    message.warning('消息内容不能为空')
    return
  }

  sending.value = true
  try {
    const msgUuid = generateMsgUuid()
    const sendTime = formatNow()
    messages.value.push(normalizeMessage({
      id: 0,
      session_id: activeSession.value.session_id,
      sender_type: 'staff',
      sender_id: activeSession.value.staff_id || 0,
      content,
      message_type: 'text',
      send_time: sendTime,
      msg_uuid: msgUuid,
      pending: true
    }))
    upsertSession({
      ...activeSession.value,
      last_message: content,
      last_message_time: sendTime,
      unread_count: 0
    })
    messageForm.content = ''
    nextTick(scrollToBottom)
    wsClient.value.send({
      event: 'cs_message',
      data: {
        session_id: activeSession.value.session_id,
        content,
        msg_type: 'text',
        msg_uuid: msgUuid
      }
    })
  } finally {
    sending.value = false
  }
}

function onInputKeydown(event) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    handleSend()
  }
}

function appendQuickReply(text) {
  messageForm.content = messageForm.content ? `${messageForm.content}${text}` : text
}

function unwrapResponse(response) {
  const payload = response && response.data ? response.data : (response || {})
  return payload.data || payload
}

async function handleScriptPopoverShow() {
  if (!scriptCategoryLoaded.value) {
    await loadScriptCategories()
  }
  loadScriptList()
}

async function loadScriptCategories() {
  scriptCategoryLoading.value = true
  try {
    const response = await getCsScriptCategoryList({ status: 1 })
    const data = unwrapResponse(response)
    const list = Array.isArray(data) ? data : (data.list || [])
    scriptCategories.value = list.filter(item => Number(item.status) === 1)
    scriptCategoryLoaded.value = true
    if (Number(activeScriptCategoryId.value) > 0) {
      const existed = scriptCategories.value.some(item => Number(item.id) === Number(activeScriptCategoryId.value))
      if (!existed) {
        activeScriptCategoryId.value = 0
      }
    }
  } catch (error) {
    console.error(error)
    message.error('话术分类加载失败')
  } finally {
    scriptCategoryLoading.value = false
  }
}

async function loadScriptList() {
  scriptListLoading.value = true
  try {
    const response = await getCsScriptList({
      category_id: Number(activeScriptCategoryId.value || 0),
      keyword: scriptKeyword.value,
      page: 1,
      limit: 100
    })
    const data = unwrapResponse(response)
    const list = Array.isArray(data) ? data : (data.list || [])
    scriptList.value = list.filter(item => Number(item.status) === 1)
  } catch (error) {
    console.error(error)
    message.error('话术列表加载失败')
  } finally {
    scriptListLoading.value = false
  }
}

function handleScriptCategorySelect(categoryId) {
  activeScriptCategoryId.value = Number(categoryId || 0)
  loadScriptList()
}

function onScriptKeywordInput() {
  if (scriptKeywordTimer.value) {
    clearTimeout(scriptKeywordTimer.value)
  }
  scriptKeywordTimer.value = setTimeout(() => {
    loadScriptList()
  }, 300)
}

function handleScriptSelect(row) {
  const content = String((row && row.content) || '').trim()
  if (!content) {
    message.warning('该话术暂无内容')
    return
  }
  messageForm.content = content
  scriptPopoverVisible.value = false
  nextTick(() => {
    if (messageInputRef.value && messageInputRef.value.focus) {
      messageInputRef.value.focus()
    }
  })
}

async function handleAccept(row) {
  openStaffDialog('accept', row)
}

async function handleTransfer(row) {
  openStaffDialog('transfer', row)
}

async function openStaffDialog(type, row) {
  staffActionType.value = type
  staffTargetSession.value = row
  selectedStaffId.value = ''
  staffDialogVisible.value = true
  await loadStaffOptions()
}

async function loadStaffOptions() {
  staffLoading.value = true
  try {
    const response = await getCsOnlineStaff()
    const data = unwrapResponse(response)
    const list = Array.isArray(data) ? data : (data.list || [])
    staffOptions.value = list.filter(item => Number(item.id || 0) > 0)
    if (staffOptions.value.length === 0) {
      message.warning('暂无可选在线客服')
    }
  } catch (error) {
    console.error(error)
    message.error('客服列表加载失败')
  } finally {
    staffLoading.value = false
  }
}

async function submitStaffAction() {
  if (!staffTargetSession.value || !staffTargetSession.value.session_id) {
    message.warning('请选择会话')
    return
  }
  if (!selectedStaffId.value) {
    message.warning('请选择客服')
    return
  }

  const staff = getSelectedStaff()
  const staffName = staff ? staffOptionLabel(staff) : `客服#${selectedStaffId.value}`
  if (staffActionType.value === 'transfer') {
    try {
      await confirm(`确定将该会话转接给 ${staffName} 吗？`, '转接确认', { type: 'warning' })
    } catch (error) {
      return
    }
  }

  staffSubmitting.value = true
  const row = staffTargetSession.value
  try {
    if (staffActionType.value === 'transfer') {
      await transferCsSession({ session_id: row.session_id, to_staff_id: Number(selectedStaffId.value), remark: '' })
      message.success('转接成功')
    } else {
      await acceptCsSession({ session_id: row.session_id, staff_id: Number(selectedStaffId.value) })
      message.success('接入成功')
    }
    staffDialogVisible.value = false
    await refreshSessions()
    if (activeSession.value && activeSession.value.session_id === row.session_id) {
      await refreshMessages(false)
    }
  } finally {
    staffSubmitting.value = false
  }
}

function getSelectedStaff() {
  return staffOptions.value.find(item => Number(item.id) === Number(selectedStaffId.value))
}

function staffOptionLabel(item) {
  const name = item.nickname || item.real_name || item.name || item.staff_name || `客服#${item.id || 0}`
  const staffNo = item.staff_no ? ` · ${item.staff_no}` : ''
  const sessionText = item.max_sessions ? ` · ${Number(item.current_sessions || 0)}/${item.max_sessions}` : ''
  return `${name}${staffNo}${sessionText}`
}

function isCurrentSessionStaff(item) {
  return staffActionType.value === 'transfer' &&
    staffTargetSession.value &&
    Number(item.id) === Number(staffTargetSession.value.staff_id || 0)
}

function resetStaffDialog() {
  staffActionType.value = ''
  staffTargetSession.value = null
  selectedStaffId.value = ''
  staffSubmitting.value = false
}

async function handleClose(row) {
  await confirm('确定结束该会话吗？', '提示', { type: 'warning' })
  await closeCsSession({ session_id: row.session_id, remark: '' })
  message.success('会话已结束')
  await refreshSessions()
  if (activeSession.value && activeSession.value.session_id === row.session_id) {
    await refreshMessages(false)
  }
}

function tabCount(status) {
  return sessions.value.filter(item => Number(item.status) === Number(status)).length
}

function statusText(status) {
  const map = {
    0: '待接入',
    1: '服务中',
    2: '已关闭'
  }
  return map[Number(status)] || '未知'
}

function senderText(type, id) {
  const senderType = String(type || '')
  if (senderType === 'user') {
    return `用户#${id || 0}`
  }
  if (senderType === 'staff') {
    return `客服#${id || 0}`
  }
  if (senderType === 'ai') {
    return 'AI客服'
  }
  return '系统'
}

function bubbleRole(item) {
  const senderType = String(item.sender_type || '')
  if (senderType === 'staff' || senderType === 'ai') {
    return 'staff'
  }
  if (senderType === 'user') {
    return 'user'
  }
  return 'system'
}

function formatTime(timeText) {
  if (!timeText) {
    return '--'
  }
  return String(timeText).slice(11, 16)
}

function scrollToBottom() {
  const wrap = messageScrollRef.value
  if (!wrap) {
    return
  }
  wrap.scrollTop = wrap.scrollHeight
}

async function connectSocket() {
  if (wsConnecting.value || wsClient.value) {
    return
  }
  setWsConnecting(true)
  try {
    const response = await getCsWsToken()
    const data = response.data || {}
    const token = data.token || ''
    const wsUrl = data.ws_url || ''
    if (!token || !wsUrl) {
      message.warning('客服实时连接配置缺失')
      return
    }

    const client = new WebSocketClient({
      url: `${wsUrl}?token=${encodeURIComponent(token)}`,
      heartbeatInterval: 30000,
      reconnectInterval: 5000,
      maxReconnectAttempts: 10,
      onOpen: () => {
        setWsConnected(true)
      },
      onClose: () => {
        setWsConnected(false)
      },
      onError: () => {
        setWsConnected(false)
      },
      onMessage: handleSocketMessage
    })
    setWsClient(client)
    client.connect()
  } catch (error) {
    console.error(error)
    message.warning('客服实时连接失败，请刷新页面重试')
  } finally {
    setWsConnecting(false)
  }
}

function disconnectSocket() {
  if (wsClient.value) {
    wsClient.value.destroy()
    setWsClient(null)
  }
  setWsConnected(false)
}

function handleSocketMessage(messageData) {
  const event = messageData && messageData.event
  const data = (messageData && messageData.data) || {}
  if (messageData && messageData.type === 'pong') {
    return
  }
  if (event === 'cs_message') {
    handleSocketCsMessage(data)
    return
  }
  if (event === 'cs_session_update' || event === 'cs_new_session') {
    handleSocketSessionUpdate(data)
    return
  }
  if (event === 'cs_transfer') {
    handleSocketTransfer(data)
    return
  }
  if (event === 'cs_close') {
    handleSocketClose(data)
  }
}

function handleSocketCsMessage(messageData) {
  const normalized = normalizeMessage(messageData)
  if (isActiveSessionMessage(normalized)) {
    mergeOrAppendMessage(normalized)
    if (normalized.sender_type === 'user') {
      markCurrentSessionRead()
      upsertSession({
        ...activeSession.value,
        unread_count: 0,
        last_message: normalized.content,
        last_message_time: normalized.send_time
      })
    }
    nextTick(scrollToBottom)
    return
  }
  if (normalized.sender_type !== 'user') {
    upsertSession({
      session_id: normalized.session_id,
      last_message: normalized.content,
      last_message_time: normalized.send_time
    })
    return
  }
  pendingUnreadSessions[normalized.session_id] = true
  upsertSession({
    session_id: normalized.session_id,
    last_message: normalized.content,
    last_message_time: normalized.send_time,
    unread_count: Math.max(getSessionUnreadCount(normalized.session_id), 1)
  })
}

function handleSocketSessionUpdate(session) {
  if (!session || !session.session_id) {
    return
  }
  if (activeSession.value && activeSession.value.session_id === session.session_id) {
    delete pendingUnreadSessions[session.session_id]
    upsertSession({
      ...session,
      unread_count: 0
    })
    return
  }
  upsertSession({
    ...session,
    keep_local_unread: Boolean(pendingUnreadSessions[session.session_id])
  })
}

function handleSocketTransfer(data) {
  if (!data || !data.session_id) {
    return
  }
  upsertSession({
    session_id: data.session_id,
    staff_id: data.to_staff_id
  })
}

function handleSocketClose(data) {
  if (!data || !data.session_id) {
    return
  }
  upsertSession({
    session_id: data.session_id,
    status: 2
  })
}

function upsertSession(partial) {
  if (!partial || !partial.session_id) {
    return
  }
  const index = sessions.value.findIndex(item => item.session_id === partial.session_id)
  if (index >= 0) {
    const unreadIncrement = Number(partial.unread_increment || 0)
    const keepLocalUnread = Boolean(partial.keep_local_unread)
    const currentUnread = Number(sessions.value[index].unread_count || 0)
    const nextUnread = partial.unread_count !== undefined ? Number(partial.unread_count || 0) : currentUnread
    const unreadCount = keepLocalUnread
      ? Math.max(currentUnread, nextUnread)
      : nextUnread + unreadIncrement
    const merged = {
      ...sessions.value[index],
      ...partial,
      unread_count: unreadCount
    }
    delete merged.unread_increment
    delete merged.keep_local_unread
    sessions.value.splice(index, 1)
    sessions.value.unshift(merged)
    if (activeSession.value && activeSession.value.session_id === merged.session_id) {
      activeSession.value = merged
    }
    return
  }
  if (partial.user_id !== undefined || partial.status !== undefined) {
    const next = { ...partial }
    delete next.unread_increment
    delete next.keep_local_unread
    sessions.value.unshift(next)
  }
}

function isActiveSessionMessage(messageData) {
  return activeSession.value && messageData.session_id === activeSession.value.session_id
}

function getSessionUnreadCount(sessionId) {
  const session = sessions.value.find(item => item.session_id === sessionId)
  return session ? Number(session.unread_count || 0) : 0
}

function normalizeMessage(messageData) {
  return {
    ...messageData,
    _key: messageData._key || `cs-admin-msg-${messageData.id || 0}-${messageData.msg_uuid || Date.now()}-${Math.random()}`
  }
}

function mergeOrAppendMessage(messageData) {
  const msgUuid = messageData.msg_uuid || ''
  const index = msgUuid
    ? messages.value.findIndex(item => item.msg_uuid === msgUuid)
    : messages.value.findIndex(item => item.id && item.id === messageData.id)
  if (index >= 0) {
    messages.value.splice(index, 1, {
      ...messages.value[index],
      ...messageData,
      _key: messages.value[index]._key,
      pending: false
    })
    return
  }
  messages.value.push(messageData)
}

function generateMsgUuid() {
  const timestamp = Date.now().toString(36)
  const random = Math.random().toString(36).slice(2, 10)
  return `${timestamp}-${random}`
}

function formatNow() {
  const date = new Date()
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

onBeforeMount(() => {
  initWorkbench()
})

onBeforeUnmount(() => {
  disconnectSocket()
  if (keywordTimer.value) {
    clearTimeout(keywordTimer.value)
    keywordTimer.value = null
  }
  if (scriptKeywordTimer.value) {
    clearTimeout(scriptKeywordTimer.value)
    scriptKeywordTimer.value = null
  }
})
</script>

<style lang="scss" scoped>
.cs-session-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 560px;
}

.page-curator-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.session-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

.cs-workbench {
  height: calc(100vh - 244px);
  min-height: 620px;
  display: flex;
  border: 1px solid var(--ds-outline);
  border-radius: 10px;
  overflow: hidden;
  background: var(--ds-surface-card);

  .session-pane {
    width: 300px;
    border-right: 1px solid var(--ds-outline);
    display: flex;
    flex-direction: column;

    .session-header {
      padding: 14px;
      border-bottom: 1px solid var(--dashboard-border-soft);

      .title {
        font-size: 16px;
        font-weight: 600;
        color: var(--ds-on-surface);
        margin-bottom: 10px;
      }
    }

    .session-tabs {
      padding: 0 8px;
      border-bottom: 1px solid var(--dashboard-border-soft);
    }

    .session-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;

      .session-item {
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.2s ease;

        &:hover {
          background: var(--ds-surface-low);
        }

        &.active {
          border-color: var(--ds-primary-container);
          background: var(--dashboard-accent-outline);
        }

        &.unread {
          .user-text,
          .msg-text {
            color: var(--ds-on-surface);
            font-weight: 600;
          }
        }

        .session-row {
          display: flex;
          align-items: center;
          justify-content: space-between;

          &.second {
            margin-top: 6px;
          }
        }

        .user-text {
          color: var(--ds-on-surface);
          font-size: 13px;
          font-weight: 500;
        }

        .time-text {
          color: var(--dashboard-text-muted);
          font-size: 12px;
        }

        .msg-text {
          color: var(--ds-on-surface-variant);
          font-size: 12px;
          width: 208px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
      }
    }
  }

  .chat-pane {
    flex: 1;
    display: flex;
    flex-direction: column;

    .chat-header {
      min-height: 70px;
      border-bottom: 1px solid var(--dashboard-border-soft);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;

      .header-title {
        font-size: 15px;
        color: var(--ds-on-surface);
        font-weight: 600;
      }

      .header-sub {
        color: var(--dashboard-text-muted);
        margin-top: 4px;
        font-size: 12px;
      }
    }

    .chat-body {
      flex: 1;
      overflow-y: auto;
      padding: 14px;
      background: var(--ds-surface-low);

      .empty-message {
        color: var(--dashboard-text-muted);
        text-align: center;
        margin-top: 40px;
      }

      .msg-line {
        margin-bottom: 12px;

        .meta {
          color: var(--dashboard-text-muted);
          font-size: 12px;
          margin-bottom: 4px;
        }

        .bubble {
          max-width: 72%;
          display: inline-block;
          line-height: 1.5;
          white-space: pre-wrap;
          border-radius: 8px;
          padding: 10px 12px;
          font-size: 13px;
        }

        &.user {
          .bubble {
            background: var(--ds-surface-card);
            color: var(--ds-on-surface);
          }
        }

        &.staff {
          text-align: right;

          .meta {
            text-align: right;
          }

          .bubble {
            background: var(--dashboard-accent-outline);
            color: var(--ds-on-surface);
          }
        }

        &.system {
          text-align: center;

          .system-tip {
            display: inline-block;
            background: var(--ds-surface-card);
            color: var(--dashboard-text-muted);
            font-size: 12px;
            border-radius: 999px;
            padding: 4px 10px;
          }
        }
      }
    }

    .chat-footer {
      border-top: 1px solid var(--dashboard-border-soft);
      padding: 10px 14px;

      .chat-tools {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
      }

      .quick-replies {
        flex: 1;
        min-width: 0;

        .quick-item {
          margin-right: 8px;
          margin-bottom: 6px;
          cursor: pointer;
        }
      }

      .script-trigger {
        flex-shrink: 0;
      }

      .script-panel {
        width: 100%;
      }

      .script-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
      }

      .script-panel__title {
        flex-shrink: 0;
        color: var(--ds-on-surface);
        font-size: 14px;
        font-weight: 600;
      }

      .script-search {
        width: 220px;
      }

      .script-panel__body {
        display: flex;
        min-height: 300px;
        max-height: 360px;
        border: 1px solid var(--dashboard-border-soft);
        border-radius: 8px;
        overflow: hidden;
        background: var(--ds-surface-card);
      }

      .script-categories {
        width: 142px;
        flex-shrink: 0;
        padding: 8px;
        border-right: 1px solid var(--dashboard-border-soft);
        overflow-y: auto;
        background: var(--ds-surface-low);
      }

      .script-category {
        display: block;
        width: 100%;
        height: 32px;
        padding: 0 10px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: var(--ds-on-surface-variant);
        cursor: pointer;
        font-size: 13px;
        line-height: 32px;
        overflow: hidden;
        text-align: left;
        text-overflow: ellipsis;
        white-space: nowrap;

        &:hover,
        &.active {
          background: var(--ds-surface-card);
          color: var(--ds-primary);
        }
      }

      .script-list {
        flex: 1;
        min-width: 0;
        padding: 8px;
        overflow-y: auto;
      }

      .script-item {
        display: block;
        width: 100%;
        padding: 10px 12px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        cursor: pointer;
        text-align: left;

        &:hover {
          background: var(--ds-surface-low);
        }
      }

      .script-item__title {
        display: block;
        margin-bottom: 4px;
        color: var(--ds-on-surface);
        font-size: 13px;
        font-weight: 600;
        line-height: 1.4;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .script-item__content {
        display: -webkit-box;
        color: var(--ds-on-surface-variant);
        font-size: 12px;
        line-height: 1.5;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
      }

      .send-wrap {
        margin-top: 8px;
        text-align: right;
      }
    }
  }

  .detail-pane {
    width: 290px;
    border-left: 1px solid var(--ds-outline);
    padding: 14px;
    background: var(--ds-surface-card);

    .detail-title {
      font-size: 15px;
      color: var(--ds-on-surface);
      font-weight: 600;
      margin-bottom: 10px;
    }

    .detail-content {
      .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        color: var(--ds-on-surface-variant);
        font-size: 13px;
        padding: 9px 0;
        border-bottom: 1px dashed var(--dashboard-border-soft);

        .last-message {
          width: 150px;
          text-align: right;
          word-break: break-all;
        }
      }
    }
  }
}
</style>
