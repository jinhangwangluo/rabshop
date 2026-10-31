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
          :icon="Delete"
          class="clear-btn"
          :disabled="messages.length === 0"
          @click="clearMessages"
        />
      </el-tooltip>
    </div>

    <!-- 消息区 -->
    <div ref="msgList" class="chat-body">
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
      <template v-for="(msg, idx) in messages">
        <!-- 用户消息（右侧） -->
        <div v-if="msg.role === 'user'" :key="'u' + idx" class="msg-row user">
          <div class="msg-bubble user-bubble">{{ msg.content }}</div>
          <div class="msg-avatar user-avatar"><LegacyIcon name="el-icon-user" /></div>
        </div>

        <!-- AI 消息（左侧） -->
        <div v-else :key="'a' + idx" class="msg-row assistant">
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
      </template>
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
import { nextTick, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import { streamCsKnowledgeBaseReply } from '@/api/cs'

defineOptions({
  name: 'KnowledgeTestReply'
})

const props = defineProps({
  knowledgeBaseId: { type: Number, required: true },
  baseInfo: { type: Object, default: () => ({}) }
})

const sending = ref(false)
const inputText = ref('')
const messages = ref([])
const msgList = ref(null)

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

  sending.value = true
  try {
    await streamCsKnowledgeBaseReply(props.knowledgeBaseId, { question: q }, payload => {
      handleStreamPayload(payload, aiIndex)
    })
    finishStreamMessage(aiIndex)
  } catch (e) {
    failStreamMessage(aiIndex, (e && e.message) || '请求失败，请稍后重试')
  } finally {
    setMessageLoading(aiIndex, false)
    // eslint-disable-next-line require-atomic-updates
    sending.value = false
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
  ElMessageBox.confirm('确定清空所有对话记录吗？', '提示', { type: 'warning' }).then(() => {
    messages.value = []
  }).catch(() => {})
}

function scrollToBottom() {
  const el = msgList.value
  if (el) el.scrollTop = el.scrollHeight
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.chat-wrap {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  min-height: 500px;
  background: $surface-page;
  overflow: hidden;
}

/* 顶部 */
.chat-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  background: #fff;
  border-bottom: 1px solid var(--ds-outline-ghost);
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
  color: $text-title;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chat-header-desc {
  font-size: 12px;
  color: $text-secondary;
  margin-top: 2px;
}

.clear-btn {
  color: $text-disabled !important;
  font-size: 16px !important;
  padding: 6px !important;

  &:hover {
    color: #cf1322 !important;
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
    background: var(--brand-primary-pale);
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
  box-shadow: 0 4px 16px $brand-shadow-card;
}

.welcome-name {
  font-size: 17px;
  font-weight: 600;
  color: $text-title;
}

.welcome-text {
  font-size: 13px;
  color: $text-secondary;
  line-height: 1.8;
  max-width: 420px;
}

/* 消息行 */
.msg-row {
  display: flex;
  align-items: flex-end;
  gap: 10px;

  &.user {
    flex-direction: row-reverse;
    align-self: flex-end;
    max-width: 72%;
  }

  &.assistant {
    flex-direction: row;
    align-self: flex-start;
    max-width: 80%;
  }
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
  background: linear-gradient(135deg, $brand-primary 0%, $brand-primary-light 100%);
  color: #fff;
}

.ai-avatar {
  background: linear-gradient(135deg, $brand-primary-pale 0%, $brand-primary-light 100%);
  color: $brand-primary-dark;
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
  background: linear-gradient(135deg, $brand-primary 0%, $brand-primary-dark 100%);
  color: #fff;
  border-radius: 16px 16px 4px 16px;
}

.ai-bubble {
  background: #fff;
  color: $text-primary;
  border-radius: 16px 16px 16px 4px;
  box-shadow: 0 2px 8px $brand-shadow-card;
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
    background: $brand-primary-light;
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
  background: mix(#ffffff, $brand-primary-pale, 85%);
  border: 1px solid var(--ds-outline-ghost);
  border-radius: 10px;
  overflow: hidden;
  font-size: 12px;
}

.hits-label {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 7px 12px;
  color: $text-secondary;
  font-weight: 500;
  border-bottom: 1px solid var(--ds-outline-ghost);

  i { color: $brand-primary; }

  .el-button {
    margin-left: auto;
    color: $text-secondary;
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
  color: $text-regular;
}

.hit-num {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: $brand-primary;
  color: #fff;
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
  color: $text-success;
  font-weight: 600;
}

/* 底部输入区 */
.chat-footer {
  flex-shrink: 0;
  background: #fff;
  border-top: 1px solid var(--ds-outline-ghost);
  padding: 14px 60px 12px;
}

.input-wrap {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  background: $surface-page;
  border: 1.5px solid var(--ds-outline-ghost);
  border-radius: 14px;
  padding: 8px 8px 8px 16px;
  transition: border-color 0.2s;

  &:focus-within {
    border-color: $brand-primary;
    background: #fff;
    box-shadow: 0 0 0 2px $brand-focus-shadow;
  }

  :deep(.el-textarea__inner) {
    background: transparent;
    border: none;
    padding: 0;
    box-shadow: none !important;
    font-size: 14px;
    line-height: 1.6;
    color: $text-primary;
    resize: none;

    &::placeholder { color: $text-placeholder; }
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
  color: $text-placeholder;
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
  background: linear-gradient(135deg, $brand-primary-pale 0%, $brand-primary-light 100%);
  color: $brand-primary;
  font-size: 18px;

  &.large {
    font-size: 28px;
  }
}
</style>
