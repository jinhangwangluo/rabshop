<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="follow-page">
    <app-safe-top
      title="关注"
      background="var(--surface-card, #ffffff)"
      emit-back
      @back="handleBack"
    >
    </app-safe-top>

    <view class="tab-panel">
      <view
        class="tab-card"
        :class="{ 'tab-card--active': activeTab === 'shop' }"
        hover-class="tab-card--hover"
        @click="handleTabChange('shop')"
      >
        <uni-icons type="shop" size="24" :color="shopTabIconColor"></uni-icons>
        <text class="tab-card__text">店铺</text>
      </view>
      <view
        class="tab-card tab-card--last"
        :class="{ 'tab-card--active': activeTab === 'live' }"
        hover-class="tab-card--hover"
        @click="handleTabChange('live')"
      >
        <uni-icons type="person" size="25" :color="liveTabIconColor"></uni-icons>
        <text class="tab-card__text">主播</text>
      </view>
    </view>

    <view class="follow-content">
      <view v-if="activeTab === 'shop'" id="shop-section" class="follow-section">
        <view class="section-header">
          <view class="section-title">
            <view class="section-title__icon section-title__icon--shop">
              <uni-icons type="shop" size="19" :color="textInverseColor"></uni-icons>
            </view>
            <text class="section-title__text">全部店铺（{{ shopCount }}）</text>
          </view>
          <view class="section-sort" hover-class="section-sort--hover" @click="handleSort('shop')">
            <text class="section-sort__text">{{ shopSortText }}</text>
            <uni-icons type="bottom" size="12" :color="textSubtitleColor"></uni-icons>
          </view>
        </view>

        <view v-if="loading" class="loading-card">
          <text class="loading-card__text">加载中...</text>
        </view>

        <block v-else>
          <view
            v-if="platformCard"
            class="follow-card platform-card"
            hover-class="platform-card--hover"
            @click="handleOpenPlatform(platformCard)"
          >
            <view class="avatar-circle" :class="platformCard.logoClass">
              <image v-if="platformCard.logo" class="avatar-circle__image" :src="platformCard.logo" mode="aspectFill"></image>
              <block v-else>
                <text class="avatar-circle__main">{{ platformCard.logoText }}</text>
              </block>
            </view>

            <view class="platform-card__body">
              <view class="platform-card__top">
                <text class="item-title platform-card__name">{{ platformCard.name }}</text>
                <text class="item-tag platform-card__tag">{{ platformCard.tag }}</text>
              </view>
              <view class="item-meta">
                <text class="item-category">{{ platformCard.category }}</text>
                <text v-if="platformCard.followText" class="platform-card__follow">{{ platformCard.followText }}</text>
              </view>
              <text v-if="platformCard.description" class="platform-card__desc">{{ platformCard.description }}</text>
              <text v-else-if="platformCard.updateText" class="platform-card__desc">{{ platformCard.updateText }}</text>
            </view>

            <view class="card-actions">
              <view
                class="outline-button outline-button--platform"
                hover-class="outline-button--hover"
                @click.stop="handleOpenPlatform(platformCard)"
              >
                <text class="outline-button__text">进入</text>
              </view>
              <view
                class="plain-button plain-button--platform"
                hover-class="plain-button--hover"
                @click.stop="handleCancelFollow('platform', platformCard)"
              >
                <text class="plain-button__text">取消关注</text>
              </view>
            </view>
          </view>

          <view v-if="shopCount" class="shop-list">
            <view v-for="item in sortedShops" :key="item.id" class="follow-card shop-card">
              <view class="avatar-circle" :class="item.logoClass">
                <image v-if="item.logo" class="avatar-circle__image" :src="item.logo" mode="aspectFill"></image>
                <block v-else>
                  <text class="avatar-circle__main">{{ item.logoText }}</text>
                  <text v-if="item.logoSubText" class="avatar-circle__sub">{{ item.logoSubText }}</text>
                </block>
              </view>

              <view class="shop-card__body">
                <text class="item-title">{{ item.name }}</text>
                <view class="item-meta">
                  <text class="item-category">{{ item.category }}</text>
                  <text class="item-tag">{{ item.tag }}</text>
                </view>
                <view class="shop-update">
                  <image
                    v-for="image in item.images"
                    :key="image"
                    class="shop-update__image"
                    :src="image"
                    mode="aspectFill"
                  ></image>
                  <view class="shop-update__text">
                    <text class="shop-update__headline">{{ item.updateTitle }}</text>
                    <text class="shop-update__time">{{ item.updateTime }}</text>
                  </view>
                </view>
              </view>

              <view class="card-actions">
                <view class="outline-button outline-button--shop" hover-class="outline-button--hover" @click="handleOpenShop(item)">
                  <text class="outline-button__text">进店</text>
                </view>
                <view class="plain-button" hover-class="plain-button--hover" @click="handleCancelFollow('shop', item)">
                  <text class="plain-button__text">取消关注</text>
                </view>
              </view>
            </view>
          </view>

          <view v-else-if="!platformCard" class="guide-card">
            <image class="guide-card__image guide-card__image--shop" src="./../static/follow_live.png" mode="aspectFit"></image>
            <view class="guide-card__body">
              <text class="guide-card__title">你还没有关注任何店铺哦</text>
              <text class="guide-card__desc">关注喜欢的店铺，及时获取上新动态</text>
              <view class="guide-card__button guide-card__button--shop" hover-class="guide-card__button--hover" @click="handleDiscoverShop">
                <text class="guide-card__button-text">去逛逛，发现好店</text>
              </view>
            </view>
          </view>
        </block>
      </view>

      <view v-if="activeTab === 'live'" id="live-section" class="follow-section follow-section--live">
        <view class="section-header">
          <view class="section-title">
            <view class="section-title__icon section-title__icon--live">
              <uni-icons type="person-filled" size="19" :color="textInverseColor"></uni-icons>
            </view>
            <text class="section-title__text">全部主播（{{ liveCount }}）</text>
          </view>
          <view class="section-sort" hover-class="section-sort--hover" @click="handleSort('live')">
            <text class="section-sort__text">{{ liveSortText }}</text>
            <uni-icons type="bottom" size="12" :color="textSubtitleColor"></uni-icons>
          </view>
        </view>

        <view v-if="loading" class="loading-card">
          <text class="loading-card__text">加载中...</text>
        </view>

        <view v-else-if="liveCount" class="live-list">
          <view v-for="item in sortedLives" :key="item.id" class="follow-card live-card">
            <view class="live-avatar" :class="item.avatarClass">
              <image v-if="item.avatar" class="live-avatar__image" :src="item.avatar" mode="aspectFill"></image>
              <text v-else class="live-avatar__text">{{ item.avatarText }}</text>
            </view>

            <view class="live-card__body">
              <view class="live-name-row">
                <text class="item-title">{{ item.name }}</text>
                <text class="live-badge" :class="{ 'live-badge--pink': item.badgeType === 'pink' }">{{ item.badge }}</text>
              </view>
              <text class="live-fans">粉丝 {{ item.fans }}</text>
              <text v-if="item.topic" class="live-topic">{{ item.topic }}</text>
              <view class="live-watching">
                <text class="live-watching__fire">🔥</text>
                <text class="live-watching__text">{{ item.watching }}</text>
              </view>
            </view>

            <view class="card-actions">
              <view class="filled-button" hover-class="filled-button--hover" @click="handleWatchLive(item)">
                <text class="filled-button__text">去看</text>
              </view>
              <view class="plain-button" hover-class="plain-button--hover" @click="handleCancelFollow('live', item)">
                <text class="plain-button__text">取消关注</text>
              </view>
            </view>
          </view>
        </view>

        <view v-else class="guide-card guide-card--live">
          <image class="guide-card__image guide-card__image--live" src="./../static/follow_shop.png" mode="aspectFit"></image>
          <view class="guide-card__body">
            <text class="guide-card__title">你还没有关注任何主播哦</text>
            <text class="guide-card__desc">关注喜欢的主播，不错过每一场精彩直播</text>
            <view class="guide-card__button guide-card__button--live" hover-class="guide-card__button--hover" @click="handleDiscoverLive">
              <text class="guide-card__button-text">去发现热门主播</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { cancelUserFollow, getUserFollowList } from '@/api/user'
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { themeState } from '@/utils/theme'

