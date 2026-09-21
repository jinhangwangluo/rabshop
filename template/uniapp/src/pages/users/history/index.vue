<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="history-page">
    <app-safe-top title="我的足迹" background="var(--surface-page)">
      <view
        slot="right"
        :class="['history-top-action', { 'history-top-action--disabled': !hasRecords }]"
        hover-class="history-top-action--hover"
        @click="handleToggleManage"
      >
        {{ manageButtonText }}
      </view>
    </app-safe-top>

    <view class="history-page__content">
      <view v-if="loading && !historyList.length" class="history-loading">
        <text class="iconfont icon-loading history-loading__icon"></text>
        <text class="history-loading__text">加载足迹中</text>
      </view>

      <block v-else-if="displayGroups.length">
        <view
          v-for="group in displayGroups"
          :key="group.key"
          class="history-section"
        >
          <view class="history-section__header">
            <view :class="['history-section__dot', { 'history-section__dot--accent': group.key === 'today' }]"></view>
            <text class="history-section__title">{{ group.label }}</text>
            <text class="history-section__count">{{ group.records.length }}条记录</text>
          </view>

          <view class="history-list">
            <view
              v-for="item in group.records"
              :key="item.id"
              :class="['history-card', { 'history-card--manage': isManaging }]"
              hover-class="history-card--hover"
              @click="handleItemClick(item)"
            >
              <view
                v-if="isManaging"
                class="history-card__check"
                hover-class="history-card__check--hover"
                @click.stop="handleToggleSelect(item.id)"
              >
                <text :class="['iconfont', item.selected ? 'icon-roundcheckfill' : 'icon-roundcheck', 'history-card__check-icon']"></text>
              </view>

              <view class="history-card__media">
                <image v-if="item.image" class="history-card__image" :src="item.image" mode="aspectFill"></image>
                <view v-else class="history-card__placeholder">
                  <text class="iconfont icon-pic history-card__placeholder-icon"></text>
                </view>
              </view>

              <view class="history-card__body">
                <view class="history-card__top">
                  <text class="history-card__badge">{{ item.badge }}</text>
                  <text class="history-card__time">{{ item.timeText }}</text>
                </view>
                <text class="history-card__title">{{ item.title }}</text>
                <text v-if="item.desc" class="history-card__desc">{{ item.desc }}</text>
                <view class="history-card__footer">
                  <text class="history-card__highlight">{{ item.highlight }}</text>
                  <text class="history-card__count">浏览 {{ item.browseCount }} 次</text>
                </view>
              </view>
            </view>
          </view>
        </view>

        <view v-if="loadingMore" class="history-page__more">
          <text class="history-page__more-text">加载更多足迹</text>
        </view>
        <view v-else-if="!hasMore" class="history-page__more">
          <text class="history-page__more-text">没有更多足迹了</text>
        </view>
      </block>

      <view v-else class="empty-state">
        <view class="empty-state__icon">
          <text class="iconfont icon-record empty-state__icon-text"></text>
        </view>
        <text class="empty-state__title">暂无浏览足迹</text>
        <text class="empty-state__desc">去首页逛逛，系统会自动为你记录最近浏览的内容、专题和商品。</text>
        <view class="empty-state__button" hover-class="empty-state__button--hover" @click="handleExplore">
          <text class="iconfont icon-home empty-state__button-icon"></text>
          <text class="empty-state__button-text">去首页逛逛</text>
        </view>
      </view>
    </view>

    <view v-if="isManaging && hasRecords" class="history-bottom-bar">
      <view class="history-bottom-bar__select" hover-class="history-bottom-bar__select--hover" @click="handleToggleSelectAll">
        <text :class="['iconfont', isAllSelected ? 'icon-roundcheckfill' : 'icon-roundcheck', 'history-bottom-bar__select-icon']"></text>
        <text class="history-bottom-bar__select-text">{{ isAllSelected ? '取消全选' : '全选' }}</text>
      </view>
      <view
        :class="['history-bottom-bar__delete', { 'history-bottom-bar__delete--disabled': !selectedCount || deleting }]"
        hover-class="history-bottom-bar__delete--hover"
        @click="handleDeleteSelected"
      >
        <text class="iconfont icon-delete history-bottom-bar__delete-icon"></text>
        <text class="history-bottom-bar__delete-text">{{ deleteButtonText }}</text>
      </view>
    </view>

    <view class="safe-bottom"></view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { deleteBrowseHistory, getBrowseHistoryList } from '@/api/history'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app'
import { themeState } from '@/utils/theme'

const GROUP_ORDER = ['today', 'yesterday', 'earlier']

function formatTimeText(value = '') {
  if (!value) return ''
  const timeParts = String(value).split(' ')
  return timeParts.length > 1 ? timeParts[1].slice(0, 5) : String(value)
}

