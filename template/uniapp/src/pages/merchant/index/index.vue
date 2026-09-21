<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="merchant-index-page">
    <scroll-view
      scroll-y
      class="merchant-index-scroll"
      refresher-enabled
      :refresher-triggered="refreshing"
      @refresherrefresh="handleRefresh"
      @scrolltolower="handleLoadMore"
    >
      <view class="merchant-index-scroll__inner">
        <view class="merchant-profile-section">
          <view v-if="loading && !hasMerchant" class="merchant-profile-skeleton">
            <view class="merchant-profile-skeleton__logo"></view>
            <view class="merchant-profile-skeleton__content">
              <view class="merchant-profile-skeleton__line merchant-profile-skeleton__line--title"></view>
              <view class="merchant-profile-skeleton__line merchant-profile-skeleton__line--meta"></view>
            </view>
            <view class="merchant-profile-skeleton__button"></view>
          </view>

          <view v-else-if="hasMerchant" class="merchant-profile-card">
            <view class="merchant-profile-card__logo-shell">
              <image v-if="merchantInfo.logo" class="merchant-profile-card__logo" :src="merchantInfo.logo" mode="aspectFill"></image>
              <text v-else class="iconfont icon-shopfill merchant-profile-card__logo-icon"></text>
              <view class="merchant-profile-card__logo-badge">
                <text class="iconfont icon-check merchant-profile-card__logo-badge-icon"></text>
              </view>
            </view>

            <view class="merchant-profile-card__content">
              <view class="merchant-profile-card__name-row">
                <text class="merchant-profile-card__name">{{ merchantInfo.name }}</text>
              </view>
              <view class="merchant-profile-card__meta">
                <text class="merchant-profile-card__followers">{{ merchantInfo.followText }}</text>
                <text v-if="merchantInfo.businessStatusText" class="merchant-profile-card__status">{{ merchantInfo.businessStatusText }}</text>
              </view>
            </view>

            <view
              class="merchant-profile-card__follow"
              :class="{ 'merchant-profile-card__follow--active': merchantInfo.isFollowed }"
              hover-class="merchant-profile-card__follow--hover"
              @click.stop="handleToggleFollow"
            >
              <text v-if="!merchantInfo.isFollowed" class="merchant-profile-card__follow-plus">+</text>
              <text class="merchant-profile-card__follow-text">{{ followButtonText }}</text>
            </view>
          </view>

          <view v-else class="merchant-error">
            <text class="merchant-error__title">{{ errorMessage || '商户信息暂不可访问' }}</text>
            <view class="merchant-error__button" hover-class="merchant-error__button--hover" @click="loadMerchantHome">
              <text class="merchant-error__button-text">重新加载</text>
            </view>
          </view>
        </view>

        <view v-if="hasMerchant" class="merchant-main-section">
          <swiper
            v-if="bannerList.length > 0"
            class="merchant-banner-swiper"
            :style="bannerSwiperStyle"
            circular
            autoplay
            indicator-dots
            indicator-color="rgba(255, 255, 255, 0.72)"
            :indicator-active-color="themePrimaryColor"
            @change="handleBannerChange"
          >
            <swiper-item v-for="(item, index) in bannerList" :key="item.key">
              <view class="merchant-banner-card" hover-class="merchant-banner-card--hover" @click="handleBannerClick(item)">
                <image class="merchant-banner-card__image" :src="item.image" mode="aspectFill" @load="handleBannerImageLoad($event, index)"></image>
              </view>
            </swiper-item>
          </swiper>

          <view v-if="liveList.length > 0" class="merchant-section merchant-section--live">
            <view class="merchant-section__header">
              <view class="merchant-section__title-wrap">
                <image class="merchant-section__icon" :src="sectionIcons.live" mode="aspectFit"></image>
                <text class="merchant-section__title">热门直播</text>
              </view>
              <view class="merchant-section__more" hover-class="merchant-section__more--hover" @click="handleMoreLive">
                <text class="merchant-section__more-text">查看全部</text>
                <text class="iconfont icon-right merchant-section__more-icon"></text>
              </view>
            </view>

            <view class="live-list">
              <view
                v-for="item in liveList"
                :key="item.key"
                class="live-card"
                hover-class="live-card--hover"
                @click="handleLiveClick(item)"
              >
                <view class="live-card__cover">
                  <image v-if="item.cover" class="live-card__image" :src="item.cover" mode="aspectFill"></image>
                  <view v-else class="live-card__placeholder">
                    <text class="iconfont icon-record live-card__placeholder-icon"></text>
                  </view>
                  <view class="live-card__badge">
                    <text class="iconfont icon-playfill live-card__badge-icon"></text>
                    <text class="live-card__badge-text">{{ item.statusText }}</text>
                  </view>
                  <view class="live-card__views">
                    <text class="iconfont icon-profile live-card__views-icon"></text>
                    <text class="live-card__views-text">{{ item.viewText }}</text>
                  </view>
                </view>
                <view class="live-card__body">
                  <image v-if="item.anchorAvatar" class="live-card__avatar" :src="item.anchorAvatar" mode="aspectFill"></image>
                  <view v-else class="live-card__avatar live-card__avatar--fallback">
                    <text class="iconfont icon-profile live-card__avatar-icon"></text>
                  </view>
                  <view class="live-card__info">
                    <text class="live-card__title">{{ item.title }}</text>
                    <text class="live-card__anchor">{{ item.anchorName }}</text>
                  </view>
                </view>
              </view>
            </view>
          </view>

          <template v-for="section in displaySections" :key="section.type">
            <view v-if="section.type === 'special'" class="merchant-section">
              <view class="merchant-section__header">
                <view class="merchant-section__title-wrap">
                  <image class="merchant-section__icon" :src="sectionIcons.special" mode="aspectFit"></image>
                  <text class="merchant-section__title">精选专题</text>
                </view>
                <view class="merchant-section__more" hover-class="merchant-section__more--hover" @click="handleMoreSpecial">
                  <text class="merchant-section__more-text">查看全部</text>
                  <text class="iconfont icon-right merchant-section__more-icon"></text>
                </view>
              </view>

              <scroll-view v-if="section.list.length > 0" :scroll-x="!section.isLast" class="special-scroll scrollbar-hidden" enable-flex>
                <view class="special-scroll__inner" :class="{ 'special-scroll__inner--vertical': section.isLast }">
                  <view
                    v-for="item in section.list"
                    :key="item.key"
                    class="special-card"
                    hover-class="special-card--hover"
                    @click="handleSpecialClick(item)"
                  >
                    <image v-if="item.cover" class="special-card__image" :src="item.cover" mode="aspectFill"></image>
                    <view v-else class="special-card__placeholder">
                      <text class="iconfont icon-record special-card__placeholder-icon"></text>
                    </view>
                    <view class="special-card__mask"></view>
                    <view class="special-card__content">
                      <text class="special-card__title">{{ item.title }}</text>
                      <text class="special-card__desc">{{ item.description }}</text>
                      <view class="special-card__button">
                        <text class="special-card__button-text">{{ item.buttonText }}</text>
                      </view>
                    </view>
                  </view>
                </view>
              </scroll-view>
              <view v-else class="content-tab-empty">
                <text class="content-tab-empty__text">暂无专题</text>
              </view>
            </view>

            <view v-else-if="section.type === 'content'" class="merchant-section merchant-section--content">
              <view class="merchant-section__header merchant-section__header--content">
                <view class="merchant-section__title-wrap">
                  <image class="merchant-section__icon" :src="sectionIcons.content" mode="aspectFit"></image>
                  <text class="merchant-section__title">推荐内容</text>
                </view>
                <view class="merchant-section__more" hover-class="merchant-section__more--hover" @click="handleMoreContent">
                  <text class="merchant-section__more-text">查看全部</text>
                  <text class="iconfont icon-right merchant-section__more-icon"></text>
                </view>
              </view>
              <scroll-view v-if="contentTabs.length > 1" scroll-x class="content-tabs scrollbar-hidden" enable-flex>
                <view class="content-tabs__inner">
                  <view
                    v-for="item in contentTabs"
                    :key="item.key"
                    :class="['content-tabs__item', { 'content-tabs__item--active': item.key === activeContentTab }]"
                    hover-class="content-tabs__item--hover"
                    @click="handleContentTab(item)"
                  >
                    <text :class="['content-tabs__text', { 'content-tabs__text--active': item.key === activeContentTab }]">{{ item.text }}</text>
                  </view>
                </view>
              </scroll-view>

              <view v-if="displayContentList.length > 0" class="content-grid">
                <view
                  v-for="(item, index) in displayContentList"
                  :key="item.key"
                  :class="['content-card', { 'content-card--even': index % 2 === 1 }]"
                  hover-class="content-card--hover"
                  @click="handleContentClick(item)"
                >
                  <view class="content-card__cover">
                    <image v-if="item.cover" class="content-card__image" :src="item.cover" mode="aspectFill"></image>
                    <view v-else class="content-card__placeholder">
                      <text class="iconfont icon-pic content-card__placeholder-icon"></text>
                    </view>
                    <view class="content-card__badge">
                      <text class="content-card__badge-text">{{ item.typeText }}</text>
                    </view>
                    <view v-if="item.durationText" class="content-card__duration">
                      <text class="content-card__duration-text">{{ item.durationText }}</text>
                    </view>
                  </view>
                  <view class="content-card__body">
                    <text class="content-card__title">{{ item.title }}</text>
                    <text class="content-card__desc">{{ item.description }}</text>
                    <view class="content-card__stats">
                      <view class="content-card__stat">
                        <text class="iconfont icon-profile content-card__stat-icon"></text>
                        <text class="content-card__stat-text">{{ item.viewText }}</text>
                      </view>
                      <view class="content-card__stat content-card__stat--like">
                        <text class="iconfont icon-like content-card__stat-icon"></text>
                        <text class="content-card__stat-text">{{ item.likeText }}</text>
                      </view>
                    </view>
                    <view class="content-card__footer">
                      <text class="content-card__price">{{ item.priceText }}</text>
                      <text class="content-card__learn">{{ item.learnText }}</text>
                    </view>
                  </view>
                </view>
              </view>

              <view v-else class="content-tab-empty">
                <text class="content-tab-empty__text">暂无内容</text>
              </view>
            </view>

            <view v-else-if="section.type === 'product'" class="merchant-section merchant-section--product">
              <view class="merchant-section__header">
                <view class="merchant-section__title-wrap">
                  <text class="iconfont icon-daohanggouwuche merchant-section__product-icon"></text>
                  <text class="merchant-section__title">精选商品</text>
                </view>
                <view class="merchant-section__more" hover-class="merchant-section__more--hover" @click="handleMoreProduct">
                  <text class="merchant-section__more-text">查看全部</text>
                  <text class="iconfont icon-right merchant-section__more-icon"></text>
                </view>
              </view>
              <diy-product :config="section.productConfig" :data="section.productData" :loading="loading" :error="section.error" @retry="handleLoadMore" />
            </view>

            <view v-if="section.isLast && section.error" class="merchant-load-state">
              <text class="merchant-load-state__text">{{ section.error }}</text>
              <view class="merchant-error__button" hover-class="merchant-error__button--hover" @click="handleLoadMore">
                <text class="merchant-error__button-text">重新加载</text>
              </view>
            </view>
            <view v-else-if="section.isLast && section.type !== 'product' && section.list.length > 0" class="merchant-load-state">
              <text class="merchant-load-state__text">{{ section.loading_more ? '加载中...' : (section.has_more ? '' : '没有更多了') }}</text>
            </view>
          </template>

          <view v-if="!loading && !hasPageContent" class="merchant-empty">
            <text class="merchant-empty__title">商家首页内容待配置</text>
          </view>

          <view class="merchant-index-scroll__bottom-space"></view>
        </view>
      </view>
    </scroll-view>

    <merchant-bottom-nav active-key="home" :merchant-id="merchantId" :menu-mode="menuMode" />
    <app-floating-button />
  </view>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, ref } from 'vue'
