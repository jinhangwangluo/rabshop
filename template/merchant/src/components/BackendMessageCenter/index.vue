<template>
  <div class="backend-message-center">
    <el-badge
      :value="unreadCount"
      :max="99"
      :hidden="unreadCount <= 0"
      class="backend-message-center__badge"
    >
      <button
        type="button"
        class="backend-message-center__trigger"
        @click="openDialog"
      >
        <LegacyIcon name="el-icon-bell" />
      </button>
    </el-badge>

    <el-dialog
      v-model="dialogVisible"
      title="消息中心"
      width="88%"
      top="6vh"
      custom-class="backend-message-dialog"
      append-to-body
      @opened="handleOpened"
    >
      <div class="backend-message">
        <aside class="backend-message__aside">
          <button
            v-for="item in typeList"
            :key="item.value || 'all'"
            type="button"
            class="backend-message__type"
            :class="{ 'backend-message__type--active': activeGroup === item.value }"
            @click="handleTypeChange(item.value)"
          >
            <span class="backend-message__type-icon">
              <LegacyIcon :name="item.icon || 'el-icon-message'" />
            </span>
            <span class="backend-message__type-label">{{ item.label }}</span>
            <span
              v-if="Number(item.unread_count || 0) > 0"
              class="backend-message__type-count"
            >
              {{ formatBadge(item.unread_count) }}
            </span>
          </button>
        </aside>

        <section class="backend-message__main">
          <div class="backend-message__toolbar">
            <div class="backend-message__summary">
              <span>{{ activeTypeLabel }}</span>
              <span class="backend-message__summary-count">共 {{ total }} 条</span>
            </div>
            <div class="backend-message__actions">
              <el-button
                size="mini"
                type="primary"
                plain
                :icon="Check"
                :loading="actionLoading"
                class="backend-message__action-btn"
                @click="handleReadAll"
              >
                全部标为已读
              </el-button>
              <el-button
                size="mini"
                type="primary"
                plain
                :icon="Delete"
                :loading="actionLoading"
                class="backend-message__action-btn"
                @click="handleClear"
              >
                一键清理
              </el-button>
            </div>
          </div>

          <div
            v-loading="loading"
            class="backend-message__list"
          >
            <el-empty
              v-if="!loading && list.length === 0"
              description="暂无消息"
              :image-size="90"
            />

            <div
              v-for="item in list"
              :key="item.id"
              class="backend-message__item"
              :class="{
                'backend-message__item--active': expandedId === item.id,
                'backend-message__item--unread': Number(item.read_status || 0) === 0
              }"
              @click="toggleMessage(item)"
            >
              <span class="backend-message__dot" />
              <span class="backend-message__avatar">
                <LegacyIcon :name="resolveIcon(item)" />
              </span>
              <div class="backend-message__content">
                <div class="backend-message__item-head">
                  <div class="backend-message__title-row">
                    <strong class="backend-message__title">{{ item.title || '后台消息' }}</strong>
                    <el-tag
                      size="mini"
                      effect="plain"
                      class="backend-message__tag"
                    >
                      {{ item.message_sub_type_text || item.message_group_text || '消息' }}
                    </el-tag>
                  </div>
                  <span class="backend-message__time">{{ item.create_time || '-' }}</span>
                </div>
                <div class="backend-message__brief">{{ item.content || '暂无消息内容' }}</div>
                <transition name="el-zoom-in-top">
                  <div
                    v-if="expandedId === item.id"
                    class="backend-message__detail"
                  >
                    <p>{{ item.content || '暂无消息内容' }}</p>
                    <div class="backend-message__meta">
                      <span>关联类型：{{ item.relation_type || '-' }}</span>
                      <span>关联ID：{{ item.relation_id || '-' }}</span>
                      <span>状态：{{ item.read_status_text || '未读' }}</span>
                    </div>
                  </div>
                </transition>
              </div>
              <LegacyIcon :name="expandedId === item.id ? 'el-icon-arrow-up' : 'el-icon-arrow-down'" class="backend-message__arrow" />
            </div>
          </div>

          <div class="backend-message__pagination">
            <el-pagination
              v-model:current-page="page"
              v-model:page-size="limit"
              background
              :page-sizes="[10, 20, 50]"
              :total="total"
              layout="total, sizes, prev, pager, next"
              @current-change="handlePageChange"
              @size-change="handleSizeChange"
            />
          </div>
        </section>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { useBackendMessageStore } from '@/store/modules/backendMessage'
import { storeToRefs } from 'pinia'
import {
  clearBackendMessage,
  getBackendMessageList,
  getBackendMessageTypes,
  readAllBackendMessage,
  readBackendMessage
} from '@/api/backendMessage'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Delete } from '@element-plus/icons-vue'
import { computed, ref, watch } from 'vue'

defineOptions({
  name: 'BackendMessageCenter'
})