function normalizeHistoryItem(item = {}, selectedIds = []) {
  const id = Number(item.id || 0)
  return {
    id,
    targetType: String(item.target_type || ''),
    targetId: Number(item.target_id || 0),
    title: String(item.title || '').trim() || '浏览记录',
    desc: String(item.desc || '').trim(),
    image: String(item.image || '').trim(),
    highlight: String(item.highlight || '').trim(),
    badge: String(item.badge || '').trim() || '足迹',
    detailUrl: String(item.detail_url || '').trim(),
    browseCount: Number(item.browse_count || 0),
    lastBrowseTime: String(item.last_browse_time || '').trim(),
    groupKey: String(item.group_key || 'earlier'),
    groupLabel: String(item.group_label || '更早'),
    timeText: formatTimeText(item.last_browse_time),
    selected: selectedIds.includes(id),
    raw: item
  }
}

defineOptions({ name: 'UserHistoryPage' })

const historyList = ref([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const loading = ref(false)
const loadingMore = ref(false)
const isManaging = ref(false)
const selectedIds = ref([])
const deleting = ref(false)

const hasRecords = computed(() => {
  return historyList.value.length > 0
})

const displayGroups = computed(() => {
  const groupMap = {}
  historyList.value.forEach((item) => {
    const key = item.groupKey || 'earlier'
    if (!groupMap[key]) {
      groupMap[key] = {
        key,
        label: item.groupLabel || '更早',
        records: []
      }
    }
    groupMap[key].records.push(item)
  })

  return GROUP_ORDER
    .map((key) => groupMap[key])
    .filter((group) => group && group.records.length > 0)
})

const hasMore = computed(() => {
  return historyList.value.length < total.value
})

const selectedCount = computed(() => {
  return selectedIds.value.length
})

const isAllSelected = computed(() => {
  return hasRecords.value && selectedCount.value === historyList.value.length
})

const manageButtonText = computed(() => {
  return isManaging.value ? '完成' : '管理'
})

const deleteButtonText = computed(() => {
  if (deleting.value) return '清除中'
  return selectedCount.value ? `清除选中(${selectedCount.value})` : '清除选中'
})

onLoad(() => {
  fetchHistoryList(true)
})

onPullDownRefresh(() => {
  fetchHistoryList(true).finally(() => {
    uni.stopPullDownRefresh()
  })
})

onReachBottom(() => {
  if (hasMore.value && !loading.value && !loadingMore.value) {
    fetchHistoryList(false)
  }
})

/**
 * 拉取浏览足迹列表。
 *
 * @param {boolean} reset 是否重置第一页。
 * @returns {Promise<void>}
 */
async function fetchHistoryList(reset = false) {
  const nextPage = reset ? 1 : page.value + 1
  if (reset) {
    loading.value = true
  } else {
    loadingMore.value = true
  }

  try {
    const res = await getBrowseHistoryList({
      page: nextPage,
      limit: limit.value
    })

    if (!res || res.code !== 200 || !res.data) {
      showToast((res && res.msg) || '足迹加载失败')
      return
    }

    const list = Array.isArray(res.data.list)
      ? res.data.list.map((item) => normalizeHistoryItem(item, selectedIds.value))
      : []
    historyList.value = reset ? list : historyList.value.concat(list)
    page.value = Number(res.data.page || nextPage)
    total.value = Number(res.data.total || 0)
    syncSelectedIds()
  } catch (error) {
    showToast('足迹加载失败，请稍后重试')
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

/**
 * 切换管理模式。
 *
 * @returns {void}
 */
function handleToggleManage() {
  if (!hasRecords.value) return
  isManaging.value = !isManaging.value
  if (!isManaging.value) {
    selectedIds.value = []
    historyList.value = historyList.value.map((item) => ({ ...item, selected: false }))
  }
}

/**
 * 切换单条记录选中状态。
 *
 * @param {number} id 浏览记录 ID。
 * @returns {void}
 */
function handleToggleSelect(id) {
  const recordId = Number(id || 0)
  if (!recordId) return

  const selected = selectedIds.value.includes(recordId)
  selectedIds.value = selected
    ? selectedIds.value.filter((item) => item !== recordId)
    : selectedIds.value.concat(recordId)
  historyList.value = historyList.value.map((item) => ({
    ...item,
    selected: selectedIds.value.includes(item.id)
  }))
}

/**
 * 切换全选。
 *
 * @returns {void}
 */
function handleToggleSelectAll() {
  selectedIds.value = isAllSelected.value ? [] : historyList.value.map((item) => item.id)
  historyList.value = historyList.value.map((item) => ({
    ...item,
    selected: selectedIds.value.includes(item.id)
  }))
}

/**
 * 删除选中的浏览足迹。
 *
 * @returns {void}
 */
function handleDeleteSelected() {
  if (!selectedCount.value || deleting.value) return

  uni.showModal({
    title: '清除足迹',
    content: '确认清除选中的 ' + selectedCount.value + ' 条足迹吗？',
    confirmColor: themeState.primary_color,
    success: (result) => {
      if (result.confirm) {
        confirmDeleteSelected()
      }
    }
  })
}

/**
 * 确认删除选中记录。
 *
 * @returns {Promise<void>}
 */
async function confirmDeleteSelected() {
  deleting.value = true
  try {
    const ids = selectedIds.value.slice()
    const res = await deleteBrowseHistory({ ids })
    if (!res || res.code !== 200) {
      showToast((res && res.msg) || '清除失败')
      return
    }

    historyList.value = historyList.value.filter((item) => !ids.includes(item.id))
    total.value = Math.max(0, total.value - ids.length)
    selectedIds.value = []
    if (!historyList.value.length) {
      isManaging.value = false
    }
    showToast('已清除')
  } catch (error) {
    showToast('清除失败，请稍后重试')
  } finally {
    deleting.value = false
  }
}

/**
 * 点击足迹卡片。
 *
 * @param {Object} item 当前点击的足迹项。
 * @returns {void}
 */
function handleItemClick(item = {}) {
  if (isManaging.value) {
    handleToggleSelect(item.id)
    return
  }

  if (item.targetType === 'product') {
    showToast('商品详情暂不可用')
    return
  }

  if (!item.detailUrl) {
    showToast('详情暂不可用')
    return
  }

  uni.navigateTo({
    url: item.detailUrl,
    fail: () => {
      showToast('详情暂不可用')
    }
  })
}

/**
 * 引导用户回首页继续浏览。
 *
 * @returns {void}
 */
function handleExplore() {
  uni.switchTab({
    url: '/pages/index/index',
    fail: () => {
      showToast('首页暂时不可用')
    }
  })
}

/**
 * 同步选中状态，移除已不存在的 ID。
 *
 * @returns {void}
 */
function syncSelectedIds() {
  const currentIds = historyList.value.map((item) => item.id)
  selectedIds.value = selectedIds.value.filter((id) => currentIds.includes(id))
  historyList.value = historyList.value.map((item) => ({
    ...item,
    selected: selectedIds.value.includes(item.id)
  }))
}

/**
 * 统一展示轻提示。
 *
 * @param {string} title 提示文案。
 * @returns {void}
 */
function showToast(title) {
  uni.showToast({
    title,
    icon: 'none'
  })
}
</script>

<style lang="scss">
page {
  background: var(--surface-page);
}
</style>

<style lang="scss" scoped>
.history-page {
  min-height: 100%;
  background: var(--surface-page);
}

.history-top-action {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  min-width: 88rpx;
  min-height: 64rpx;
  font-size: 28rpx;
  line-height: 1.2;
  text-align: right;
  color: var(--brand-primary);

  &--hover {
    opacity: 0.72;
  }

  &--disabled {
    color: var(--text-disabled);
  }
}

.history-page__content {
  padding: 24rpx 32rpx 0;
  box-sizing: border-box;
}

.history-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 320rpx;
  border-radius: 24rpx;
  background: var(--surface-card);

  &__icon {
    margin-right: 12rpx;
    font-size: 30rpx;
    line-height: 1;
    color: var(--brand-primary);
  }

  &__text {
    font-size: 24rpx;
    line-height: 1.4;
    color: var(--text-secondary);
  }
}

.history-section + .history-section {
  margin-top: 32rpx;
}

.history-section__header {
  display: flex;
  align-items: center;
}

.history-section__dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: var(--text-disabled);

  &--accent {
    background: var(--brand-primary);
  }
}

.history-section__title {
  margin-left: 16rpx;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 1.3;
  color: var(--text-title);
}

.history-section__count {
  margin-left: auto;
  font-size: 20rpx;
  font-weight: 500;
  line-height: 1.2;
  color: var(--text-secondary);
}

.history-list {
  margin-top: 20rpx;
}

.history-card + .history-card {
  margin-top: 20rpx;
}

.history-card {
  display: flex;
  align-items: center;
  padding: 20rpx;
  border-radius: 24rpx;
  background: var(--surface-card);
  box-sizing: border-box;
  box-shadow: 0 4rpx 12rpx var(--shadow-card);
}

.history-card--hover {
  opacity: 0.96;
}

.history-card__check {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52rpx;
  min-height: 156rpx;
  margin-right: 14rpx;

  &--hover {
    opacity: 0.8;
  }
}

.history-card__check-icon {
  font-size: 34rpx;
  line-height: 1;
  color: var(--brand-primary);
}

.history-card__media {
  flex-shrink: 0;
  width: 156rpx;
  height: 156rpx;
  border-radius: 20rpx;
  overflow: hidden;
  background: var(--surface-card-soft);
}

.history-card__image,
.history-card__placeholder {
  width: 156rpx;
  height: 156rpx;
}

.history-card__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
}

.history-card__placeholder-icon {
  font-size: 42rpx;
  line-height: 1;
  color: var(--text-disabled);
}

.history-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 22rpx;
}