import { onLoad, onReady, onUnload } from '@dcloudio/uni-app'
import { getMerchantFollowStatus, getMerchantHome, getMerchantHomeSection, toggleMerchantFollow } from '@/api/merchant'
import { getLiveRoomList } from '@/api/live'
import MerchantBottomNav from '../components/MerchantBottomNav.vue'
import DiyProduct from '@/pages/diy/components/diy/DiyProduct.vue'
import { normalizePublicErrorMessage } from '@/utils/error'

import iconSectionLive from '../static/icon_01_1785509002283.png'
import iconSectionSpecial from '../static/icon_03_1785509002283.png'
import iconSectionContent from '../static/icon_05_1785509002283.png'

const instance = getCurrentInstance()

const SECTION_ICONS = {
  live: iconSectionLive,
  special: iconSectionSpecial,
  content: iconSectionContent
}

const CONTENT_TABS = [
  { key: 'all', text: '全部内容', contentType: 0 },
  { key: 'video', text: '视频教程', contentType: 6 },
  { key: 'graphic', text: '图文教程', contentType: 1 },
  { key: 'audio', text: '音频听学', contentType: 5 },
  { key: 'text', text: '文字', contentType: 2 },
  { key: 'novel', text: '小说', contentType: 3 },
  { key: 'image', text: '图片', contentType: 4 }
]