const SHOP_AVATAR_CLASSES = ['avatar-circle--nala', 'avatar-circle--ur', 'avatar-circle--green']
const LIVE_AVATAR_CLASSES = ['live-avatar--rose', 'live-avatar--blue']
const TAB_BAR_PATHS = ['/pages/index/index', '/pages/category/index', '/pages/cart/index', '/pages/user/index']

defineOptions({ name: 'UserFollowPage' })

const activeTab = ref('shop')
const brandColor = computed(() => themeState.primary_color)
const liveColor = '#f20b56'
const textTitleColor = 'var(--text-title, #18181b)'
const textSubtitleColor = 'var(--text-subtitle, #52525b)'
const textSecondaryColor = 'var(--text-secondary, #71717a)'
const textInverseColor = 'var(--text-inverse, #ffffff)'
const loading = ref(false)
const shopSortOrder = ref('desc')
const liveSortOrder = ref('desc')
const platformCard = ref(null)
const shops = ref([])
const lives = ref([])

/**
 * 店铺关注数量。
 *
 * @returns {number} 当前店铺列表数量。
 */
const shopCount = computed(() => {
  return shops.value.length
})

/**
 * 主播关注数量。
 *
 * @returns {number} 当前主播列表数量。
 */
const liveCount = computed(() => {
  return lives.value.length
})

/**
 * 店铺 Tab 图标颜色。
 *
 * @returns {string} 当前图标颜色。
 */
