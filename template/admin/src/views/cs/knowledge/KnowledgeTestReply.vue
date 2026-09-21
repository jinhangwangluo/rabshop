<template>
  <div class="chat-wrap">
    <!-- 顶部标题栏 -->
    <div class="chat-header">
      <div class="chat-header-avatar">
        <img v-if="baseInfo && baseInfo.cover" :src="baseInfo.cover" class="avatar-img">
        <div v-else class="avatar-placeholder"><LegacyIcon name="el-icon-collection" /></div>
      </div>
      <div class="chat-header-info">
        <div class="chat-header-name">{{ (baseInfo && baseInfo.name) || '知识库助手' }}</div>
        <div class="chat-header-desc">基于知识库内容回复，输入问题进行测试</div>
      </div>
      <el-tooltip content="清空对话">
        <el-button
          type="text"
          icon="el-icon-delete"
          class="clear-btn"
          :disabled="messages.length === 0"
          @click="clearMessages"
        />
      </el-tooltip>
    </div>

    <!-- 消息区 -->
    <div ref="msgListRef" class="chat-body">
      <!-- 欢迎提示 -->
      <div v-if="messages.length === 0" class="chat-welcome">
        <div class="welcome-avatar">
          <img v-if="baseInfo && baseInfo.cover" :src="baseInfo.cover" class="avatar-img">
          <div v-else class="avatar-placeholder large"><LegacyIcon name="el-icon-collection" /></div>
        </div>
        <div class="welcome-name">{{ (baseInfo && baseInfo.name) || '知识库助手' }}</div>
        <div class="welcome-text">你好！我是基于该知识库的智能助手，请输入你的问题，我将从知识库中为你检索相关内容并回答。</div>
      </div>

      <!-- 消息列表 -->
      <div v-for="(msg, idx) in messages" :key="idx" class="msg-row-wrap">
        <!-- 用户消息（右侧） -->
        <div v-if="msg.role === 'user'" class="msg-row user">
          <div class="msg-bubble user-bubble">{{ msg.content }}</div>
          <div class="msg-avatar user-avatar"><LegacyIcon name="el-icon-user" /></div>
        </div>

        <!-- AI 消息（左侧） -->
        <div v-else class="msg-row assistant">
          <div class="msg-avatar ai-avatar">
            <img v-if="baseInfo && baseInfo.cover" :src="baseInfo.cover" class="avatar-img">
            <div v-else class="avatar-placeholder"><LegacyIcon name="el-icon-collection" /></div>
          </div>
          <div class="msg-content">
            <div class="msg-bubble ai-bubble">
              <span v-if="msg.loading && !msg.content" class="typing">
                <span /><span /><span />
              </span>
              <template v-else>{{ msg.content }}</template>
            </div>
            <!-- 命中段落 -->
            <div v-if="!msg.loading && msg.hits && msg.hits.length > 0" class="hits-wrap">
              <div class="hits-label">
                <LegacyIcon name="el-icon-search" />
                引用片段（{{ msg.hits.length }} 条）
                <el-button type="text" size="mini" @click="msg._showHits = !msg._showHits">
                  {{ msg._showHits ? '收起' : '展开' }}
                </el-button>
              </div>
              <div v-if="msg._showHits" class="hits-list">
                <div v-for="(hit, hi) in msg.hits" :key="hi" class="hit-item">
                  <span class="hit-num">{{ hi + 1 }}</span>
                  <span class="hit-text">{{ hit.content }}</span>
                  <span v-if="hit.score" class="hit-score">{{ (hit.score * 100).toFixed(1) }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 底部输入区 -->
    <div class="chat-footer">
      <div class="input-wrap">
        <el-input
          ref="inputRef"
          v-model="inputText"
          type="textarea"
          :autosize="{ minRows: 1, maxRows: 4 }"
          placeholder="输入问题，按 Enter 发送，Shift+Enter 换行..."
          resize="none"
          :disabled="sending"
          @keydown.enter="handleEnter"
        />
        <el-button
          type="primary"
          :loading="sending"
          :disabled="!inputText.trim()"
          class="send-btn"
          @click="sendMessage"
        >
          <LegacyIcon v-if="!sending" name="el-icon-s-promotion" />
        </el-button>
      </div>
      <div class="footer-hint">Enter 发送 &nbsp;·&nbsp; Shift+Enter 换行</div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, nextTick, ref } from 'vue'
import { streamCsKnowledgeBaseReply } from '@/api/cs'

defineOptions({
  name: 'KnowledgeTestReply'
})