const DEFAULT_BANNER_HEIGHT_STYLE = 'height: 240rpx;'

const EMPTY_MERCHANT_INFO = {
  id: 0,
  name: '',
  logo: '',
  followCount: 0,
  followText: '',
  businessStatusText: '',
  isFollowed: false,
  verified: false
}

defineOptions({ name: 'MerchantIndexPage' })

const merchantId = ref(0)
const menuMode = ref('special')
const loading = ref(false)
const refreshing = ref(false)
const followLoading = ref(false)
const errorMessage = ref('')
const merchantInfo = ref({ ...EMPTY_MERCHANT_INFO })
const bannerList = ref([])
const bannerActiveIndex = ref(0)
const bannerAspectRatioMap = ref({})
const bannerSwiperHeightPx = ref(0)
const liveList = ref([])
const sectionList = ref([])
const activeContentTab = ref('all')
const sectionIcons = SECTION_ICONS
let homeRequestId = 0

const contentList = computed(() => {
  const section = sectionList.value.find((item) => item.type === 'content')
  return section ? section.list : []
})

const displaySections = computed(() => {
  return sectionList.value.map((section, index) => {
    const isLast = index === sectionList.value.length - 1
    return {
      ...section,
      isLast,
      productConfig: { layout: 'grid', show_count: section.limit, load_more: isLast, with_nav: false },
      productData: { list: section.list, has_more: section.has_more, loading_more: section.loading_more }
    }
  })
})

const hasMerchant = computed(() => {
  return Number(merchantInfo.value.id || 0) > 0
})

const hasPageContent = computed(() => {
  return bannerList.value.length > 0 ||
    liveList.value.length > 0 ||
    sectionList.value.length > 0
})

const followButtonText = computed(() => {
  if (followLoading.value) {
    return '处理中'
  }
  return merchantInfo.value.isFollowed ? '已关注' : '关注'
})

const bannerSwiperStyle = computed(() => {
  if (bannerSwiperHeightPx.value > 0) {
    return `height: ${bannerSwiperHeightPx.value}px;`
  }
  return DEFAULT_BANNER_HEIGHT_STYLE
})

const contentTabs = computed(() => {
  const loadedTypes = new Set(contentList.value.map((item) => Number(item.contentType || 0)))
  return CONTENT_TABS.filter((item) => !item.contentType || loadedTypes.has(item.contentType))
})

const displayContentList = computed(() => {
  const active = CONTENT_TABS.find((item) => item.key === activeContentTab.value) || CONTENT_TABS[0]
  if (!active.contentType) {
    return contentList.value
  }
  return contentList.value.filter((item) => Number(item.contentType || 0) === Number(active.contentType || 0))
})

onLoad((options = {}) => {
  merchantId.value = Number(options.merchant_id || options.merchantId || 0)
  loadMerchantHome()
})

onReady(() => {
  if (typeof uni.onWindowResize === 'function') {
    uni.onWindowResize(handleBannerWindowResize)
  }
})

onUnload(() => {
  homeRequestId += 1
  if (typeof uni.offWindowResize === 'function') {
    uni.offWindowResize(handleBannerWindowResize)
  }
})

/**
 * 加载商家首页数据。
 *
 * @returns {Promise<void>}
 */
async function loadMerchantHome() {
  const requestId = ++homeRequestId
  if (merchantId.value <= 0) {
    errorMessage.value = '商户ID不能为空'
    clearPageData()
    loading.value = false
    refreshing.value = false
    return
  }

  loading.value = true
  errorMessage.value = ''
  try {
    const res = await getMerchantHome({ merchant_id: merchantId.value }, { showError: false })
    if (requestId !== homeRequestId) {
      return
    }
    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '商家首页加载失败'
      clearPageData()
      return
    }

    const data = res.data || {}
    if (!Array.isArray(data.sections)) {
      throw new Error('商家首页配置暂不可用')
    }
    merchantInfo.value = normalizeMerchant(data.merchant || {})
    menuMode.value = data.home_config?.menu?.mode || 'special'
    resetBannerHeight()
    bannerList.value = normalizeBanners(data.banners || [])
    sectionList.value = normalizeSections(data.sections)
    activeContentTab.value = 'all'

    await Promise.all([
      loadLiveList(requestId),
      loadFollowStatus(requestId)
    ])
  } catch (error) {
    if (requestId !== homeRequestId) {
      return
    }
    console.error('[merchant-index] loadMerchantHome failed', error)
    errorMessage.value = normalizePublicErrorMessage(error.message, '商家首页加载失败')
    clearPageData()
  } finally {
    if (requestId === homeRequestId) {
      loading.value = false
      refreshing.value = false
    }
  }
}

/**
 * 加载商户热门直播。
 *
 * @param {number} requestId 当前首页请求序号，用于忽略刷新前的响应。
 * @returns {Promise<void>}
 */
async function loadLiveList(requestId) {
  try {
    const res = await getLiveRoomList({
      merchant_id: merchantId.value,
      page: 1,
      limit: 2,
      status: 1,
      is_hot: 1
    }, { showError: false })
    const data = res && res.code === 200 && res.data ? res.data : {}
    const list = Array.isArray(data.list) ? data.list : []
    if (requestId === homeRequestId) {
      liveList.value = normalizeLiveList(list)
    }
  } catch (error) {
    console.error('[merchant-index] loadLiveList failed', error)
    if (requestId === homeRequestId) {
      liveList.value = []
    }
  }
}

/**
 * 规范化服务端已筛选并排序的首页模块。
 *
 * @param {Array<Object>} sections 首页聚合响应中的模块列表。
 * @returns {Array<Object>} 包含分页运行时状态的模块。
 */
function normalizeSections(sections = []) {
  const seenTypes = new Set()
  return sections.filter((section) => {
    const valid = section && ['content', 'special', 'product'].includes(section.type) && !seenTypes.has(section.type)
    if (valid) {
      seenTypes.add(section.type)
    }
    return valid
  }).map((section, index, list) => ({
    type: section.type,
    list: normalizeSectionItems(section.type, section.list),
    total: Math.max(0, Number(section.total || 0)),
    page: Math.max(1, Number(section.page || 1)),
    limit: Math.max(1, Number(section.limit || 10)),
    has_more: index === list.length - 1 && (section.has_more === true || Number(section.has_more) === 1),
    loading_more: false,
    error: ''
  }))
}

/**
 * 规范化并按业务 ID 去重模块数据，商品字段保持后端原始结构。
 *
 * @param {string} type 模块类型。
 * @param {Array<Object>} list 模块列表。
 * @returns {Array<Object>} 模块展示数据。
 */
