<template>
  <div class="cs-session-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">客服会话管理</h1>
        <p class="page-curator-subtitle">
          实时查看会话状态、快速处理客户消息，并统一会话工作台操作。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        :icon="Refresh"
        @click="refreshSessions(false)"
      >
        刷新会话
      </el-button>
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
              <template #prefix><LegacyIcon name="el-icon-search" class="el-input__icon" /></template>
            </el-input>
          </div>

          <el-tabs v-model="activeTab" class="session-tabs" @tab-click="onTabChange">
            <el-tab-pane name="all">
              <template #label><span>全部（{{ sessions.length }}）</span></template>
            </el-tab-pane>
            <el-tab-pane name="0">
              <template #label><span>待接入（{{ tabCount(0) }}）</span></template>
            </el-tab-pane>
            <el-tab-pane name="1">
              <template #label><span>服务中（{{ tabCount(1) }}）</span></template>
            </el-tab-pane>
            <el-tab-pane name="2">
              <template #label><span>已关闭（{{ tabCount(2) }}）</span></template>
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

          <div ref="messageScroll" v-loading="messageLoading" class="chat-body">
            <div v-if="!messages.length" class="empty-message">暂无消息</div>
            <div
              v-for="item in messages"
              :key="item.id"
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
                      <template #prefix><LegacyIcon name="el-icon-search" class="el-input__icon" /></template>
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
                  <el-button size="mini" :icon="ChatLineSquare" class="script-trigger">
                    话术
                  </el-button>
                </template>
              </el-popover>
            </div>
            <el-input
              ref="messageInput"
              v-model="messageForm.content"
              type="textarea"
              :rows="3"
              resize="none"
              placeholder="输入消息，Enter 发送，Shift+Enter 换行"
              @keydown="onInputKeydown"
            />
            <div class="send-wrap">
              <el-button :loading="sending" type="primary" @click="handleSend">发送</el-button>
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
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ChatLineSquare, Refresh } from '@element-plus/icons-vue'
import {
  acceptCsSession,
  closeCsSession,
  createCsMessage,
  getCsOnlineStaff,
  getCsSessionList,
  getCsSessionMessages,
  getCsScriptCategoryList,
  getCsScriptList,
  markCsSessionRead,
  transferCsSession
} from '@/api/cs'

defineOptions({
  name: 'MerchantCsSessionWorkbench'
})

const sessionLoading = ref(false)
const messageLoading = ref(false)
const sending = ref(false)
const activeTab = ref('all')
const keyword = ref('')
let keywordTimer = null
const sessions = ref([])
const activeSession = ref(null)
const messages = ref([])
let pollTimer = null
let pollingBusy = false
const pollInterval = 10000
const queryLimit = ref(100)
let sessionRequestId = 0
let messageRequestId = 0
const scriptPopoverVisible = ref(false)
const scriptCategoryLoading = ref(false)
const scriptListLoading = ref(false)
const scriptCategoryLoaded = ref(false)
const scriptCategories = ref([])
const activeScriptCategoryId = ref(0)
const scriptKeyword = ref('')
let scriptKeywordTimer = null
const scriptList = ref([])
const staffDialogVisible = ref(false)
const staffActionType = ref('')
const staffTargetSession = ref(null)
const staffLoading = ref(false)
const staffSubmitting = ref(false)
const staffOptions = ref([])
const selectedStaffId = ref('')
const messageForm = ref({
  content: ''
})
const quickReplies = ['您好，我是客服，请问有什么可以帮您？', '请稍等，我正在为您核实。', '问题已记录，我们会尽快处理。']
const messageScroll = ref(null)
const messageInput = ref(null)

const filteredSessions = computed(() => {
  if (activeTab.value === 'all') {
    return sessions.value
  }
  const tabStatus = Number(activeTab.value)
  return sessions.value.filter(item => Number(item.status) === tabStatus)
})