const shopTabIconColor = computed(() => {
  return activeTab.value === 'shop' ? brandColor.value : textTitleColor
})

/**
 * 主播 Tab 图标颜色。
 *
 * @returns {string} 当前图标颜色。
 */
const liveTabIconColor = computed(() => {
  return activeTab.value === 'live' ? liveColor : textTitleColor
})

/**
 * 店铺排序文案。
 *
 * @returns {string} 当前店铺排序说明。
 */
const shopSortText = computed(() => {
  return shopSortOrder.value === 'asc' ? '最早动态' : '最新动态'
})

/**
 * 主播排序文案。
 *
 * @returns {string} 当前主播排序说明。
 */
const liveSortText = computed(() => {
  return liveSortOrder.value === 'asc' ? '最早开播' : '最近开播'
})

/**
 * 店铺排序后的列表。
 *
 * @returns {Array} 页面可直接渲染的店铺列表。
 */
const sortedShops = computed(() => {
  return sortShopList(shops.value, shopSortOrder.value)
})

/**
 * 主播排序后的列表。
 *
 * @returns {Array} 页面可直接渲染的主播列表。
 */
const sortedLives = computed(() => {
  return sortLiveList(lives.value, liveSortOrder.value)
})

onLoad(() => {
  loadFollowList(activeTab.value)
})

/**
 * 按当前 Tab 加载关注列表。
 *
 * @param {string} type 关注类型：shop/live。
 * @returns {Promise<void>}
 */
async function loadFollowList(type = 'shop') {
  if (type !== 'shop' && type !== 'live') {
    return
  }

  loading.value = true
  try {
    const res = await getUserFollowList({
      type,
      limit: 20
    }, {
      showError: false
    })

    if (!res || res.code !== 200 || !res.data) {
      clearFollowList(type)
      showToast(res && res.msg ? res.msg : '关注列表加载失败')
      return
    }

    const data = res.data || {}
    platformCard.value = normalizePlatformCard(data.platforms)

    if (type === 'shop') {
      const list = Array.isArray(data.shops) ? data.shops : (Array.isArray(data.list) ? data.list : [])
      shops.value = normalizeShopList(list)
      return
    }

    const list = Array.isArray(data.lives) ? data.lives : (Array.isArray(data.list) ? data.list : [])
    lives.value = normalizeLiveList(list)
  } catch (error) {
    clearFollowList(type)
    showToast('关注列表加载失败')
  } finally {
    loading.value = false
  }
}

/**
 * 清空指定类型列表。
 *
 * @param {string} type 关注类型：shop/live。
 * @returns {void}
 */
function clearFollowList(type) {
  if (type === 'shop') {
    platformCard.value = null
    shops.value = []
    return
  }

  lives.value = []
}

/**
 * 规范化平台首卡。
 *
 * @param {Array|Object} list 接口返回的平台列表或单项。
 * @returns {Object|null} 页面可直接渲染的平台卡片。
 */
function normalizePlatformCard(list = []) {
  const item = Array.isArray(list) ? list[0] : list
  if (!item || typeof item !== 'object') {
    return null
  }

  const name = String(item.name || '平台').trim()
  const followCount = Number(item.follow_count || 0)
  const routeUrl = String(item.route_url || item.routeUrl || '').trim()

  return {
    id: Number(item.id || item.favorite_id || item.target_id || 0),
    targetId: Number(item.target_id || item.id || 0),
    merchantId: Number(item.merchant_id || 0),
    name,
    category: String(item.category || '平台').trim(),
    tag: String(item.tag || '已关注').trim(),
    followText: String(item.follow_count_text || (item.follow_count !== undefined ? `${followCount}粉丝` : '')).trim(),
    description: String(item.description || '').trim(),
    logo: String(item.logo || item.avatar || '').trim(),
    logoText: getAvatarText(name, '平'),
    logoClass: 'avatar-circle--platform',
    routeUrl,
    updateText: formatPlatformTime(item.update_time || item.create_time, '暂无更新时间'),
    favoriteUpdateTime: String(item.update_time || '').trim(),
    favoriteUpdateTimeStamp: parseFollowTimestamp(item.update_time || item.create_time),
    createTime: String(item.create_time || '').trim(),
    updateTime: String(item.update_time || '').trim()
  }
}

/**
 * 规范化店铺关注列表。
 *
 * @param {Array} list 接口返回店铺列表。
 * @returns {Array} 页面可直接渲染的店铺列表。
 */