function normalizeSectionItems(type, list = []) {
  const source = Array.isArray(list) ? list : []
  const normalized = type === 'content' ? normalizeContentList(source)
    : (type === 'special' ? normalizeSpecialList(source) : source)
  const items = new Map()
  normalized.forEach((item) => {
    const id = Number(item && item.id || 0)
    if (id > 0) {
      items.set(id, item)
    }
  })
  return Array.from(items.values())
}

/**
 * 仅对最后一个启用模块进行触底分页；失败时保留页码，允许重试。
 *
 * @returns {Promise<void>}
 */
async function handleLoadMore() {
  const section = sectionList.value[sectionList.value.length - 1]
  if (loading.value || refreshing.value || !section || !section.has_more || section.loading_more) {
    return
  }

  const requestId = homeRequestId
  const nextPage = section.page + 1
  section.loading_more = true
  section.error = ''
  try {
    const res = await getMerchantHomeSection({
      merchant_id: merchantId.value,
      type: section.type,
      page: nextPage
    }, { showError: false })
    if (requestId !== homeRequestId) {
      return
    }
    if (!res || res.code !== 200 || !res.data || res.data.type !== section.type || !Array.isArray(res.data.list)) {
      throw new Error(res && res.msg || '加载失败，请重试')
    }
    const data = res.data
    const nextList = normalizeSectionItems(section.type, data.list)
    const merged = new Map(section.list.map((item) => [Number(item.id), item]))
    nextList.forEach((item) => merged.set(Number(item.id), item))
    section.list = Array.from(merged.values())
    section.page = Number(data.page || nextPage)
    section.total = Math.max(0, Number(data.total || 0))
    section.has_more = data.has_more === true || Number(data.has_more) === 1
  } catch (error) {
    if (requestId === homeRequestId) {
      section.error = normalizePublicErrorMessage(error.message, '加载失败，请重试')
    }
  } finally {
    if (requestId === homeRequestId) {
      section.loading_more = false
    }
  }
}

/**
 * 静默同步当前用户关注状态。
 *
 * @param {number} requestId 当前首页请求序号。
 * @returns {Promise<void>}
 */
async function loadFollowStatus(requestId) {
  try {
    const res = await getMerchantFollowStatus({ merchant_id: merchantId.value }, {
      showError: false,
      loginRedirect: false
    })
    if (requestId === homeRequestId && res && res.code === 200 && res.data) {
      applyFollowData(res.data)
    }
  } catch (error) {
    if (requestId === homeRequestId) {
      merchantInfo.value.isFollowed = false
    }
  }
}

/**
 * 下拉刷新商家首页。
 *
 * @returns {void}
 */
function handleRefresh() {
  if (refreshing.value) {
    return
  }
  refreshing.value = true
  loadMerchantHome()
}

/**
 * 清空页面数据。
 *
 * @returns {void}
 */
function clearPageData() {
  merchantInfo.value = { ...EMPTY_MERCHANT_INFO }
  menuMode.value = 'special'
  bannerList.value = []
  resetBannerHeight()
  liveList.value = []
  sectionList.value = []
  activeContentTab.value = 'all'
}

/**
 * 重置轮播图高度状态。
 *
 * @returns {void}
 */
function resetBannerHeight() {
  bannerActiveIndex.value = 0
  bannerAspectRatioMap.value = {}
  bannerSwiperHeightPx.value = 0
}

/**
 * 规范化商户信息。
 *
 * @param {Object} merchant 接口商户信息。
 * @returns {Object} 页面商户信息。
 */
function normalizeMerchant(merchant = {}) {
  const id = Number(merchant.id || 0)
  const followCount = Math.max(0, Number(merchant.follow_count || 0))
  const followText = String(merchant.follow_count_text || '').trim()
  return {
    id,
    name: String(merchant.name || merchant.mer_name || '').trim() || '商家店铺',
    logo: String(merchant.logo || merchant.avatar || merchant.mer_avatar || '').trim(),
    followCount,
    followText: followText || `${formatCountText(followCount)}粉丝`,
    businessStatusText: String(merchant.business_status_text || '').trim(),
    isFollowed: merchant.is_followed === true || Number(merchant.is_followed || 0) === 1,
    verified: true
  }
}

/**
 * 规范化幻灯片配置。
 *
 * @param {Array} list 接口幻灯片列表。
 * @returns {Array<Object>} 页面幻灯片列表。
 */
function normalizeBanners(list = []) {
  return Array.isArray(list)
    ? list
      .map((item, index) => ({
        key: `banner-${Number(item.id || index + 1)}-${index}`,
        id: Number(item.id || index + 1),
        image: String(item.image || '').trim(),
        linkType: String(item.link_type || '').trim(),
        linkValue: String(item.link_value || '').trim()
      }))
      .filter((item) => item.image)
    : []
}

/**
 * 规范化直播列表。
 *
 * @param {Array} list 接口直播列表。
 * @returns {Array<Object>} 页面直播列表。
 */
function normalizeLiveList(list = []) {
  return Array.isArray(list)
    ? list
      .map((item, index) => {
        const id = Number(item.id || 0)
        const anchor = item.anchor && typeof item.anchor === 'object' ? item.anchor : {}
        const status = Number(item.status || 0)
        const viewCount = Number(item.view_count || item.viewer_count || item.online_num || 0)
        return {
          key: `live-${id}-${index}`,
          id,
          merchantId: Number(item.merchant_id || 0),
          title: String(item.title || '').trim() || '直播内容待完善',
          cover: String(item.cover_image || item.cover || item.banner || item.poster || '').trim(),
          anchorName: String(item.anchor_name || anchor.name || '').trim() || '商户主播',
          anchorAvatar: String(item.anchor_avatar || anchor.avatar || '').trim(),
          statusText: status === 1 ? '直播中' : '预告中',
          viewText: `${formatCountText(viewCount)} 观看`
        }
      })
      .filter((item) => item.id > 0)
      .slice(0, 2)
    : []
}

/**
 * 规范化精选专题列表。
 *
 * @param {Array} list 接口专题列表。
 * @returns {Array<Object>} 页面专题列表。
 */
function normalizeSpecialList(list = []) {
  return Array.isArray(list)
    ? list
      .map((item, index) => {
        const id = Number(item.id || 0)
        const description = String(item.description || item.summary || '').trim()
        const priceText = String(item.price_text || '').trim()
        return {
          key: `special-${id}-${index}`,
          id,
          title: String(item.title || '').trim() || '专题标题待完善',
          description: description || buildSpecialDesc(item),
          cover: String(item.cover || item.image || '').trim(),
          priceText,
          buttonText: priceText === '免费' ? '免费学习' : '立即查看'
        }
      })
      .filter((item) => item.id > 0)
    : []
}

