<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="collect-page">

    <app-safe-top
      title="我的收藏"
      background="#fff"
      show-back
      emit-back
      @back="handleBack"
    />


    <view class="collect-page__header">
      <view class="collect-page__tabs">
        <view
          v-for="tab in tabs"
          :key="tab.key"
          class="collect-page__tab"
          @click="handleTabChange(tab.key)"
        >
          <text :class="['collect-page__tab-text', { 'collect-page__tab-text--active': activeTab === tab.key }]">
            {{ tab.label }}
          </text>
          <view v-if="activeTab === tab.key" class="collect-page__tab-line"></view>
          <view v-else class="collect-page__tab-line collect-page__tab-line--placeholder"></view>
        </view>
      </view>
    </view>

    <view class="collect-page__content">
      <view v-if="loading && !displayItems.length" class="collect-page__loading">
        <uni-load-more status="loading" :content-text="loadMoreText"></uni-load-more>
      </view>

      <block v-else-if="displayItems.length">
        <view class="collect-list">
          <view
            v-for="item in displayItems"
            :key="item.id"
            class="collect-card"
            hover-class="collect-card--hover"
            @click="handleCardClick(item)"
          >
            <image class="collect-card__image" :src="item.image" mode="aspectFill"></image>
            <view class="collect-card__body">
              <text class="collect-card__title">{{ item.title }}</text>
              <text class="collect-card__desc">{{ item.desc }}</text>
              <view class="collect-card__footer">
                <view class="collect-card__meta">
                  <text class="collect-card__highlight">{{ item.highlight }}</text>
                  <text v-if="item.badge" class="collect-card__badge">{{ item.badge }}</text>
                </view>
                <view class="collect-card__action" hover-class="collect-card__action--hover" @click.stop="handleToggleFavorite(item)">
                  <uni-icons type="heart" size="22" :color="themePrimaryColor"></uni-icons>
                </view>
              </view>
            </view>
          </view>
        </view>

        <uni-load-more v-if="hasMore || loadingMore" :status="loadMoreStatus" :content-text="loadMoreText"></uni-load-more>
      </block>

      <view v-else class="empty-state">
        <image class="empty-state__image" :src="emptyImage" mode="aspectFit"></image>
      </view>

      <view class="collect-page__recommend">
        <guess-you-like title="猜你喜欢" :limit="4" />
      </view>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import GuessYouLike from '@/components/guess-you-like/index.vue'