function normalizeShopList(list = []) {
  return list.map((item, index) => {
    const merchantId = Number(item.merchant_id || item.target_id || item.id || 0)
    const name = String(item.name || '店铺').trim()
    const specials = Array.isArray(item.new_specials) ? item.new_specials : []
    const images = specials
      .map((special) => String((special && special.cover) || '').trim())
      .filter((image) => image)
      .slice(0, 3)

    return {
      id: merchantId,
      targetId: merchantId,
      merchantId,
      name,
      category: String(item.category || '精选店铺').trim(),
      tag: String(item.tag || item.business_status_text || '已关注').trim(),
      logo: String(item.logo || item.avatar || '').trim(),
      logoText: getAvatarText(name, '店'),
      logoSubText: '',
      logoClass: SHOP_AVATAR_CLASSES[index % SHOP_AVATAR_CLASSES.length],
      images,
      updateTitle: String(item.new_summary || '暂无新品专题').trim(),
      updateTime: formatFollowTime(item.latest_new_time, '暂无新品动态'),
      latestNewTime: String(item.latest_new_time || '').trim(),
      latestNewTimeStamp: parseFollowTimestamp(item.latest_new_time || item.update_time || item.create_time),
      favoriteUpdateTime: String(item.update_time || '').trim(),
      favoriteUpdateTimeStamp: parseFollowTimestamp(item.update_time || item.create_time),
      newCount: Number(item.new_count || 0),
      routeUrl: String(item.route_url || '').trim(),
      newSpecials: specials
    }
  })
}

/**
 * 规范化主播关注列表。
 *
 * @param {Array} list 接口返回主播列表。
 * @returns {Array} 页面可直接渲染的主播列表。
 */
function normalizeLiveList(list = []) {
  return list.map((item, index) => {
    const anchorId = Number(item.anchor_id || item.target_id || item.id || 0)
    const roomId = Number(item.room_id || 0)
    const name = String(item.name || '主播').trim()
    const liveStatus = Number(item.live_status)
    const badgeType = String(item.badge_type || (liveStatus === 1 ? 'pink' : 'gold')).trim()

    return {
      id: anchorId,
      targetId: anchorId,
      anchorId,
      roomId,
      name,
      badge: String(item.badge || item.live_status_text || '暂无直播').trim(),
      badgeType,
      fans: String(item.follow_count_text || '--').trim(),
      followCount: Number(item.follow_count || 0),
      topic: String(item.topic || item.room_title || item.description || '').trim(),
      watching: String(item.watching_text || item.live_status_text || '暂无直播').trim(),
      avatar: String(item.avatar || '').trim(),
      avatarText: getAvatarText(name, '播'),
      avatarClass: LIVE_AVATAR_CLASSES[index % LIVE_AVATAR_CLASSES.length],
      routeUrl: String(item.route_url || '').trim(),
      liveStatus,
      startTime: String(item.start_time || '').trim(),
      startTimeStamp: parseFollowTimestamp(item.start_time || item.update_time || item.create_time),
      favoriteUpdateTime: String(item.update_time || '').trim(),
      favoriteUpdateTimeStamp: parseFollowTimestamp(item.update_time || item.create_time)
    }
  })
}

/**
 * 生成头像兜底文字。
 *
 * @param {string} value 原始名称。
 * @param {string} fallback 兜底文字。
 * @returns {string} 头像文字。
 */
function getAvatarText(value = '', fallback = '') {
  const text = String(value || '').trim()
  if (!text) {
    return fallback
  }

  const prefix = text.slice(0, 2)
  if (/^[A-Za-z0-9]{2}$/.test(prefix)) {
    return prefix.toUpperCase()
  }

  return text.slice(0, 1)
}

/**
 * 格式化关注动态时间。
 *
 * @param {string} value 时间字符串。
 * @param {string} emptyText 空值文案。
 * @returns {string} 页面展示时间。
 */
function formatFollowTime(value = '', emptyText = '') {
  const text = String(value || '').trim()
  if (!text) {
    return emptyText
  }

  return `最近上新 ${text.length >= 16 ? text.slice(5, 16) : text}`
}

/**
 * 格式化平台更新时间。
 *
 * @param {string} value 时间字符串。
 * @param {string} emptyText 空值文案。
 * @returns {string} 页面展示时间。
 */
function formatPlatformTime(value = '', emptyText = '') {
  const text = String(value || '').trim()
  if (!text) {
    return emptyText
  }

  return `最近更新 ${text.length >= 16 ? text.slice(5, 16) : text}`
}

/**
 * 将日期字符串转换为时间戳。
 *
 * @param {string} value 日期字符串。
 * @returns {number} 时间戳。
 */
function parseFollowTimestamp(value = '') {
  const text = String(value || '').trim()
  if (!text) {
    return 0
  }

  const normalized = text.indexOf('T') > -1 ? text : text.replace(/-/g, '/')
  const timestamp = Date.parse(normalized)
  return Number.isNaN(timestamp) ? 0 : timestamp
}

/**
 * 获取直播排序优先级。
 *
 * @param {Object} item 直播项。
 * @returns {number} 排序优先级。
 */
function getLiveSortRank(item = {}) {
  const status = Number(item.liveStatus || 0)
  if (status === 1) {
    return 0
  }
  if (status === 0) {
    return 1
  }
  if (status === 2) {
    return 2
  }

  return 3
}