/**
 * 规范化推荐内容列表。
 *
 * @param {Array} list 接口内容列表。
 * @returns {Array<Object>} 页面内容列表。
 */
function normalizeContentList(list = []) {
  return Array.isArray(list)
    ? list
      .map((item, index) => {
        const id = Number(item.id || item.article_id || 0)
        const contentType = Number(item.content_type || 0)
        const viewCount = Number(item.view_count || item.views || 0)
        const likeCount = Number(item.like_count || 0)
        return {
          key: `content-${id}-${index}`,
          id,
          contentType,
          title: String(item.title || '').trim() || '内容标题待完善',
          description: String(item.description || item.summary || '').trim() || '精彩内容持续更新中',
          cover: String(item.cover || item.image || '').trim(),
          typeText: buildContentTypeText(item, contentType),
          durationText: formatDuration(item.media_duration || 0),
          viewText: `${formatCountText(viewCount)} 浏览`,
          likeText: `${formatCountText(likeCount)} 点赞`,
          learnText: String(item.learn_count_text || '').trim() || `${formatCountText(item.learn_count || 0)}人已学`,
          priceText: String(item.price_text || '').trim() || buildPriceText(item.price),
          detailUrl: String(item.detail_url || '').trim() || buildContentUrl(id, contentType)
        }
      })
      .filter((item) => item.id > 0)
    : []
}

/**
 * 生成专题简短描述。
 *
 * @param {Object} item 专题项。
 * @returns {string} 描述文案。
 */
function buildSpecialDesc(item = {}) {
  const count = Number(item.content_count || 0)
  if (count > 0) {
    return `${count}节内容 · 系统学习`
  }
  return '精选课程 · 值得收藏'
}

/**
 * 生成内容类型标签。
 *
 * @param {Object} item 内容项。
 * @param {number} contentType 内容类型。
 * @returns {string} 类型文案。
 */
function buildContentTypeText(item = {}, contentType = 0) {
  const text = String(item.content_type_text || item.type_text || '').trim()
  if (text) {
    return text === '视频' ? '视频教程' : `${text}教程`
  }
  if (Number(contentType) === 6) {
    return '视频教程'
  }
  if (Number(contentType) === 5) {
    return '音频课程'
  }
  return '图文教程'
}

/**
 * 生成内容详情地址。
 *
 * @param {number} id 内容 ID。
 * @param {number} contentType 内容类型。
 * @returns {string} 页面地址。
 */
function buildContentUrl(id, contentType) {
  const articleId = Number(id || 0)
  if (articleId <= 0) {
    return ''
  }
  if (Number(contentType || 0) === 5) {
    return `/pages/contents/content/aud/index?id=${articleId}`
  }
  if (Number(contentType || 0) === 6) {
    return `/pages/contents/content/vid/index?id=${articleId}`
  }
  if (Number(contentType || 0) === 3) {
    return `/pages/contents/content/novel/index?id=${articleId}`
  }
  return `/pages/contents/content/detail/index?id=${articleId}`
}

/**
 * 生成价格展示。
 *
 * @param {number|string} value 价格。
 * @returns {string} 价格文案。
 */
function buildPriceText(value) {
  const price = Number(value || 0)
  return price > 0 ? `¥${price.toFixed(2)}` : '免费'
}

/**
 * 应用关注接口返回数据。
 *
 * @param {Object} data 关注接口返回。
 * @returns {void}
 */
function applyFollowData(data = {}) {
  const isFollowed = data.is_followed === true || Number(data.is_followed || 0) === 1
  const followCount = Math.max(0, Number(data.follow_count || merchantInfo.value.followCount || 0))
  const followText = String(data.follow_count_text || '').trim()
  merchantInfo.value = {
    ...merchantInfo.value,
    isFollowed,
    followCount,
    followText: followText || `${formatCountText(followCount)}粉丝`
  }
}

/**
 * 点击关注或取消关注。
 *
 * @returns {Promise<void>}
 */
async function handleToggleFollow() {
  if (followLoading.value || merchantId.value <= 0) {
    return
  }

  followLoading.value = true
  try {
    const res = await toggleMerchantFollow({ merchant_id: merchantId.value }, {
      showError: false,
      redirect: `/pages/merchant/index/index?merchant_id=${merchantId.value}`
    })
    if (!res || res.code !== 200 || !res.data) {
      if (res && res.code === 401) {
        return
      }
      uni.showToast({
        title: res && res.msg ? res.msg : '关注操作失败',
        icon: 'none'
      })
      return
    }

    applyFollowData(res.data)
    uni.showToast({
      title: merchantInfo.value.isFollowed ? '已关注' : '已取消关注',
      icon: 'none'
    })
  } catch (error) {
    uni.showToast({
      title: '关注操作失败',
      icon: 'none'
    })
  } finally {
    followLoading.value = false
  }
}

/**
 * 点击幻灯片。
 *
 * @param {Object} item 幻灯片项。
 * @returns {void}
 */
function handleBannerClick(item = {}) {
  const linkType = String(item.linkType || '').trim()
  const linkValue = String(item.linkValue || '').trim()
  if (!linkType || !linkValue) {
    return
  }

  if (linkType === 'special') {
    navigateToSpecialDetail(linkValue)
    return
  }

  if (linkType === 'url' || linkType === 'page') {
    navigateToPage(linkValue)
  }
}

/**
 * 记录轮播图原始比例并更新展示高度。
 *
 * @param {Object} event 图片加载事件。
 * @param {number} index 图片索引。
 * @returns {void}
 */
function handleBannerImageLoad(event, index) {
  const detail = event && event.detail ? event.detail : {}
  const imageWidth = Number(detail.width || 0)
  const imageHeight = Number(detail.height || 0)
  const bannerIndex = Number(index || 0)
  if (imageWidth <= 0 || imageHeight <= 0 || bannerIndex < 0) {
    return
  }

  bannerAspectRatioMap.value[bannerIndex] = imageHeight / imageWidth
  if (bannerIndex === bannerActiveIndex.value) {
    updateBannerHeight()
  }
}

/**
 * 切换轮播图时使用当前图片比例更新高度。
 *
 * @param {Object} event 轮播切换事件。
 * @returns {void}
 */
function handleBannerChange(event) {
  const detail = event && event.detail ? event.detail : {}
  bannerActiveIndex.value = Number(detail.current || 0)
  updateBannerHeight()
}

/**
 * 根据轮播容器实际宽度计算图片高度。
 *
 * @returns {void}
 */
