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
        <Bell />
      </button>
    </el-badge>

    <el-dialog
      v-model="dialogVisible"
      title="消息中心"
      width="88%"
      top="6vh"
      class="backend-message-dialog"
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
                type="primary"
                plain
                icon="el-icon-check"
                :loading="actionLoading"
                class="backend-message__action-btn"
                @click="handleReadAll"
              >
                全部标为已读
              </el-button>
              <el-button
                type="primary"
                plain
                icon="el-icon-delete"
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
                      effect="plain"
                      class="backend-message__tag"
                    >
                      {{ item.message_sub_type_text || item.message_group_text || '消息' }}
                    </el-tag>
                  </div>
                  <div class="backend-message__item-extra">
                    <span class="backend-message__time">{{ item.create_time || '-' }}</span>
                    <el-button
                      v-if="Number(item.relation_id || 0) > 0"
                      type="text"
                      icon="el-icon-view"
                      class="backend-message__detail-link"
                      @click.stop="handleViewDetail(item)"
                    >
                      查看详情
                    </el-button>
                  </div>
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
              <LegacyIcon
                :name="expandedId === item.id ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"
                class="backend-message__arrow"
              />
            </div>
          </div>

          <div class="backend-message__pagination">
            <el-pagination
              background
              :current-page="page"
              :page-size="limit"
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
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Bell } from '@element-plus/icons-vue'
import {
  clearBackendMessage,
  getBackendMessageList,
  getBackendMessageTypes,
  readAllBackendMessage,
  readBackendMessage
} from '@/api/backendMessage'
import { useBackendMessageStore } from '@/store/modules/backendMessage'

defineOptions({
  name: 'BackendMessageCenter'
})

const router = useRouter()
const backendMessageStore = useBackendMessageStore()
const { unreadCount, latestMessage } = storeToRefs(backendMessageStore)
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
const activeTypeLabel = computed(() => {
  const item = typeList.value.find(type => type.value === activeGroup.value)
  return item ? item.label : '全部消息'
})