const backendMessageStore = useBackendMessageStore()
const { unreadCount: storeUnreadCount, latestMessage: backendMessageLatest } = storeToRefs(backendMessageStore)

const dialogVisible = ref(false)
const loading = ref(false)
const actionLoading = ref(false)
const activeGroup = ref('')
const typeList = ref([])
const list = ref([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const expandedId = ref(0)

const unreadCount = computed(() => Number(storeUnreadCount.value || 0))
const activeTypeLabel = computed(() => {
  const item = typeList.value.find(type => type.value === activeGroup.value)
  return item ? item.label : '全部消息'
})

watch(backendMessageLatest, (message) => {
  if (!message || !message.id) {
    return
  }
  fetchTypes()
  if (dialogVisible.value && (activeGroup.value === '' || activeGroup.value === message.message_group)) {
    fetchList()
  }
})

function openDialog() {
  dialogVisible.value = true
}

function handleOpened() {
  fetchTypes()
  fetchList()
}

async function fetchTypes() {
  try {
    const res = await getBackendMessageTypes()
    typeList.value = (res.data && res.data.list) || []
    backendMessageStore.fetchUnreadCount()
  } catch (error) {
    typeList.value = []
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getBackendMessageList({
      page: page.value,
      limit: limit.value,
      message_group: activeGroup.value
    })
    const data = res.data || {}
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } catch (error) {
    list.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function handleTypeChange(value) {
  activeGroup.value = value
  page.value = 1
  expandedId.value = 0
  fetchList()
}

function handlePageChange(pageNum) {
  page.value = pageNum
  fetchList()
}

function handleSizeChange(size) {
  limit.value = size
  page.value = 1
  fetchList()
}

async function toggleMessage(item) {
  expandedId.value = expandedId.value === item.id ? 0 : item.id
  if (Number(item.read_status || 0) === 0) {
    await readBackendMessage({ ids: [item.id] })
    const current = list.value.find(message => message.id === item.id)
    if (current) {
      current.read_status = 1
      current.read_status_text = '已读'
    }
    fetchTypes()
  }
}

async function handleReadAll() {
  actionLoading.value = true
  try {
    await readAllBackendMessage({ message_group: activeGroup.value })
    ElMessage.success('已标记为已读')
    await fetchTypes()
    await fetchList()
  } finally {
    actionLoading.value = false
  }
}

function handleClear() {
  ElMessageBox.confirm('清理后当前消息将从消息中心隐藏，历史记录仍会保留用于审计。确认清理吗？', '一键清理', {
    type: 'warning'
  }).then(async() => {
    actionLoading.value = true
    try {
      await clearBackendMessage({ message_group: activeGroup.value })
      ElMessage.success('已清理')
      page.value = 1
      expandedId.value = 0
      await fetchTypes()
      await fetchList()
    } finally {
      actionLoading.value = false
    }
  }).catch(() => {})
}

function resolveIcon(item) {
  const type = typeList.value.find(type => type.value === item.message_group)
  return type ? type.icon : 'el-icon-message'
}

function formatBadge(value) {
  const count = Number(value || 0)
  return count > 99 ? '99+' : String(count)
}
</script>

<style lang="scss" scoped>
.backend-message-center {
  position: relative;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  justify-content: center;
  height: 100%;
  padding: 0 18px 0 8px;

  &__badge {
    position: relative;
    z-index: 2;
    display: inline-flex;
    line-height: 1;
  }

  :deep(.el-badge__content.is-fixed) {
    z-index: 3;
    pointer-events: none;
  }

  &__trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: var(--text-secondary);
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 10px;
    transition: background .2s, color .2s;

    &:hover {
      color: var(--brand-primary);
      background: var(--ds-surface-low);
    }

    i {
      font-size: 20px;
      line-height: 1;
    }
  }
}
.backend-message-dialog {
  display: flex;
  flex-direction: column;
  max-height: 88vh;
  max-width: 1120px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--ds-surface-card);

  .el-dialog__header {
    padding: 16px 20px;
    background: var(--ds-surface-card);
    border-bottom: 1px solid var(--ds-outline);
  }

  .el-dialog__title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
  }

  .el-dialog__body {
    flex: 1;
    min-height: 0;
    padding: 0 !important;
    overflow: hidden;
    background: var(--ds-surface-card);
  }
}

.backend-message {
  display: flex;
  height: calc(88vh - 55px);
  min-height: 0;
  color: var(--text-primary);
  background: var(--ds-surface-card);

  &__aside {
    flex-shrink: 0;
    width: 238px;
    padding: 16px 14px;
    background: var(--ds-surface-card);
    border-right: 1px solid var(--ds-outline);
  }

  &__type {
    display: flex;
    align-items: center;
    width: 100%;
    height: 44px;
    margin-bottom: 6px;
    padding: 0 10px;
    color: var(--text-primary);
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 6px;
    transition: background .2s, color .2s;
  }

  &__type:hover,
  &__type--active {
    color: var(--brand-primary);
    background: var(--ds-surface-low);
  }

  &__type-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    margin-right: 10px;
    font-size: 16px;
  }

  &__type-label {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    font-size: 14px;
    font-weight: 500;
    text-align: left;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__type-count {
    min-width: 28px;
    height: 18px;
    padding: 0 6px;
    font-size: 12px;
    line-height: 18px;
    color: var(--text-inverse);
    text-align: center;
    background: var(--brand-primary);
    border-radius: 12px;
  }

  &__main {
    display: flex;
    flex: 1;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    padding: 20px 22px 16px;
    background: var(--ds-surface-card);
  }

  &__toolbar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  &__summary {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
  }

  &__summary-count {
    margin-left: 8px;
    font-size: 13px;
    font-weight: 400;
    color: var(--text-secondary);
  }

  &__actions {
    display: flex;
    align-items: center;

    :deep(.backend-message__action-btn) {
      color: var(--brand-primary) !important;
      background: var(--ds-surface-low);
      border-color: var(--ds-outline-ghost);

      &:hover,
      &:focus {
        color: var(--text-inverse) !important;
        background: var(--brand-primary);
        border-color: var(--brand-primary);
      }
    }
  }

  &__list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
  }

  &__item {
    position: relative;
    display: flex;
    align-items: flex-start;
    min-height: 72px;
    padding: 14px 40px 14px 8px;
    cursor: pointer;
    background: var(--ds-surface-card);
    border: 1px solid transparent;
    border-bottom-color: var(--ds-outline);
    transition: background .2s, border .2s, box-shadow .2s;
  }

  &__item:hover,
  &__item--active {
    background: var(--ds-surface-low);
    border-color: var(--ds-outline);
    border-radius: 6px;
    box-shadow: 0 10px 26px var(--brand-shadow-card);
  }

  &__dot {
    flex-shrink: 0;
    width: 7px;
    height: 7px;
    margin: 18px 14px 0 0;
    background: transparent;
    border-radius: 50%;
  }

  &__item--unread &__dot {
    background: var(--brand-primary);
  }

  &__avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 42px;
    height: 42px;
    margin-right: 14px;
    font-size: 18px;
    color: var(--brand-primary);
    background: var(--ds-surface-low);
    border-radius: 50%;
  }

  &__content {
    flex: 1;
    min-width: 0;
  }

  &__item-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
  }

  &__title-row {
    display: flex;
    align-items: center;
    min-width: 0;
  }

  &__title {
    max-width: 360px;
    margin-right: 8px;
    overflow: hidden;
    font-size: 14px;
    color: var(--text-primary);
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__tag {
    color: var(--brand-primary);
    background: var(--ds-surface-low);
    border-color: var(--ds-outline-ghost);
  }

  &__time {
    flex-shrink: 0;
    margin-left: 14px;
    font-size: 12px;
    color: var(--text-secondary);
  }

  &__brief {
    overflow: hidden;
    font-size: 13px;
    line-height: 20px;
    color: var(--text-regular);
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__detail {
    margin-top: 14px;
    padding: 12px 14px;
    font-size: 13px;
    line-height: 22px;
    color: var(--text-primary);
    background: var(--ds-surface-card);
    border: 1px solid var(--ds-outline);
    border-radius: 6px;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    margin-top: 10px;
    font-size: 12px;
    color: var(--text-secondary);

    span {
      margin-right: 14px;
    }
  }

  &__arrow {
    position: absolute;
    top: 24px;
    right: 14px;
    color: var(--text-secondary);
  }

  &__pagination {
    display: flex;
    flex-shrink: 0;
    justify-content: flex-end;
    padding-top: 14px;

    :deep(.el-pagination.is-background .el-pager li:not(.disabled).active) {
      color: var(--text-inverse);
      background-color: var(--brand-primary);
    }

    :deep(.el-pagination.is-background .el-pager li:not(.disabled):hover) {
      color: var(--brand-primary);
    }

    :deep(.el-pagination.is-background .el-pager li:not(.disabled).active:hover) {
      color: var(--text-inverse);
    }
  }
}
</style>

<style lang="scss">
.backend-message-dialog {
  display: flex;
  flex-direction: column;
  max-height: 88vh;
  max-width: 1120px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--ds-surface-card);

  .el-dialog__header {
    padding: 16px 20px;
    background: var(--ds-surface-card);
    border-bottom: 1px solid var(--ds-outline);
  }

  .el-dialog__title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
  }

  .el-dialog__headerbtn .el-dialog__close {
    color: var(--text-secondary);
  }

  .el-dialog__body {
    flex: 1;
    min-height: 0;
    padding: 0 !important;
    overflow: hidden;
    background: var(--ds-surface-card);
  }
}
</style>