function updateBannerHeight() {
  const aspectRatio = Number(bannerAspectRatioMap.value[bannerActiveIndex.value] || 0)
  if (aspectRatio <= 0) {
    return
  }

  nextTick(() => {
    const query = uni.createSelectorQuery().in(instance.proxy)
    query.select('.merchant-banner-swiper').boundingClientRect((rect) => {
      const bannerWidth = Number(rect && rect.width ? rect.width : 0)
      if (bannerWidth > 0) {
        bannerSwiperHeightPx.value = Math.round(bannerWidth * aspectRatio)
      }
    }).exec()
  })
}

/**
 * 窗口尺寸变化时重新同步轮播高度。
 *
 * @returns {void}
 */
function handleBannerWindowResize() {
  updateBannerHeight()
}

/**
 * 进入直播间。
 *
 * @param {Object} item 直播项。
 * @returns {void}
 */
function handleLiveClick(item = {}) {
  const id = Number(item.id || 0)
  if (id <= 0) {
    return
  }
  uni.navigateTo({ url: `/pages/lives/room/index?id=${id}` })
}

/**
 * 进入专题详情。
 *
 * @param {Object} item 专题项。
 * @returns {void}
 */
function handleSpecialClick(item = {}) {
  navigateToSpecialDetail(item.id)
}

/**
 * 进入内容详情。
 *
 * @param {Object} item 内容项。
 * @returns {void}
 */
function handleContentClick(item = {}) {
  navigateToPage(item.detailUrl)
}

/**
 * 切换推荐内容标签。
 *
 * @param {Object} item 标签项。
 * @returns {void}
 */
function handleContentTab(item = {}) {
  const key = String(item.key || '').trim()
  if (key) {
    activeContentTab.value = key
  }
}

/**
 * 查看全部直播。
 *
 * @returns {void}
 */
function handleMoreLive() {
  navigateToPage(`/pages/lives/live/index?merchant_id=${merchantId.value}`)
}

/**
 * 查看全部专题。
 *
 * @returns {void}
 */
function handleMoreSpecial() {
  navigateToPage(`/pages/merchant/special/index?merchant_id=${merchantId.value}`)
}

/**
 * 查看全部内容。
 *
 * @returns {void}
 */
function handleMoreContent() {
  navigateToPage(`/pages/contents/content/list/index?merchant_id=${merchantId.value}`)
}

/**
 * 查看当前商户全部商品。
 *
 * @returns {void}
 */
function handleMoreProduct() {
  navigateToPage(`/pages/goods/list/index?merchant_id=${merchantId.value}`)
}

/**
 * 跳转专题详情。
 *
 * @param {number|string} specialId 专题 ID。
 * @returns {void}
 */
function navigateToSpecialDetail(specialId) {
  const id = Number(specialId || 0)
  if (id <= 0) {
    return
  }

  uni.navigateTo({
    url: `/pages/contents/special/detail/index?id=${id}&merchant_id=${merchantId.value}`
  })
}

/**
 * 跳转页面地址。
 *
 * @param {string} url 页面地址。
 * @returns {void}
 */
function navigateToPage(url = '') {
  const targetUrl = String(url || '').trim()
  if (!targetUrl || targetUrl.indexOf('/') !== 0) {
    return
  }

  uni.navigateTo({
    url: targetUrl,
    fail: () => {
      uni.redirectTo({ url: targetUrl })
    }
  })
}

/**
 * 格式化计数。
 *
 * @param {number|string} value 原始数量。
 * @returns {string} 展示数量。
 */
function formatCountText(value) {
  const count = Math.max(0, Number(value || 0))
  if (count >= 10000) {
    return `${(count / 10000).toFixed(count % 10000 === 0 ? 0 : 1).replace(/\.0$/, '')}万`
  }
  return String(count)
}

/**
 * 格式化媒体时长。
 *
 * @param {number|string} value 秒数。
 * @returns {string} 时长文案。
 */
function formatDuration(value) {
  const totalSeconds = Math.max(0, Number(value || 0))
  if (totalSeconds <= 0) {
    return ''
  }
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = Math.floor(totalSeconds % 60)
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}
</script>

<style lang="scss">
page {
  height: 100%;
  background: #ffffff;
}
</style>

<style lang="scss" scoped>
.merchant-index-page {
  height: 100%;
  min-height: 100%;
  background: #ffffff;
  display: flex;
  flex-direction: column;
}

.merchant-index-scroll {
  flex: 1;
  min-height: 0;
}

.merchant-index-scroll__inner {
  min-height: 100%;
  background: #ffffff;
}

.merchant-profile-section {
  padding: 20rpx 26rpx 28rpx;
  background: #ffffff;
}

.merchant-profile-card,
.merchant-profile-skeleton {
  min-height: 116rpx;
  display: flex;
  align-items: center;
}

.merchant-profile-card__logo-shell,
.merchant-profile-skeleton__logo {
  width: 112rpx;
  height: 112rpx;
  border-radius: 50%;
  background: #f3f4f6;
  overflow: visible;
  flex-shrink: 0;
  position: relative;
}

.merchant-profile-card__logo-shell {
  box-shadow: 0 10rpx 24rpx rgba(15, 23, 42, 0.08);
}

.merchant-profile-card__logo {
  width: 112rpx;
  height: 112rpx;
  border-radius: 50%;
  display: block;
}