.history-card__top {
  display: flex;
  align-items: center;
  min-width: 0;
}

.history-card__badge {
  flex-shrink: 0;
  margin-right: 12rpx;
  padding: 6rpx 14rpx;
  border-radius: 8rpx;
  background: var(--brand-primary-pale);
  font-size: 20rpx;
  font-weight: 700;
  line-height: 1;
  color: var(--brand-primary);
}

.history-card__time {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 22rpx;
  line-height: 1.2;
  color: var(--text-secondary);
}

.history-card__title {
  display: block;
  margin-top: 12rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 28rpx;
  font-weight: 700;
  line-height: 1.4;
  color: var(--text-title);
}

.history-card__desc {
  display: block;
  margin-top: 6rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 22rpx;
  line-height: 1.4;
  color: var(--text-secondary);
}

.history-card__footer {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-top: 14rpx;
}

.history-card__highlight {
  font-size: 30rpx;
  font-weight: 700;
  line-height: 1;
  color: var(--brand-primary);
}

.history-card__count {
  flex-shrink: 0;
  margin-left: 16rpx;
  font-size: 20rpx;
  line-height: 1.2;
  color: var(--text-secondary);
}

.history-page__more {
  padding: 28rpx 0 40rpx;
  text-align: center;
}

.history-page__more-text {
  font-size: 24rpx;
  line-height: 1.4;
  color: var(--text-placeholder);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 120rpx;
  padding: 64rpx 40rpx;
  border-radius: 24rpx;
  background: var(--surface-card);
  border: 2rpx solid var(--border-light);
  box-sizing: border-box;

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 112rpx;
    height: 112rpx;
    border-radius: 56rpx;
    background: var(--surface-card-soft);
  }

  &__icon-text {
    font-size: 52rpx;
    line-height: 1;
    color: var(--text-disabled);
  }

  &__title {
    margin-top: 28rpx;
    font-size: 34rpx;
    font-weight: 700;
    line-height: 1.3;
    color: var(--text-title);
  }

  &__desc {
    margin-top: 16rpx;
    font-size: 24rpx;
    line-height: 1.6;
    text-align: center;
    color: var(--text-secondary);
  }

  &__button {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 280rpx;
    height: 80rpx;
    margin-top: 36rpx;
    padding: 0 32rpx;
    border-radius: 999rpx;
    background: var(--brand-primary);
    color: var(--text-inverse);
  }

  &__button--hover {
    opacity: 0.88;
  }

  &__button-icon {
    margin-right: 10rpx;
    font-size: 26rpx;
    line-height: 1;
  }

  &__button-text {
    font-size: 26rpx;
    font-weight: 700;
    line-height: 1;
  }
}