const props = defineProps({
  knowledgeBaseId: { type: Number, required: true },
  baseInfo: { type: Object, default: () => ({}) }
})
const { proxy } = getCurrentInstance()
const confirm = proxy.$confirm
const msgListRef = ref(null)
const sending = ref(false)
const inputText = ref('')
const messages = ref([])

function setSending(value) {
  sending.value = value
}

function handleEnter(e) {
  if (e.shiftKey) return
  e.preventDefault()
  sendMessage()
}

async function sendMessage() {
  const q = inputText.value.trim()
  if (!q || sending.value) return

  inputText.value = ''
  messages.value.push({ role: 'user', content: q })

  const aiIndex = messages.value.length
  messages.value.push({ role: 'assistant', content: '', hits: [], loading: true, _showHits: false })
  nextTick(scrollToBottom)

  setSending(true)
  try {
    await streamCsKnowledgeBaseReply(props.knowledgeBaseId, { question: q }, payload => {
      handleStreamPayload(payload, aiIndex)
    })
    finishStreamMessage(aiIndex)
  } catch (e) {
    failStreamMessage(aiIndex, (e && e.message) || '请求失败，请稍后重试')
  } finally {
    setMessageLoading(aiIndex, false)
    setSending(false)
    nextTick(scrollToBottom)
  }
}

function handleStreamPayload(payload, aiIndex) {
  if (!payload || typeof payload !== 'object') return
  const aiMsg = messages.value[aiIndex]
  if (!aiMsg) return

  if (payload.type === 'start') {
    aiMsg.hits = Array.isArray(payload.hits) ? payload.hits : []
    nextTick(scrollToBottom)
    return
  }

  if (payload.type === 'chunk') {
    aiMsg.loading = true
    aiMsg.content += payload.delta || ''
    nextTick(scrollToBottom)
    return
  }

  if (payload.type === 'done') {
    aiMsg.loading = false
    if (payload.content) {
      aiMsg.content = payload.content
    }
    if (Array.isArray(payload.hits)) {
      aiMsg.hits = payload.hits
    }
    nextTick(scrollToBottom)
    return
  }

  if (payload.type === 'error') {
    aiMsg.loading = false
    aiMsg.content = payload.message || 'AI模型调用失败，请检查模型配置'
    nextTick(scrollToBottom)
  }
}

function finishStreamMessage(aiIndex) {
  const aiMsg = messages.value[aiIndex]
  if (!aiMsg) return
  if (!aiMsg.content) {
    aiMsg.content = '暂无回复'
  }
}

function failStreamMessage(aiIndex, message) {
  const aiMsg = messages.value[aiIndex]
  if (!aiMsg) return
  aiMsg.content = message
}

function setMessageLoading(aiIndex, loading) {
  const aiMsg = messages.value[aiIndex]
  if (!aiMsg) return
  aiMsg.loading = loading
}

function clearMessages() {
  confirm('确定清空所有对话记录吗？', '提示', { type: 'warning' }).then(() => {
    messages.value = []
  }).catch(() => {})
}

function scrollToBottom() {
  const el = msgListRef.value
  if (el) el.scrollTop = el.scrollHeight
}
</script>

<style lang="scss" scoped>
.chat-wrap {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  min-height: 500px;
  background: var(--dashboard-bg);
  color: var(--dashboard-text-primary);
  overflow: hidden;
}

/* 顶部 */
.chat-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  background: var(--dashboard-surface);
  border-bottom: 1px solid var(--dashboard-border);
  flex-shrink: 0;
}

.chat-header-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
}

.chat-header-info {
  flex: 1;
  min-width: 0;
}

.chat-header-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--dashboard-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chat-header-desc {
  font-size: 12px;
  color: var(--dashboard-text-muted);
  margin-top: 2px;
}

.clear-btn {
  color: var(--dashboard-text-muted) !important;
  font-size: 16px !important;
  padding: 6px !important;

  &:hover {
    color: var(--dashboard-danger) !important;
  }
}

/* 消息区 */
.chat-body {
  flex: 1;
  overflow-y: auto;
  padding: 28px 60px;
  display: flex;
  flex-direction: column;
  gap: 22px;

  &::-webkit-scrollbar {
    width: 4px;
  }

  &::-webkit-scrollbar-thumb {
    background: var(--dashboard-border);
    border-radius: 2px;
  }
}

/* 欢迎 */
.chat-welcome {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 20px 40px;
  text-align: center;
}

.welcome-avatar {
  width: 68px;
  height: 68px;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 4px 16px var(--dashboard-accent-outline);
}