.merchant-profile-card__logo-icon {
  width: 112rpx;
  height: 112rpx;
  border-radius: 50%;
  background: var(--brand-primary-pale, #fff7ed);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 52rpx;
  line-height: 52rpx;
  color: var(--brand-primary, #ea580c);
}

.merchant-profile-card__logo-badge {
  position: absolute;
  right: 0;
  bottom: 2rpx;
  width: 34rpx;
  height: 34rpx;
  border-radius: 50%;
  border: 4rpx solid #ffffff;
  background: #c8a01a;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.merchant-profile-card__logo-badge-icon {
  font-size: 18rpx;
  line-height: 18rpx;
  color: #ffffff;
}

.merchant-profile-card__content,
.merchant-profile-skeleton__content {
  flex: 1;
  min-width: 0;
  margin-left: 28rpx;
}

.merchant-profile-card__name-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.merchant-profile-card__name {
  flex: 1;
  min-width: 0;
  max-width: 360rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 40rpx;
  line-height: 52rpx;
  font-weight: 800;
  color: #111827;
}

.merchant-profile-card__meta {
  margin-top: 10rpx;
  display: flex;
  align-items: center;
}

.merchant-profile-card__followers,
.merchant-profile-card__status {
  font-size: 28rpx;
  line-height: 36rpx;
  font-weight: 500;
  color: #6b7280;
}

.merchant-profile-card__status {
  margin-left: 34rpx;
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.merchant-profile-card__follow,
.merchant-profile-skeleton__button {
  width: 128rpx;
  height: 56rpx;
  border-radius: 999rpx;
  flex-shrink: 0;
}

.merchant-profile-card__follow {
  margin-left: auto;
  background: var(--brand-primary, #ea580c);
  box-shadow: 0 10rpx 22rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.merchant-profile-card__follow--active {
  background: #f3f4f6;
  box-shadow: none;
}

.merchant-profile-card__follow--hover {
  opacity: 0.88;
}

.merchant-profile-card__follow-plus {
  display: block;
  margin-right: 6rpx;
  font-size: 30rpx;
  line-height: 30rpx;
  font-weight: 700;
  color: #ffffff;
}

.merchant-profile-card__follow-text {
  display: block;
  font-size: 26rpx;
  line-height: 30rpx;
  font-weight: 800;
  color: #ffffff;
}

.merchant-profile-card__follow--active .merchant-profile-card__follow-text {
  color: #6b7280;
}

.merchant-profile-skeleton__logo,
.merchant-profile-skeleton__line,
.merchant-profile-skeleton__button {
  background: #f1f1f1;
}

.merchant-profile-skeleton__line {
  height: 30rpx;
  border-radius: 999rpx;
}

.merchant-profile-skeleton__line--title {
  width: 260rpx;
}

.merchant-profile-skeleton__line--meta {
  width: 210rpx;
  margin-top: 20rpx;
}

.merchant-error {
  min-height: 220rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.merchant-error__title {
  font-size: 28rpx;
  line-height: 40rpx;
  color: #6b7280;
}

.merchant-error__button {
  margin-top: 22rpx;
  min-width: 164rpx;
  height: 60rpx;
  padding: 0 28rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.merchant-error__button--hover {
  opacity: 0.88;
}

.merchant-error__button-text {
  font-size: 24rpx;
  line-height: 28rpx;
  font-weight: 700;
  color: #ffffff;
}

.merchant-main-section {
  padding: 0 26rpx;
  background: #ffffff;
}

.merchant-banner-swiper {
  height: 240rpx;
  border-radius: 20rpx;
  overflow: hidden;
  background: #f3f4f6;
  box-shadow: 0 14rpx 34rpx rgba(15, 23, 42, 0.08);
}

.merchant-banner-card {
  width: 100%;
  height: 100%;
  border-radius: 20rpx;
  overflow: hidden;
}

.merchant-banner-card--hover {
  opacity: 0.92;
}

.merchant-banner-card__image {
  width: 100%;
  height: 100%;
  display: block;
}

.merchant-section {
  margin-top: 22rpx;
}

.merchant-section--live {
  margin-top: 46rpx;
}

.merchant-section__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.merchant-section__title-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
}

.merchant-section__header--content .merchant-section__title-wrap {
  flex: 0 0 auto;
}

.merchant-section__icon {
  width: 34rpx;
  height: 34rpx;
  margin-right: 10rpx;
  flex-shrink: 0;
}

.merchant-section__product-icon {
  flex-shrink: 0;
  width: 34rpx;
  margin-right: 10rpx;
  font-size: 34rpx;
  line-height: 34rpx;
  color: var(--brand-primary, #ea580c);
}

.merchant-section--product {
  margin-left: -6rpx;
  margin-right: -6rpx;
}

.merchant-section--product .merchant-section__header {
  padding: 0 6rpx;
}

.merchant-section__title {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 800;
  color: #111827;
}

.merchant-section__more {
  padding-left: 28rpx;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.merchant-section__more--hover {
  opacity: 0.72;
}

.merchant-section__more-text,
.merchant-section__more-icon {
  font-size: 24rpx;
  line-height: 32rpx;
  font-weight: 700;
  color: #6b7280;
}

.merchant-section__more-icon {
  margin-left: 4rpx;
}

.live-list {
  margin-top: 22rpx;
  display: flex;
  align-items: stretch;
}

.live-card {
  width: 320rpx;
  border-radius: 14rpx;
  background: #ffffff;
  overflow: hidden;
  box-shadow: 0 10rpx 28rpx rgba(15, 23, 42, 0.1);
}

.live-card + .live-card {
  margin-left: 28rpx;
}

.live-card--hover {
  opacity: 0.9;
}

.live-card__cover {
  height: 142rpx;
  background: #f3f4f6;
  position: relative;
  overflow: hidden;
}

.live-card__image,
.live-card__placeholder {
  width: 100%;
  height: 100%;
}

.live-card__image {
  display: block;
}

.live-card__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
}

.live-card__placeholder-icon {
  font-size: 52rpx;
  line-height: 52rpx;
  color: #c4c4c4;
}

.live-card__badge {
  position: absolute;
  left: 12rpx;
  top: 12rpx;
  height: 34rpx;
  padding: 0 12rpx;
  border-radius: 8rpx;
  background: var(--brand-primary, #ea580c);
  display: flex;
  align-items: center;
}

.live-card__badge-icon {
  margin-right: 6rpx;
  font-size: 18rpx;
  line-height: 18rpx;
  color: #ffffff;
}

.live-card__badge-text {
  font-size: 22rpx;
  line-height: 28rpx;
  font-weight: 700;
  color: #ffffff;
}

.live-card__views {
  position: absolute;
  left: 14rpx;
  bottom: 12rpx;
  height: 34rpx;
  padding: 0 12rpx;
  border-radius: 999rpx;
  background: rgba(17, 24, 39, 0.48);
  display: flex;
  align-items: center;
}

.live-card__views-icon,
.live-card__views-text {
  font-size: 22rpx;
  line-height: 28rpx;
  color: #ffffff;
}

.live-card__views-icon {
  margin-right: 6rpx;
}

.live-card__body {
  min-height: 76rpx;
  padding: 14rpx 16rpx 16rpx;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}

.live-card__avatar {
  width: 52rpx;
  height: 52rpx;
  border-radius: 50%;
  background: #f3f4f6;
  flex-shrink: 0;
}

.live-card__avatar--fallback {
  display: flex;
  align-items: center;
  justify-content: center;
}

.live-card__avatar-icon {
  font-size: 26rpx;
  line-height: 26rpx;
  color: #9ca3af;
}

.live-card__info {
  flex: 1;
  min-width: 0;
  margin-left: 14rpx;
}

.live-card__title,
.live-card__anchor {
  display: block;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.live-card__title {
  font-size: 26rpx;
  line-height: 34rpx;
  font-weight: 700;
  color: #111827;
}

.live-card__anchor {
  margin-top: 4rpx;
  font-size: 22rpx;
  line-height: 28rpx;
  color: #6b7280;
}

.special-scroll,
.content-tabs {
  width: 100%;
  margin-top: 22rpx;
  white-space: nowrap;
}

.special-scroll__inner,
.content-tabs__inner {
  white-space: nowrap;
}

.special-scroll__inner--vertical {
  display: flex;
  flex-direction: column;
  white-space: normal;
}

.special-scroll__inner--vertical .special-card {
  width: 100%;
  height: 260rpx;
  margin: 0 0 24rpx;
  flex-shrink: 0;
}

.special-scroll__inner--vertical .special-card__title {
  font-size: 28rpx;
  line-height: 40rpx;
}

.special-scroll__inner--vertical .special-card__desc {
  font-size: 24rpx;
  line-height: 34rpx;
}

.special-card {
  width: 248rpx;
  height: 200rpx;
  margin-right: 20rpx;
  border-radius: 14rpx;
  background: #f3f4f6;
  overflow: hidden;
  display: inline-flex;
  position: relative;
  vertical-align: top;
  box-shadow: 0 8rpx 22rpx rgba(15, 23, 42, 0.08);
}

.special-card--hover {
  opacity: 0.9;
}

.special-card__image,
.special-card__placeholder {
  width: 100%;
  height: 100%;
}

.special-card__image {
  display: block;
}

.special-card__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
}

.special-card__placeholder-icon {
  font-size: 42rpx;
  line-height: 42rpx;
  color: #c4c4c4;
}

.special-card__mask {
  position: absolute;
  left: 0;
  top: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.34);
}

.special-card__content {
  position: absolute;
  left: 16rpx;
  top: 14rpx;
  right: 12rpx;
  bottom: 12rpx;
}

.special-card__title,
.special-card__desc {
  display: block;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.special-card__title {
  font-size: 26rpx;
  line-height: 32rpx;
  font-weight: 800;
  color: #111827;
}

.special-card__desc {
  margin-top: 2rpx;
  font-size: 18rpx;
  line-height: 24rpx;
  color: #6b7280;
}

.special-card__button {
  position: absolute;
  left: 0;
  bottom: 0;
  height: 28rpx;
  padding: 0 12rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  display: flex;
  align-items: center;
}

.special-card__button-text {
  font-size: 18rpx;
  line-height: 22rpx;
  font-weight: 700;
  color: #ffffff;
}

.content-tabs {
  margin-top: 20rpx;
}

.content-tabs__item {
  min-width: 96rpx;
  height: 44rpx;
  padding: 0 20rpx;
  margin-right: 14rpx;
  border-radius: 999rpx;
  background: #f3f4f6;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: top;
  box-sizing: border-box;
}

.content-tabs__item--active {
  background: var(--brand-primary, #ea580c);
}

.content-tabs__item--hover {
  opacity: 0.82;
}

.content-tabs__text {
  font-size: 22rpx;
  line-height: 28rpx;
  font-weight: 700;
  color: #8b8f99;
}

.content-tabs__text--active {
  color: #ffffff;
}

.content-grid {
  margin-top: 26rpx;
  display: flex;
  flex-wrap: wrap;
}

.content-card {
  width: calc(50% - 10rpx);
  min-width: 0;
  box-sizing: border-box;
  margin-bottom: 26rpx;
  border-radius: 14rpx;
  background: #ffffff;
  overflow: hidden;
  box-shadow: 0 10rpx 28rpx rgba(15, 23, 42, 0.1);
}

.content-card--even {
  margin-left: 20rpx;
}

.content-card--hover {
  opacity: 0.9;
}

.content-card__cover {
  height: 230rpx;
  background: #f3f4f6;
  position: relative;
  overflow: hidden;
}

.content-card__image,
.content-card__placeholder {
  width: 100%;
  height: 100%;
}

.content-card__image {
  display: block;
}

.content-card__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
}

.content-card__placeholder-icon {
  font-size: 56rpx;
  line-height: 56rpx;
  color: #c4c4c4;
}

.content-card__badge {
  position: absolute;
  left: 12rpx;
  top: 12rpx;
  height: 36rpx;
  padding: 0 12rpx;
  border-radius: 8rpx;
  background: var(--brand-primary, #ea580c);
  display: flex;
  align-items: center;
}

.content-card__badge-text {
  font-size: 22rpx;
  line-height: 28rpx;
  font-weight: 800;
  color: #ffffff;
}

.content-card__duration {
  position: absolute;
  right: 12rpx;
  bottom: 12rpx;
  height: 42rpx;
  min-width: 66rpx;
  padding: 0 12rpx;
  border-radius: 10rpx;
  background: rgba(17, 24, 39, 0.66);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.content-card__duration-text {
  font-size: 24rpx;
  line-height: 30rpx;
  font-weight: 700;
  color: #ffffff;
}

.content-card__body {
  padding: 20rpx 18rpx 18rpx;
}

.content-card__title {
  display: -webkit-box;
  height: 72rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  font-size: 28rpx;
  line-height: 36rpx;
  font-weight: 800;
  color: #111827;
}

.content-card__desc {
  display: -webkit-box;
  height: 66rpx;
  margin-top: 12rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 22rpx;
  line-height: 33rpx;
  color: #6b7280;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.content-card__stats {
  height: 34rpx;
  display: flex;
  align-items: center;
}

.content-card__stat {
  max-width: 150rpx;
  display: flex;
  align-items: center;
}

.content-card__stat--like {
  margin-left: 20rpx;
}

.content-card__stat-icon,
.content-card__stat-text {
  font-size: 22rpx;
  line-height: 28rpx;
  color: #6b7280;
}

.content-card__stat-icon {
  margin-right: 6rpx;
  flex-shrink: 0;
}

.content-card__stat-text {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.content-card__footer {
  margin-top: 18rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.content-card__price {
  font-size: 28rpx;
  line-height: 36rpx;
  font-weight: 800;
  color: var(--brand-primary, #ea580c);
}

.content-card__learn {
  max-width: 150rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 22rpx;
  line-height: 30rpx;
  color: #6b7280;
}

.content-tab-empty,
.merchant-empty {
  min-height: 180rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.content-tab-empty__text,
.merchant-empty__title {
  font-size: 26rpx;
  line-height: 36rpx;
  color: #9ca3af;
}

.merchant-index-scroll__bottom-space {
  height: 144rpx;
}

.merchant-load-state {
  min-height: 64rpx;
  margin-top: 12rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.merchant-load-state__text {
  max-width: 100%;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #71717a);
  text-align: center;
  word-break: break-all;
}

/* #ifdef MP-WEIXIN */
.merchant-index-scroll__bottom-space {
  height: calc(144rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