.history-bottom-bar {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 32rpx 24rpx;
  background: var(--surface-card);
  box-shadow: 0 -8rpx 24rpx var(--shadow-soft);
  box-sizing: border-box;

  &__select {
    display: flex;
    align-items: center;
    min-height: 72rpx;
  }

  &__select--hover {
    opacity: 0.82;
  }

  &__select-icon {
    margin-right: 12rpx;
    font-size: 34rpx;
    line-height: 1;
    color: var(--brand-primary);
  }

  &__select-text {
    font-size: 26rpx;
    line-height: 1.2;
    color: var(--text-title);
  }

  &__delete {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 220rpx;
    height: 72rpx;
    padding: 0 28rpx;
    border-radius: 999rpx;
    background: var(--brand-primary);
    color: var(--text-inverse);
    box-sizing: border-box;
  }

  &__delete--disabled {
    background: var(--surface-card-soft);
    color: var(--text-disabled);
  }

  &__delete--hover {
    opacity: 0.88;
  }

  &__delete-icon {
    margin-right: 8rpx;
    font-size: 26rpx;
    line-height: 1;
  }

  &__delete-text {
    font-size: 26rpx;
    font-weight: 700;
    line-height: 1;
  }
}

.safe-bottom {
  height: 32rpx;
}

/* #ifdef H5 */
.history-bottom-bar {
  bottom: var(--window-bottom, 0px);
}

.safe-bottom {
  height: calc(132rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.history-bottom-bar {
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
}

.safe-bottom {
  height: calc(132rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