import { cancelFavorite, getFavoriteList } from '@/api/favorite'
import { getStatusImageUrl } from '@/utils/status_images'
import { computed, ref } from 'vue'
import { onLoad, onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'
import { themeState } from '@/utils/theme'

function getTabs() {
  return [
    { key: 'content', label: '内容' },
    { key: 'special', label: '专题' }
  ]
}

function normalizeFavoriteItem(item) {
  const targetType = String(item.target_type || '').trim()
  const targetId = Number(item.target_id || 0)

  return {
    id: String(item.id || `${targetType}-${targetId}`),
    favoriteId: Number(item.favorite_id || 0),
    targetType,
    targetId,
    title: String(item.title || '').trim(),
    desc: String(item.desc || '').trim(),
    highlight: String(item.highlight || '').trim(),
    badge: String(item.badge || '').trim(),
    image: String(item.image || '').trim(),
    detailUrl: String(item.detail_url || '').trim()
  }
}

defineOptions({ name: 'UserCollectPage' })

const activeTab = ref('content')
const tabs = ref(getTabs())
const collectMap = ref({
  content: [],
  special: []
})
const pageMap = ref({
  content: 1,
  special: 1
})
const totalMap = ref({
  content: 0,
  special: 0
})
const emptyImage = ref(getStatusImageUrl('no_collection.png'))
const limit = ref(10)
const loading = ref(false)
const loadingMore = ref(false)
const loadMoreText = ref({
  contentdown: '上拉加载更多',
  contentrefresh: '加载中...',
  contentnomore: '没有更多收藏了'
})

/**
 * 当前 tab 的展示列表。
 *
 * @returns {Array<Object>} 当前激活分栏的收藏数据。
 */
const displayItems = computed(() => {
  return Array.isArray(collectMap.value[activeTab.value]) ? collectMap.value[activeTab.value] : []
})

/**
 * 当前分栏是否还有下一页。
 *
 * @returns {boolean}
 */
const hasMore = computed(() => {
  return displayItems.value.length < Number(totalMap.value[activeTab.value] || 0)
})

/**
 * 分页加载状态。
 *
 * @returns {string}
 */
const loadMoreStatus = computed(() => {
  return loadingMore.value ? 'loading' : (hasMore.value ? 'more' : 'noMore')
})

onLoad((options = {}) => {
  const initialTab = options.tab === 'topic' ? 'special' : options.tab
  if (initialTab && tabs.value.some((item) => item.key === initialTab)) {
    activeTab.value = initialTab
  }

  fetchCollectList(true)
})

onShow(() => {
  fetchCollectList(true)
})

onPullDownRefresh(() => {
  fetchCollectList(true).finally(() => {
    uni.stopPullDownRefresh()
  })
})

onReachBottom(() => {
  if (hasMore.value && !loading.value && !loadingMore.value) {
    fetchCollectList(false)
  }
})

/**
 * 拉取收藏列表。
 *
 * @param {boolean} reset 是否重置为第一页。
 * @returns {Promise<void>}
 */
async function fetchCollectList(reset = false) {
  const type = activeTab.value
  const nextPage = reset ? 1 : Number(pageMap.value[type] || 1) + 1

  if (reset) {
    loading.value = true
  } else {
    loadingMore.value = true
  }

  try {
    const res = await getFavoriteList({
      type,
      page: nextPage,
      limit: limit.value
    })

    if (!res || res.code !== 200 || !res.data) {
      return
    }

    const list = Array.isArray(res.data.list) ? res.data.list.map(normalizeFavoriteItem) : []
    collectMap.value = {
      ...collectMap.value,
      [type]: reset ? list : collectMap.value[type].concat(list)
    }
    pageMap.value = {
      ...pageMap.value,
      [type]: Number(res.data.page || nextPage)
    }
    totalMap.value = {
      ...totalMap.value,
      [type]: Number(res.data.total || 0)
    }
  } catch (error) {
    console.error('[collect] fetchCollectList failed', error)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

/**
 * 切换收藏分栏。
 *
 * @param {string} tabKey 分栏 key。
 * @returns {void}
 */
function handleTabChange(tabKey) {
  if (!tabs.value.some((item) => item.key === tabKey) || activeTab.value === tabKey) {
    return
  }

  activeTab.value = tabKey
  fetchCollectList(true)
}

/**
 * 返回上一页；若没有历史栈，则回到“我的” tab。
 *
 * @returns {void}
 */
function handleBack() {
  const pageStack = getCurrentPages()

  if (Array.isArray(pageStack) && pageStack.length > 1) {
    uni.navigateBack({
      fail: () => {
        switchToUserTab()
      }
    })
    return
  }

  switchToUserTab()
}

/**
 * 点击收藏卡片后的统一处理。
 *
 * @param {Object} item 当前收藏项。
 * @returns {void}
 */
function handleCardClick(item = {}) {
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
 * 触发取消收藏确认。
 *
 * @param {Object} item 当前收藏项。
 * @returns {void}
 */
function handleToggleFavorite(item = {}) {
  uni.showModal({
    title: '取消收藏',
    content: `确认移除“${item.title || '当前内容'}”吗？`,
    confirmColor: themeState.primary_color,
    success: (result) => {
      if (result.confirm) {
        confirmRemoveItem(item)
      }
    }
  })
}

/**
 * 执行取消收藏并更新当前列表。
 *
 * @param {Object} item 当前收藏项。
 * @returns {Promise<void>}
 */
async function confirmRemoveItem(item = {}) {
  if (!item.targetType || !item.targetId) {
    showToast('收藏信息不完整')
    return
  }

  try {
    const res = await cancelFavorite({
      type: item.targetType,
      target_id: item.targetId
    })

    if (!res || res.code !== 200) {
      return
    }

    const currentList = Array.isArray(collectMap.value[activeTab.value]) ? collectMap.value[activeTab.value] : []
    collectMap.value = {
      ...collectMap.value,
      [activeTab.value]: currentList.filter((currentItem) => currentItem.id !== item.id)
    }
    totalMap.value = {
      ...totalMap.value,
      [activeTab.value]: Math.max(0, Number(totalMap.value[activeTab.value] || 0) - 1)
    }
    showToast('已取消收藏')
  } catch (error) {
    console.error('[collect] cancelFavorite failed', error)
  }
}

/**
 * 兜底切回个人中心 tab。
 *
 * @returns {void}
 */
function switchToUserTab() {
  uni.switchTab({
    url: '/pages/user/index',
    fail: () => {
      uni.reLaunch({
        url: '/pages/user/index',
        fail: () => {
          showToast('个人中心暂不可用')
        }
      })
    }
  })
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
  background: #f5f5f5;
}
</style>

<style lang="scss" scoped>
.collect-page {
  min-height: 100%;
  background: #f5f5f5;
}

.collect-page__header {
  padding: 24rpx 32rpx 10rpx;
  background: #f5f5f5;
}

.collect-page__tabs {
  display: flex;
  align-items: flex-end;
  padding: 16rpx 32rpx 0;
}

.collect-page__tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.collect-page__tab-text {
  font-size: 28rpx;
  font-weight: 500;
  line-height: 40rpx;
  color: #7f7f85;

  &--active {
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
  }
}

.collect-page__tab-line {
  width: 72rpx;
  height: 4rpx;
  margin-top: 12rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
}

.collect-page__tab-line--placeholder {
  background: transparent;
}

.collect-page__content {
  padding: 12rpx 24rpx 64rpx;
}

.collect-page__loading {
  padding: 80rpx 0;
}

.collect-page__recommend {
  margin-top: 42rpx;
}

.collect-card {
  display: flex;
  align-items: center;
  padding: 24rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 4rpx 12rpx rgba(15, 23, 42, 0.03);

  & + & {
    margin-top: 24rpx;
  }
}

.collect-card--hover {
  opacity: 0.94;
}

.collect-card__image {
  flex-shrink: 0;
  width: 160rpx;
  height: 160rpx;
  border-radius: 24rpx;
  background: #eceeef;
}

.collect-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 24rpx;
}

.collect-card__title {
  display: block;
  overflow: hidden;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 48rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-title, #1a1c1c);
}

.collect-card__desc {
  display: block;
  margin-top: 10rpx;
  overflow: hidden;
  font-size: 28rpx;
  line-height: 40rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-secondary, #7f7f85);
}

.collect-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 18rpx;
}

.collect-card__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.collect-card__highlight {
  font-size: 36rpx;
  font-weight: 700;
  line-height: 1;
  color: var(--brand-primary, #ea580c);
}

.collect-card__badge {
  margin-left: 12rpx;
  padding: 4rpx 10rpx;
  border-radius: 10rpx;
  font-size: 18rpx;
  font-weight: 700;
  line-height: 1.2;
  color: var(--brand-primary, #ea580c);
  background: #fff1e8;
}

.collect-card__action {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
}

.collect-card__action--hover {
  background: var(--brand-primary-pale, #fff7ed);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 420rpx;
}

.empty-state__image {
  width: 240rpx;
}
</style>