.welcome-name {
  font-size: 17px;
  font-weight: 600;
  color: var(--dashboard-text-primary);
}

.welcome-text {
  font-size: 13px;
  color: var(--dashboard-text-secondary);
  line-height: 1.8;
  max-width: 420px;
}

/* 消息行 */
.msg-row {
  display: flex;
  align-items: flex-end;
  gap: 10px;

  &.user {
    flex-direction: row;
    align-self: flex-end;
    max-width: 72%;
  }

  &.assistant {
    flex-direction: row;
    align-self: flex-start;
    max-width: 80%;
  }
}

.msg-row-wrap {
  display: contents;
}

.msg-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.user-avatar {
  background: linear-gradient(135deg, var(--dashboard-accent) 0%, var(--dashboard-accent-light) 100%);
  color: var(--dashboard-on-accent);
}

.ai-avatar {
  background: linear-gradient(135deg, var(--dashboard-accent-outline) 0%, var(--dashboard-accent-light) 100%);
  color: var(--dashboard-accent);
}

.msg-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.msg-bubble {
  padding: 10px 14px;
  font-size: 14px;
  line-height: 1.75;
  word-break: break-word;
  white-space: pre-wrap;
}

.user-bubble {
  background: linear-gradient(135deg, var(--dashboard-accent) 0%, var(--dashboard-accent-hover) 100%);
  color: var(--dashboard-on-accent);
  border-radius: 16px 16px 4px 16px;
}

.ai-bubble {
  background: var(--dashboard-surface);
  color: var(--dashboard-text-primary);
  border-radius: 16px 16px 16px 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.14);
}

/* 打字动画 */
.typing {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 2px;

  span {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--dashboard-accent-light);
    animation: blink 1.2s infinite ease-in-out;

    &:nth-child(2) { animation-delay: 0.2s; }
    &:nth-child(3) { animation-delay: 0.4s; }
  }
}

@keyframes blink {
  0%, 80%, 100% { transform: scale(0.7); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}

/* 引用片段 */
.hits-wrap {
  background: var(--dashboard-surface-muted);
  border: 1px solid var(--dashboard-border);
  border-radius: 10px;
  overflow: hidden;
  font-size: 12px;
}

.hits-label {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 7px 12px;
  color: var(--dashboard-text-muted);
  font-weight: 500;
  border-bottom: 1px solid var(--dashboard-border-soft);

  i { color: var(--dashboard-accent); }

  .el-button {
    margin-left: auto;
    color: var(--dashboard-text-muted);
    padding: 0;
    height: auto;
    line-height: 1;
  }
}

.hits-list {
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.hit-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  color: var(--dashboard-text-secondary);
}

.hit-num {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--dashboard-accent);
  color: var(--dashboard-on-accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
}

.hit-text {
  flex: 1;
  line-height: 1.6;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.hit-score {
  flex-shrink: 0;
  color: var(--dashboard-success);
  font-weight: 600;
}

/* 底部输入区 */
.chat-footer {
  flex-shrink: 0;
  background: var(--dashboard-surface);
  border-top: 1px solid var(--dashboard-border);
  padding: 14px 60px 12px;
}

.input-wrap {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  background: var(--dashboard-surface-muted);
  border: 1.5px solid var(--dashboard-border);
  border-radius: 14px;
  padding: 8px 8px 8px 16px;
  transition: border-color 0.2s;

  &:focus-within {
    border-color: var(--dashboard-accent);
    background: var(--dashboard-surface);
    box-shadow: 0 0 0 2px rgba(243, 65, 0, 0.18);

  }

  :deep(.el-textarea__inner) {
    background: transparent;
    border: none;
    padding: 0;
    box-shadow: none !important;
    font-size: 14px;
    line-height: 1.6;
    color: var(--dashboard-text-primary);
    resize: none;

    &::placeholder { color: var(--dashboard-text-muted); }
  }

  .el-input--medium{
    height: 55px;
  }
}

.send-btn {
  flex-shrink: 0;
  width: 38px !important;
  height: 38px !important;
  border-radius: 10px !important;
  padding: 0 !important;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.footer-hint {
  margin-top: 7px;
  font-size: 11px;
  color: var(--dashboard-text-muted);
  text-align: center;
}

/* 头像通用 */
.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--dashboard-accent-outline) 0%, var(--dashboard-accent-light) 100%);
  color: var(--dashboard-accent);
  font-size: 18px;

  &.large {
    font-size: 28px;
  }
}
</style>