onMounted(() => {
  initWorkbench()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onBeforeUnmount(() => {
  stopPolling()
  if (keywordTimer) {
    clearTimeout(keywordTimer)
    keywordTimer = null
  }
  if (scriptKeywordTimer) {
    clearTimeout(scriptKeywordTimer)
    scriptKeywordTimer = null
  }
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})

async function initWorkbench() {
  await refreshSessions(false)
  startPolling()
}
async function refreshSessions(silent = false) {
  const requestId = ++sessionRequestId
  if (!silent) {
    sessionLoading.value = true
  }

  try {
    const payload = await fetchSessionPayload()
    if (requestId !== sessionRequestId) {
      return
    }
    const list = payload.list || []
    sessions.value = Array.isArray(list) ? list : []
    syncActiveSession()
  } catch (error) {
    console.error(error)
    if (!silent) {
      ElMessage.error('会话加载失败，请稍后重试')
    }
  } finally {
    if (!silent && requestId === sessionRequestId) {
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
  refreshSessions(false)
}
function onKeywordChange() {
  if (keywordTimer) {
    clearTimeout(keywordTimer)
  }
  keywordTimer = setTimeout(() => {
    queryLimit.value = 100
    refreshSessions(true)
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

  const requestId = ++messageRequestId
  if (!silent) {
    messageLoading.value = true
  }

  try {
    const response = await getCsSessionMessages({
      session_id: activeSession.value.session_id,
      page: 1,
      limit: 50
    })
    if (requestId !== messageRequestId) {
      return
    }
    const payload = response.data || response || {}
    const data = payload.data || payload || {}
    messages.value = Array.isArray(data.list) ? data.list : []
    nextTick(() => {
      scrollToBottom()
    })
  } catch (error) {
    console.error(error)
    if (!silent) {
      ElMessage.error('会话消息加载失败，请稍后重试')
    }
  } finally {
    if (!silent && requestId === messageRequestId) {
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
    clearCurrentSessionUnread()
  } catch (error) {
    // 忽略已读失败，不影响主流程
  }
}
async function handleSend() {
  if (!activeSession.value || !activeSession.value.session_id) {
    ElMessage.warning('请先选择会话')
    return
  }

  const content = String(messageForm.value.content || '').trim()
  if (!content) {
    ElMessage.warning('消息内容不能为空')
    return
  }

  sending.value = true
  try {
    await createCsMessage({
      session_id: activeSession.value.session_id,
      content,
      message_type: 'text',
      extra: {}
    })
    // eslint-disable-next-line require-atomic-updates
    messageForm.value.content = ''
    await refreshMessages(false)
    await refreshSessions()
  } finally {
    // eslint-disable-next-line require-atomic-updates
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
  messageForm.value.content = messageForm.value.content ? `${messageForm.value.content}${text}` : text
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
    ElMessage.error('话术分类加载失败')
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
    ElMessage.error('话术列表加载失败')
  } finally {
    scriptListLoading.value = false
  }
}
function handleScriptCategorySelect(categoryId) {
  activeScriptCategoryId.value = Number(categoryId || 0)
  loadScriptList()
}
function onScriptKeywordInput() {
  if (scriptKeywordTimer) {
    clearTimeout(scriptKeywordTimer)
  }
  scriptKeywordTimer = setTimeout(() => {
    loadScriptList()
  }, 300)
}
function handleScriptSelect(row) {
  const content = String((row && row.content) || '').trim()
  if (!content) {
    ElMessage.warning('该话术暂无内容')
    return
  }
  messageForm.value.content = content
  scriptPopoverVisible.value = false
  nextTick(() => {
    if (messageInput.value && messageInput.value.focus) {
      messageInput.value.focus()
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
      ElMessage.warning('暂无可选在线客服')
    }
  } catch (error) {
    console.error(error)
    ElMessage.error('客服列表加载失败')
  } finally {
    staffLoading.value = false
  }
}
async function submitStaffAction() {
  if (!staffTargetSession.value || !staffTargetSession.value.session_id) {
    ElMessage.warning('请选择会话')
    return
  }
  if (!selectedStaffId.value) {
    ElMessage.warning('请选择客服')
    return
  }

  const staff = getSelectedStaff()
  const staffName = staff ? staffOptionLabel(staff) : `客服#${selectedStaffId.value}`
  if (staffActionType.value === 'transfer') {
    try {
      await ElMessageBox.confirm(`确定将该会话转接给 ${staffName} 吗？`, '转接确认', { type: 'warning' })
    } catch (error) {
      return
    }
  }

  staffSubmitting.value = true
  const row = staffTargetSession.value
  try {
    if (staffActionType.value === 'transfer') {
      await transferCsSession({ session_id: row.session_id, to_staff_id: Number(selectedStaffId.value), remark: '' })
      ElMessage.success('转接成功')
    } else {
      await acceptCsSession({ session_id: row.session_id, staff_id: Number(selectedStaffId.value) })
      ElMessage.success('接入成功')
    }
    staffDialogVisible.value = false
    await refreshSessions()
    if (activeSession.value && activeSession.value.session_id === row.session_id) {
      await refreshMessages(false)
    }
  } finally {
    // eslint-disable-next-line require-atomic-updates
    staffSubmitting.value = false
  }
}
function getSelectedStaff() {
  return staffOptions.value.find(item => Number(item.id) === Number(selectedStaffId.value))
}
function staffOptionLabel(item) {
  const name = item.nickname || item.real_name || item.name || item.staff_name || `客服#${item.id || 0}`
  const staffNo = item.staff_no ? ` · ${item.staff_no}` : ''
  const sessions = item.max_sessions ? ` · ${Number(item.current_sessions || 0)}/${item.max_sessions}` : ''
  return `${name}${staffNo}${sessions}`
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
function clearCurrentSessionUnread() {
  if (!activeSession.value || !activeSession.value.session_id) {
    return
  }
  const sessionId = activeSession.value.session_id
  const index = sessions.value.findIndex(item => item.session_id === sessionId)
  if (index >= 0) {
    const next = {
      ...sessions.value[index],
      unread_count: 0
    }
    sessions.value.splice(index, 1, next)
    activeSession.value = next
    return
  }
  activeSession.value = {
    ...activeSession.value,
    unread_count: 0
  }
}
async function handleClose(row) {
  await ElMessageBox.confirm('确定结束该会话吗？', '提示', { type: 'warning' })
  await closeCsSession({ session_id: row.session_id, remark: '' })
  ElMessage.success('会话已结束')
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
  const wrap = messageScroll.value
  if (!wrap) {
    return
  }
  wrap.scrollTop = wrap.scrollHeight
}
async function pollTick() {
  if (pollingBusy || document.hidden) {
    return
  }
  pollingBusy = true
  try {
    await refreshSessions(true)
    await refreshMessages(true)
  } finally {
    // eslint-disable-next-line require-atomic-updates
    pollingBusy = false
  }
}
function startPolling() {
  stopPolling()
  if (document.hidden) {
    return
  }
  pollTimer = setInterval(() => {
    pollTick()
  }, pollInterval)
}
function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}
function handleVisibilityChange() {
  if (document.hidden) {
    stopPolling()
    return
  }
  startPolling()
  pollTick()
}
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
  border: 1px solid #e5e9f2;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;

  .session-pane {
    width: 300px;
    border-right: 1px solid #e5e9f2;
    display: flex;
    flex-direction: column;

    .session-header {
      padding: 14px;
      border-bottom: 1px solid #eef2f7;

      .title {
        font-size: 16px;
        font-weight: 600;
        color: #303133;
        margin-bottom: 10px;
      }
    }

    .session-tabs {
      padding: 0 8px;
      border-bottom: 1px solid #f3f5f9;
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
          background: #f7f9fc;
        }

        &.active {
          border-color: #8cb9ff;
          background: #edf4ff;
        }

        &.unread {
          .user-text,
          .msg-text {
            color: var(--text-primary);
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
          color: #303133;
          font-size: 13px;
          font-weight: 500;
        }

        .time-text {
          color: #909399;
          font-size: 12px;
        }

        .msg-text {
          color: #606266;
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
      border-bottom: 1px solid #eef2f7;
      padding: 12px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;

      .header-title {
        font-size: 15px;
        color: #303133;
        font-weight: 600;
      }

      .header-sub {
        color: #909399;
        margin-top: 4px;
        font-size: 12px;
      }
    }

    .chat-body {
      flex: 1;
      overflow-y: auto;
      padding: 14px;
      background: #f7f9fc;

      .empty-message {
        color: #909399;
        text-align: center;
        margin-top: 40px;
      }

      .msg-line {
        margin-bottom: 12px;

        .meta {
          color: #909399;
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
            background: #eef1f6;
            color: #303133;
          }
        }

        &.staff {
          text-align: right;

          .meta {
            text-align: right;
          }

          .bubble {
            background: #dff2cf;
            color: #303133;
          }
        }

        &.system {
          text-align: center;

          .system-tip {
            display: inline-block;
            background: #f2f6fc;
            color: #909399;
            font-size: 12px;
            border-radius: 999px;
            padding: 4px 10px;
          }
        }
      }
    }

    .chat-footer {
      border-top: 1px solid #eef2f7;
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
        border: 1px solid var(--prev-border-color-lighter);
        border-radius: 8px;
        overflow: hidden;
        background: var(--prev-bg-white);
      }

      .script-categories {
        width: 142px;
        flex-shrink: 0;
        padding: 8px;
        border-right: 1px solid var(--prev-border-color-lighter);
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
          background: var(--prev-bg-white);
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
    border-left: 1px solid #e5e9f2;
    padding: 14px;
    background: #fbfcfe;

    .detail-title {
      font-size: 15px;
      color: #303133;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .detail-content {
      .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        color: #606266;
        font-size: 13px;
        padding: 9px 0;
        border-bottom: 1px dashed #e9edf3;

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