/**
 * 排序店铺列表。
 *
 * @param {Array} list 店铺列表。
 * @param {string} order 排序方向：desc/asc。
 * @returns {Array} 排序后的列表副本。
 */
function sortShopList(list = [], order = 'desc') {
  const items = Array.isArray(list) ? list.slice() : []
  const isAsc = order === 'asc'

  return items.sort((left, right) => {
    const leftTime = Number(left.latestNewTimeStamp || left.favoriteUpdateTimeStamp || 0)
    const rightTime = Number(right.latestNewTimeStamp || right.favoriteUpdateTimeStamp || 0)
    if (leftTime !== rightTime) {
      return isAsc ? leftTime - rightTime : rightTime - leftTime
    }

    const leftCount = Number(left.newCount || 0)
    const rightCount = Number(right.newCount || 0)
    if (leftCount !== rightCount) {
      return isAsc ? leftCount - rightCount : rightCount - leftCount
    }

    return String(left.name || '').localeCompare(String(right.name || ''))
  })
}

/**
 * 排序主播列表。
 *
 * @param {Array} list 主播列表。
 * @param {string} order 排序方向：desc/asc。
 * @returns {Array} 排序后的列表副本。
 */
function sortLiveList(list = [], order = 'desc') {
  const items = Array.isArray(list) ? list.slice() : []
  const isAsc = order === 'asc'

  return items.sort((left, right) => {
    const leftRank = getLiveSortRank(left)
    const rightRank = getLiveSortRank(right)
    if (leftRank !== rightRank) {
      return isAsc ? rightRank - leftRank : leftRank - rightRank
    }

    const leftTime = Number(left.startTimeStamp || left.favoriteUpdateTimeStamp || 0)
    const rightTime = Number(right.startTimeStamp || right.favoriteUpdateTimeStamp || 0)
    if (leftTime !== rightTime) {
      return isAsc ? leftTime - rightTime : rightTime - leftTime
    }

    const leftFans = Number(left.followCount || 0)
    const rightFans = Number(right.followCount || 0)
    if (leftFans !== rightFans) {
      return isAsc ? leftFans - rightFans : rightFans - leftFans
    }

    return String(left.name || '').localeCompare(String(right.name || ''))
  })
}

/**
 * 返回上一页；无历史栈时回到个人中心。
 *
 * @returns {void}
 */
function handleBack() {
  const pageStack = getCurrentPages()

  if (Array.isArray(pageStack) && pageStack.length > 1) {
    uni.navigateBack({
      fail: () => {
        switchToUserCenter()
      }
    })
    return
  }

  switchToUserCenter()
}

/**
 * 切换顶部关注类型。
 *
 * @param {string} tabKey 类型 key：shop/live。
 * @returns {void}
 */
function handleTabChange(tabKey) {
  if (tabKey !== 'shop' && tabKey !== 'live') {
    return
  }

  activeTab.value = tabKey
  loadFollowList(tabKey)
}

/**
 * 跳转搜索页。
 *
 * @returns {void}
 */
function handleSearch() {
  uni.navigateTo({
    url: '/pages/contents/search/index'
  })
}

/**
 * 管理入口占位提示。
 *
 * @returns {void}
 */
function handleManage() {
  showToast('关注管理开发中')
}

/**
 * 打开排序选择。
 *
 * @param {string} type 排序类型。
 * @returns {void}
 */
function handleSort(type) {
  if (type === 'shop') {
    uni.showActionSheet({
      itemList: ['最新动态', '最早动态'],
      success: (result) => {
        shopSortOrder.value = result.tapIndex === 1 ? 'asc' : 'desc'
      }
    })
    return
  }

  if (type === 'live') {
    uni.showActionSheet({
      itemList: ['最近开播', '最早开播'],
      success: (result) => {
        liveSortOrder.value = result.tapIndex === 1 ? 'asc' : 'desc'
      }
    })
  }
}

/**
 * 跳转店铺主页。
 *
 * @param {Object} item 店铺项。
 * @returns {void}
 */
function handleOpenShop(item = {}) {
  const merchantId = Number(item.merchantId || 0)
  uni.navigateTo({
    url: merchantId > 0 ? `/pages/merchant/user/index?merchant_id=${merchantId}` : '/pages/merchant/user/index'
  })
}

/**
 * 打开平台首卡。
 *
 * @param {Object} item 平台项。
 * @returns {void}
 */
function handleOpenPlatform(item = {}) {
  openFollowRoute(item.routeUrl || item.route_url || '', '/pages/index/index')
}

/**
 * 取消关注前二次确认。
 *
 * @param {string} type 关注类型：shop/live/platform。
 * @param {Object} item 当前关注项。
 * @returns {void}
 */