watch(latestMessage, (message) => {
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

function handlePageChange(value) {
  page.value = value
  fetchList()
}

function handleSizeChange(value) {
  limit.value = value
  page.value = 1
  fetchList()
}

async function toggleMessage(item) {
  const messageId = item.id
  expandedId.value = expandedId.value === messageId ? 0 : messageId
  if (Number(item.read_status || 0) === 0) {
    await readBackendMessage({ ids: [messageId] })
    const currentItem = list.value.find(message => message.id === messageId)
    if (currentItem) {
      currentItem.read_status = 1
      currentItem.read_status_text = '已读'
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

function resolveDetailRoute(item) {
  const detailId = Number(item && item.relation_id ? item.relation_id : 0)
  if (!detailId) {
    return null
  }

  const routeMap = {
    order: { name: 'OrderList', queryKey: 'detail_id' },
    user_recharge_order: { name: 'UserRecharge', queryKey: 'detail_id' },
    user_withdraw: { name: 'UserExtract', queryKey: 'detail_id' },
    merchant_withdraw: { name: 'MerchantWithdraw', queryKey: 'detail_id' },
    user: { name: 'UserList', queryKey: 'detail_id' },
    content_comment: { name: 'ContentComment', queryKey: 'detail_id' },
    article: { name: 'ArticleList', queryKey: 'detail_id' },
    special: { name: 'SpecialManage', queryKey: 'detail_id' },
    live_room: { name: 'LiveRoomList', queryKey: 'detail_id' },
    live_anchor: { name: 'LiveAnchorList', queryKey: 'detail_id' },
    merchant: { name: 'MerchantApply', queryKey: 'detail_id' }
  }

  return routeMap[item && item.relation_type] || null
}

function handleViewDetail(item) {
  const detailId = Number(item && item.relation_id ? item.relation_id : 0)
  const detailRoute = resolveDetailRoute(item)
  if (!detailRoute || !detailId) {
    ElMessage.info('暂无可查看的详情')
    return
  }

  dialogVisible.value = false
  expandedId.value = 0
  router.push({
    name: detailRoute.name,
    query: {
      [detailRoute.queryKey]: String(detailId)
    }
  }).catch(() => {
    ElMessage.error('打开消息详情失败')
  })
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
    color: var(--dashboard-text-secondary, #606266);
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 10px;
    transition: background .2s, color .2s;

    &:hover {
      color: var(--dashboard-accent, #409eff);
      background: var(--dashboard-accent-soft, #ecf5ff);
    }

    :deep(svg) {
      width: 20px;
      height: 20px;
    }
  }
}

.backend-message {
  display: flex;
  height: 560px;
  max-height: calc(100vh - 150px);
  min-height: 0;
  overflow: hidden;
  color: var(--dashboard-text-primary, #303133);
  background: var(--dashboard-surface, #fff);

  &__aside {
    flex-shrink: 0;
    width: 238px;
    padding: 16px 14px;
    overflow-y: auto;
    background: var(--dashboard-surface, #fff);
    border-right: 1px solid var(--dashboard-border, #ebeef5);
  }

  &__type {
    display: flex;
    align-items: center;
    width: 100%;
    height: 44px;
    margin-bottom: 6px;
    padding: 0 10px;
    color: var(--dashboard-text-primary, #303133);
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 6px;
    transition: background .2s, color .2s;
  }

  &__type:hover,
  &__type--active {
    color: var(--dashboard-accent, #409eff);
    background: var(--dashboard-accent-soft, #ecf5ff);
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
    color: var(--dashboard-on-accent, #fff);
    text-align: center;
    background: var(--dashboard-accent, #409eff);
    border-radius: 12px;
  }

  &__main {
    display: flex;
    flex: 1;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    overflow: hidden;
    padding: 20px 22px 16px;
    background: var(--dashboard-surface, #fff);
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
    color: var(--dashboard-text-primary, #303133);
  }

  &__summary-count {
    margin-left: 8px;
    font-size: 13px;
    font-weight: 400;
    color: var(--dashboard-text-muted, #909399);
  }

  &__actions {
    display: flex;
    align-items: center;

    :deep(.backend-message__action-btn) {
      color: var(--dashboard-accent, #409eff) !important;
      background: var(--dashboard-accent-soft, #ecf5ff);
      border-color: var(--dashboard-accent-outline, rgba(64, 158, 255, .16));

      &:hover,
      &:focus {
        color: var(--dashboard-on-accent, #fff) !important;
        background: var(--dashboard-accent, #409eff);
        border-color: var(--dashboard-accent, #409eff);
      }
    }
  }

  &__list {
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
  }

  &__item {
    position: relative;
    display: flex;
    align-items: flex-start;
    min-height: 72px;
    padding: 14px 40px 14px 8px;
    cursor: pointer;
    background: var(--dashboard-surface, #fff);
    border: 1px solid transparent;
    border-bottom-color: var(--dashboard-border-soft, #ebeef5);
    transition: background .2s, border .2s, box-shadow .2s;
  }

  &__item:hover,
  &__item--active {
    background: var(--dashboard-surface-muted, #f5f7fa);
    border-color: var(--dashboard-border, #dcdfe6);
    border-radius: 6px;
    box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
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
    background: var(--dashboard-accent, #409eff);
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
    color: var(--dashboard-accent, #409eff);
    background: var(--dashboard-accent-soft, #ecf5ff);
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
    color: var(--dashboard-text-primary, #303133);
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__tag {
    color: var(--dashboard-accent, #409eff);
    background: var(--dashboard-accent-soft, #ecf5ff);
    border-color: var(--dashboard-accent-outline, rgba(64, 158, 255, .16));
  }

  &__time {
    flex-shrink: 0;
    font-size: 12px;
    color: var(--dashboard-text-muted, #909399);
  }

  &__item-extra {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: 14px;
    flex-shrink: 0;
  }

  &__detail-link {
    padding: 0;
    font-size: 12px;
    color: var(--dashboard-accent, #409eff);
  }

  &__brief {
    overflow: hidden;
    font-size: 13px;
    line-height: 20px;
    color: var(--dashboard-text-secondary, #606266);
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  &__detail {
    margin-top: 14px;
    padding: 12px 14px;
    font-size: 13px;
    line-height: 22px;
    color: var(--dashboard-text-primary, #303133);
    background: var(--dashboard-surface, #fff);
    border: 1px solid var(--dashboard-border, #ebeef5);
    border-radius: 6px;
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    margin-top: 10px;
    font-size: 12px;
    color: var(--dashboard-text-muted, #909399);

    span {
      margin-right: 14px;
    }
  }

  &__arrow {
    position: absolute;
    top: 24px;
    right: 14px;
    color: var(--dashboard-text-muted, #909399);
  }

  &__pagination {
    display: flex;
    flex-shrink: 0;
    justify-content: flex-end;
    padding-top: 14px;
    border-top: 1px solid var(--dashboard-border-soft, #ebeef5);

    :deep(.el-pagination.is-background .el-pager li:not(.disabled).active) {
      color: var(--dashboard-on-accent, #fff);
      background-color: var(--dashboard-accent, #409eff);
    }

    :deep(.el-pagination.is-background .el-pager li:not(.disabled):hover) {
      color: var(--dashboard-accent, #409eff);
    }

    :deep(.el-pagination.is-background .el-pager li:not(.disabled).active:hover) {
      color: var(--dashboard-on-accent, #fff);
    }
  }
}
</style>

<style lang="scss">
.backend-message-dialog {
  max-width: 1120px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--dashboard-surface, #fff);

  .el-dialog__header {
    padding: 16px 20px;
    background: var(--dashboard-surface, #fff);
    border-bottom: 1px solid var(--dashboard-border, #ebeef5);
  }

  .el-dialog__title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dashboard-text-primary, #303133);
  }

  .el-dialog__headerbtn .el-dialog__close {
    color: var(--dashboard-text-muted, #909399);
  }

  .el-dialog__body {
    padding: 0 !important;
    overflow: hidden;
    background: var(--dashboard-surface, #fff);
  }
}
</style>