function handleCancelFollow(type, item = {}) {
  uni.showModal({
    title: '取消关注',
    content: `确认取消关注“${item.name || '当前对象'}”吗？`,
    confirmColor: type === 'live' ? liveColor : themeState.primary_color,
    success: (result) => {
      if (result.confirm) {
        removeFollowItem(type, item)
      }
    }
  })
}

/**
 * 调用接口取消关注并移除当前列表项。
 *
 * @param {string} type 关注类型：shop/live/platform。
 * @param {Object} item 关注项。
 * @returns {Promise<void>}
 */
async function removeFollowItem(type, item = {}) {
  const targetId = resolveFollowTargetId(type, item)
  const isPlatform = type === 'platform'
  if (!isPlatform && targetId <= 0) {
    showToast('关注对象不存在')
    return
  }

  const res = await cancelUserFollow({
    type,
    target_id: targetId
  }, {
    showError: false
  })

  if (!res || res.code !== 200) {
    showToast(res && res.msg ? res.msg : '取消关注失败')
    return
  }

  if (type === 'platform') {
    platformCard.value = null
  } else if (type === 'shop') {
    shops.value = shops.value.filter((shop) => Number(shop.targetId || shop.id || 0) !== targetId)
  } else {
    lives.value = lives.value.filter((live) => Number(live.targetId || live.id || 0) !== targetId)
  }

  showToast('已取消关注')
}

/**
 * 获取取消关注对象 ID。
 *
 * @param {string} type 关注类型：shop/live/platform。
 * @param {Object} item 关注项。
 * @returns {number} 关注对象 ID。
 */
function resolveFollowTargetId(type, item = {}) {
  if (type === 'platform') {
    return 0
  }

  if (type === 'shop') {
    return Number(item.targetId || item.merchantId || item.id || 0)
  }

  return Number(item.targetId || item.anchorId || item.id || 0)
}

/**
 * 店铺发现入口。
 *
 * @returns {void}
 */
function handleDiscoverShop() {
  uni.navigateTo({
    url: '/pages/contents/search/index?type=merchant'
  })
}

/**
 * 主播发现入口。
 *
 * @returns {void}
 */
function handleDiscoverLive() {
  uni.navigateTo({
    url: '/pages/lives/live/index'
  })
}

/**
 * 观看直播入口。
 *
 * @param {Object} item 主播项。
 * @returns {void}
 */
function handleWatchLive(item = {}) {
  const roomId = Number(item.roomId || 0)
  const url = roomId > 0 ? `/pages/lives/room/index?id=${roomId}` : (item.routeUrl || '/pages/lives/live/index')

  uni.navigateTo({
    url,
    fail: () => {
      showToast(item.name ? `${item.name}暂未开播` : '直播暂未开播')
    }
  })
}

/**
 * 按路由类型打开页面，兼容 tabBar 跳转。
 *
 * @param {string} targetUrl 目标地址。
 * @param {string} fallbackUrl 失败时兜底地址。
 * @param {string} failMessage 打开失败提示。
 * @returns {void}
 */
function openFollowRoute(targetUrl = '', fallbackUrl = '', failMessage = '暂无法打开该页面') {
  const resolvedUrl = String(targetUrl || fallbackUrl || '').trim()
  if (!resolvedUrl) {
    showToast(failMessage)
    return
  }

  const finalUrl = resolvedUrl.charAt(0) === '/' ? resolvedUrl : `/${resolvedUrl}`
  const routePath = finalUrl.split('?')[0]

  if (TAB_BAR_PATHS.indexOf(routePath) > -1) {
    uni.switchTab({
      url: routePath,
      fail: () => {
        if (fallbackUrl && fallbackUrl !== targetUrl) {
          openFollowRoute(fallbackUrl, '', failMessage)
          return
        }

        showToast(failMessage)
      }
    })
    return
  }

  uni.navigateTo({
    url: finalUrl,
    fail: () => {
      if (fallbackUrl && fallbackUrl !== targetUrl) {
        openFollowRoute(fallbackUrl, '', failMessage)
        return
      }

      showToast(failMessage)
    }
  })
}

/**
 * 切回个人中心。
 *
 * @returns {void}
 */
function switchToUserCenter() {
  uni.switchTab({
    url: '/pages/user/index'
  })
}

/**
 * 展示轻提示。
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
  background: var(--surface-page, #f9f9f9);
}

.follow-page .app-safe-top__back {
  display: none;
}

.follow-page .app-safe-top__title {
  font-size: 32rpx;
  font-weight: 700;
  line-height: 44rpx;
}
</style>

<style lang="scss" scoped>
.follow-page {
  --follow-live: #f20b56;
  --follow-live-pale: #fff0f5;
  --follow-live-soft: #fff1f4;
  --follow-live-shadow: rgba(242, 11, 86, 0.16);
  --follow-shop-update: #c56028;
  min-height: 100%;
  background: var(--surface-page, #f9f9f9);
}

.nav-actions {
  display: flex;
  align-items: center;
  width: 148rpx;
}

.nav-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 64rpx;
}

.nav-action + .nav-action {
  margin-left: 20rpx;
}

.nav-action--hover {
  opacity: 0.72;
}

.nav-action__text {
  margin-top: 4rpx;
  font-size: 20rpx;
  font-weight: 500;
  line-height: 28rpx;
  color: var(--text-title, #18181b);
}

.tab-panel {
  display: flex;
  align-items: center;
  padding: 24rpx 32rpx 28rpx;
  background: var(--surface-card, #ffffff);
}

.tab-card {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 80rpx;
  border-radius: 24rpx;
  background: var(--surface-card-muted, #f3f4f6);
}

.tab-card--last {
  margin-left: 20rpx;
}

.tab-card--active {
  background: var(--brand-primary-pale, #fff7ed);
}

.tab-card--hover {
  opacity: 0.9;
}

.tab-card__text {
  margin-left: 14rpx;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 44rpx;
  color: var(--text-title, #18181b);
}

.tab-card--active .tab-card__text {
  color: var(--brand-primary, #ea580c);
}

.follow-content {
  padding: 28rpx 32rpx 56rpx;
}

.platform-card {
  margin-bottom: 24rpx;
  border: 1rpx solid var(--brand-focus-shadow, rgba(234, 88, 12, 0.16));
  background: linear-gradient(180deg, #fffaf2 0%, #ffffff 100%);
}

.platform-card--hover {
  opacity: 0.92;
}

.platform-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 24rpx;
}

.platform-card__top {
  display: flex;
  align-items: center;
  min-width: 0;
}

.platform-card__name {
  flex: 1;
  min-width: 0;
  margin-right: 14rpx;
}

.platform-card__tag {
  flex-shrink: 0;
}

.platform-card__follow {
  margin-left: 14rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-subtitle, #52525b);
}

.platform-card__desc {
  display: block;
  overflow: hidden;
  margin-top: 12rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-secondary, #71717a);
}

.follow-section + .follow-section {
  margin-top: 32rpx;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 46rpx;
  margin-bottom: 18rpx;
}

.section-title {
  display: flex;
  align-items: center;
}

.section-title__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28rpx;
  height: 28rpx;
  border-radius: 7rpx;
}

.section-title__icon--shop {
  background: var(--brand-primary, #ea580c);
}

.section-title__icon--live {
  border-radius: 50%;
  background: #f20b56;
}

.section-title__text {
  margin-left: 14rpx;
  font-size: 32rpx;
  font-weight: 700;
  line-height: 44rpx;
  color: var(--text-title, #18181b);
}

.section-sort {
  display: flex;
  align-items: center;
  padding: 8rpx 0 8rpx 20rpx;
}

.section-sort--hover {
  opacity: 0.72;
}

.section-sort__text {
  margin-right: 8rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-subtitle, #52525b);
}

.follow-card,
.guide-card {
  border-radius: 24rpx;
  background: var(--surface-card, #ffffff);
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.follow-card {
  display: flex;
  align-items: center;
  padding: 28rpx;
}

.follow-card + .follow-card {
  margin-top: 20rpx;
}

.avatar-circle,
.live-avatar {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 112rpx;
  height: 112rpx;
  border-radius: 50%;
  overflow: hidden;
}

.avatar-circle__main {
  font-size: 32rpx;
  font-weight: 500;
  line-height: 40rpx;
}

.avatar-circle__sub {
  margin-top: 6rpx;
  font-size: 18rpx;
  line-height: 24rpx;
}

.avatar-circle__image,
.live-avatar__image {
  display: block;
  width: 100%;
  height: 100%;
}

.avatar-circle--nala {
  color: #ffffff;
  background: linear-gradient(135deg, #c89b6b 0%, #b28352 100%);
}

.avatar-circle--ur {
  color: #ffffff;
  background: #050505;
}

.avatar-circle--green {
  color: #0c6b42;
  border: 1rpx solid #e3e6e8;
  background: #ffffff;
}

.avatar-circle--platform {
  color: var(--brand-primary, #ea580c);
  border: 1rpx solid var(--brand-focus-shadow, rgba(234, 88, 12, 0.16));
  background: #ffffff;
}

.shop-card__body,
.live-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 24rpx;
}

.item-title {
  display: block;
  overflow: hidden;
  font-size: 28rpx;
  font-weight: 700;
  line-height: 40rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-title, #18181b);
}

.item-meta {
  display: flex;
  align-items: center;
  margin-top: 12rpx;
}

.item-category {
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-regular, #3f3f46);
}

.item-tag,
.live-badge {
  margin-left: 14rpx;
  padding: 4rpx 12rpx;
  border: 1rpx solid var(--border-default, #e4e4e7);
  border-radius: 8rpx;
  font-size: 20rpx;
  line-height: 30rpx;
  color: var(--text-secondary, #71717a);
}

.shop-update {
  display: flex;
  align-items: center;
  margin-top: 14rpx;
}

.shop-update__image {
  width: 44rpx;
  height: 44rpx;
  border-radius: 8rpx;
  background: var(--surface-card-muted, #f3f4f6);
}

.shop-update__image + .shop-update__image {
  margin-left: 4rpx;
}

.shop-update__text {
  min-width: 0;
  margin-left: 12rpx;
}

.shop-update__headline,
.shop-update__time {
  display: block;
  overflow: hidden;
  max-width: 280rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.shop-update__headline {
  font-size: 20rpx;
  line-height: 28rpx;
  color: var(--follow-shop-update);
}

.shop-update__time {
  margin-top: 2rpx;
  font-size: 18rpx;
  line-height: 26rpx;
  color: var(--text-secondary, #71717a);
}

.card-actions {
  flex-shrink: 0;
  margin-left: 20rpx;
}

.outline-button,
.plain-button,
.filled-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 132rpx;
  min-height: 64rpx;
  border-radius: 999rpx;
}

.outline-button {
  border: 1rpx solid var(--brand-primary, #ea580c);
  background: var(--surface-card, #ffffff);
}

.outline-button--platform {
  min-width: 132rpx;
}

.outline-button__text {
  font-size: 24rpx;
  font-weight: 700;
  line-height: 34rpx;
  color: var(--brand-primary, #ea580c);
}

.plain-button {
  margin-top: 16rpx;
  background: var(--surface-card-muted, #f3f4f6);
}

.plain-button__text {
  font-size: 22rpx;
  font-weight: 500;
  line-height: 32rpx;
  color: var(--text-regular, #3f3f46);
}

.outline-button--hover,
.plain-button--hover,
.filled-button--hover,
.guide-card__button--hover {
  opacity: 0.82;
}

.loading-card {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 168rpx;
  border-radius: 24rpx;
  background: var(--surface-card, #ffffff);
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.loading-card__text {
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #71717a);
}

.guide-card {
  display: flex;
  align-items: center;
  margin-top: 20rpx;
  padding: 48rpx 32rpx;
}

.guide-card__image {
  flex-shrink: 0;
}

.guide-card__image--shop {
  width: 198rpx;
  height: 142rpx;
}

.guide-card__image--live {
  width: 200rpx;
  height: 150rpx;
}

.guide-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 34rpx;
}

.guide-card__title {
  display: block;
  font-size: 30rpx;
  font-weight: 700;
  line-height: 42rpx;
  color: var(--text-title, #18181b);
}

.guide-card__desc {
  display: block;
  margin-top: 12rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-subtitle, #52525b);
}

.guide-card__button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 240rpx;
  min-height: 64rpx;
  margin-top: 18rpx;
  border-radius: 999rpx;
  background: var(--surface-card, #ffffff);
}

.guide-card__button--shop {
  border: 1rpx solid var(--brand-primary, #ea580c);
}

.guide-card__button--live {
  border: 1rpx solid var(--follow-live);
}

.guide-card__button-text {
  font-size: 20rpx;
  font-weight: 700;
  line-height: 28rpx;
  color: var(--brand-primary, #ea580c);
}

.guide-card__button--live .guide-card__button-text {
  color: var(--follow-live);
}

.live-avatar__text {
  font-size: 44rpx;
  font-weight: 700;
  line-height: 56rpx;
  color: #ffffff;
}

.live-avatar--rose {
  background: linear-gradient(135deg, #ffe2e8 0%, #b96572 100%);
}

.live-avatar--blue {
  background: linear-gradient(135deg, #e4edf8 0%, #6f829a 100%);
}

.live-name-row {
  display: flex;
  align-items: center;
}

.live-name-row .item-title {
  flex: 0 1 auto;
  max-width: 210rpx;
}

.live-badge {
  flex-shrink: 0;
  border: 0;
  color: #a76823;
  background: #fff2d9;
}

.live-badge--pink {
  color: var(--follow-live);
  background: var(--follow-live-pale);
}

.live-fans {
  display: block;
  margin-top: 8rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-subtitle, #52525b);
}

.live-topic {
  display: block;
  overflow: hidden;
  margin-top: 12rpx;
  max-width: 360rpx;
  font-size: 24rpx;
  font-weight: 700;
  line-height: 34rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-title, #18181b);
}

.live-watching {
  display: flex;
  align-items: center;
  margin-top: 8rpx;
}

.live-watching__fire {
  font-size: 20rpx;
  line-height: 28rpx;
}

.live-watching__text {
  margin-left: 6rpx;
  font-size: 22rpx;
  line-height: 30rpx;
  color: var(--text-title, #18181b);
}

.filled-button {
  background: var(--follow-live);
  box-shadow: 0 12rpx 24rpx var(--follow-live-shadow);
}

.filled-button__text {
  font-size: 24rpx;
  font-weight: 700;
  line-height: 34rpx;
  color: #ffffff;
}
</style>
