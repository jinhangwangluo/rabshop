<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="novel-detail-page">
    <view class="novel-detail-page__safe-top">
      <app-safe-top :title="pageTitle" background="transparent" mp-extra="8rpx" :show-back="true" emit-back icon-color="#1a1c1c" @back="handleBack" />
    </view>

    <view v-if="loading" class="novel-state">
      <text class="novel-state__text">加载中...</text>
    </view>

    <view v-else-if="errorMessage" class="novel-state">
      <text class="novel-state__text">{{ errorMessage }}</text>
      <view class="novel-state__button" @click="fetchDetail">
        <text>重新加载</text>
      </view>
    </view>

    <scroll-view v-else scroll-y class="novel-detail-page__scroll" :show-scrollbar="false">
      <view class="novel-detail-page__content">
        <view class="novel-header">
          <text class="novel-header__title">{{ detail.title }}</text>
          <view class="novel-header__meta">
            <text v-if="detail.wordCountText" class="novel-header__meta-item">字数：{{ detail.wordCountText }}</text>
            <text v-if="detail.readDurationText" class="novel-header__meta-item">阅读时长：{{ detail.readDurationText }}</text>
            <text v-if="detail.chapterCountText" class="novel-header__meta-item">章节：{{ detail.chapterCountText }}</text>
          </view>
        </view>

        <view v-if="displayContent" class="novel-reading">
          <rich-text class="novel-reading__content" :nodes="displayContent"></rich-text>
        </view>
        <view v-else class="novel-empty">
          <text class="novel-empty__text">{{ emptyContentText }}</text>
        </view>

        <view v-if="showPaywall" class="novel-paywall">
          <view class="novel-paywall__fade"></view>
          <view class="unlock-card">
            <view class="unlock-card__lock">
              <text class="iconfont icon-lock unlock-card__lock-icon"></text>
            </view>
            <text class="unlock-card__title">解锁全文，探索精彩后续</text>
            <view class="unlock-card__price-row">
              <text class="unlock-card__price-symbol">¥</text>
              <text class="unlock-card__price-value">{{ detail.price }}</text>
            </view>
            <view class="unlock-card__primary" @click="handleUnlock">{{ buyLoading ? '处理中...' : '立即解锁' }}</view>
            <view class="unlock-card__secondary" @click="handleMemberUnlock">加入会员免费看</view>
          </view>
        </view>

        <view v-if="showChapterNavigation" class="chapter-actions">
          <view class="chapter-actions__row">
            <view
              class="chapter-actions__button chapter-actions__button--prev"
              :class="{ 'chapter-actions__button--disabled': !prevChapter }"
              @click="handlePrevChapter"
            >上一章</view>
            <view
              class="chapter-actions__button chapter-actions__button--next"
              :class="{ 'chapter-actions__button--disabled': !nextChapter }"
              @click="handleNextChapter"
            >下一章</view>
          </view>
          <text class="chapter-actions__tip">{{ chapterTipText }}</text>
        </view>
      </view>
    </scroll-view>

    <view v-if="!loading && !errorMessage && showChapterNavigation" class="novel-progress-bar">
      <view class="novel-progress-bar__track">
        <view class="novel-progress-bar__value" :style="progressBarStyle"></view>
      </view>
      <view class="novel-progress-bar__body">
        <view
          class="novel-progress-bar__action"
          :class="{ 'novel-progress-bar__action--disabled': !prevChapter }"
          @click="handlePrevChapter"
        >
          <text class="iconfont icon-shangyiqu novel-progress-bar__action-icon"></text>
          <text class="novel-progress-bar__action-text">上一章</text>
        </view>
        <view class="novel-progress-bar__center">
          <text class="novel-progress-bar__percent">{{ detail.progressText }}</text>
          <text class="novel-progress-bar__label">当前进度</text>
        </view>
        <view
          class="novel-progress-bar__action"
          :class="{ 'novel-progress-bar__action--disabled': !nextChapter }"
          @click="handleNextChapter"
        >
          <text class="iconfont icon-xiayiqu novel-progress-bar__action-icon"></text>
          <text class="novel-progress-bar__action-text">下一章</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { buyNow } from '@/api/cart'
import { getContentDetail } from '@/api/content'

const NOVEL_CONTENT_TYPE = 3
const CONTENT_ORDER_TYPE = 1
const SPECIAL_ORDER_TYPE = 3
const DEFAULT_PROGRESS_PERCENT = 0

function formatCount(count = 0) {
  const num = Number(count || 0)
  if (num >= 10000) {
    return (num / 10000).toFixed(1).replace(/\.0$/, '') + '万'
  }
  return num > 0 ? String(num) : ''
}

function formatMoney(value = 0) {
  const amount = Number(value || 0)
  if (amount <= 0) {
    return '0'
  }
  return amount.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
}

function estimateReadDuration(wordCount = 0) {
  const count = Number(wordCount || 0)
  if (count <= 0) {
    return ''
  }
  return `${Math.max(Math.ceil(count / 300), 1)}分钟`
}

function normalizeBoolean(value) {
  return value === true || value === 1 || value === '1'
}

function resolveDetailPayload(payload = {}) {
  if (payload && typeof payload === 'object' && payload.content_type !== undefined) {
    return payload
  }

  if (payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object') {
    return payload.data
  }

  return payload || {}
}

function escapeRichText(text = '') {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function normalizeRichText(content = '') {
  const source = String(content || '').trim()
  if (!source) {
    return ''
  }
  if (/<[a-z][\s\S]*>/i.test(source)) {
    return source
  }

  return source
    .split(/\n+/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean)
    .map((paragraph) => `<p>${escapeRichText(paragraph)}</p>`)
    .join('')
}

function collectCartIds(payload = {}) {
  const ids = []
  const cartId = Number(payload.cart_id || payload.id || 0)
  if (cartId > 0) {
    ids.push(cartId)
  }

  const cart = payload.cart && typeof payload.cart === 'object' ? payload.cart : {}
  const nestedCartId = Number(cart.cart_id || cart.id || 0)
  if (nestedCartId > 0) {
    ids.push(nestedCartId)
  }

  const preview = payload.preview && typeof payload.preview === 'object' ? payload.preview : {}
  const groups = Array.isArray(preview.groups) ? preview.groups : []
  groups.forEach((group) => {
    const groupIds = Array.isArray(group.cart_ids) ? group.cart_ids : []
    groupIds.forEach((id) => {
      const parsed = Number(id || 0)
      if (parsed > 0) {
        ids.push(parsed)
      }
    })
  })

  return Array.from(new Set(ids))
}

defineOptions({
  name: 'ContentNovelPage'
})

const pageTitle = ref('小说详情')
const articleId = ref(0)
const entrySource = ref('')
const specialId = ref(0)
const chapterPage = ref(1)
const chapterLimit = ref(20)
const specialChapterContext = ref(null)
const detail = ref({
  id: 0,
  title: '',
  price: '0',
  wordCountText: '',
  readDurationText: '',
  chapterCountText: '',
  progressPercent: DEFAULT_PROGRESS_PERCENT,
  progressText: '0%',
  chapterProgressText: '第1/0章'
})
const displayContent = ref('')
const loading = ref(true)
const errorMessage = ref('')
const isUnlocked = ref(false)
const showPaywall = ref(false)
const buyLoading = ref(false)
const hasLoaded = ref(false)
const emptyContentText = ref('暂无小说内容')
const progressBarStyle = computed(() => {
  return `width: ${Number(detail.value.progressPercent || 0)}%;`
})
const chapterTipText = computed(() => {
  const prefix = isUnlocked.value ? '当前章节' : '本章未解锁'
  return `${prefix} · ${detail.value.chapterProgressText}`
})
const isFromSpecial = computed(() => {
  return specialId.value > 0
})
const showChapterNavigation = computed(() => {
  const context = specialChapterContext.value || {}
  return isFromSpecial.value && Number(context.total_all || 0) > 1
})
const prevChapter = computed(() => {
  const context = specialChapterContext.value || {}
  return context.prev_chapter || null
})
const nextChapter = computed(() => {
  const context = specialChapterContext.value || {}
  return context.next_chapter || null
})
onLoad((options = {}) => {
  initPage(options)
})
onShow(() => {
  if (hasLoaded.value && articleId.value && !loading.value) {
    fetchDetail()
  }
})
/**
 * 初始化页面参数并拉取详情。
 *
 * @param {Object} options 页面入参。
 * @returns {void}
 */
function initPage(options = {}) {
  articleId.value = Number(options.id || 0)
  entrySource.value = String(options.source || '').trim()
  specialId.value = Number(options.special_id || options.specialId || 0)
  chapterPage.value = Math.max(1, Number(options.chapter_page || options.chapterPage || 1))
  chapterLimit.value = Math.max(1, Math.min(20, Number(options.chapter_limit || options.chapterLimit || 20)))
  syncPageTitle('小说详情')
  fetchDetail()
}

/**
 * 获取小说详情。
 *
 * @returns {Promise<void>}
 */
async function fetchDetail() {
  if (!articleId.value) {
    errorMessage.value = '内容ID不能为空'
    loading.value = false
    return
  }

  loading.value = true
  errorMessage.value = ''

  try {
    const params = { id: articleId.value }
    if (isFromSpecial.value) {
      params.special_id = specialId.value
      params.chapter_page = chapterPage.value
      params.chapter_limit = chapterLimit.value
    }

    const res = await getContentDetail(params, {
      showError: false,
      loginRedirect: false
    })

    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '小说加载失败'
      return
    }

    const detail = resolveDetailPayload(res.data)
    if (Number(detail.content_type || 0) !== NOVEL_CONTENT_TYPE) {
      errorMessage.value = '不支持的内容类型'
      return
    }

    applyDetail(detail)
  } catch (error) {
    errorMessage.value = '小说加载失败'
  } finally {
    hasLoaded.value = true
    loading.value = false
  }
}

/**
 * 将接口字段转换为页面展示字段。
 *
 * @param {Object} data 接口返回的小说详情。
 * @returns {void}
 */
function applyDetail(data = {}) {
  const wordCount = Number(data.word_count || 0)
  const chapterCount = Number(data.chapter_count || 0)
  const isUnlocked = String(data.access_state || '') === 'learning' || normalizeBoolean(data.can_learn)
  const trialContent = String(data.trial_content || '').trim()
  const fullContent = String(data.content || '').trim()
  const richTextContent = normalizeRichText(isUnlocked ? fullContent : trialContent)
  const specialContext = data.special_chapter_context && typeof data.special_chapter_context === 'object'
    ? data.special_chapter_context
    : null
  const currentNumber = Number(specialContext && specialContext.current_number || 1)
  const totalChapters = Number(specialContext && specialContext.total_all || chapterCount || 0)
  const progressPercent = totalChapters > 0
    ? Math.min(100, Math.max(0, Math.round((currentNumber / totalChapters) * 100)))
    : DEFAULT_PROGRESS_PERCENT
  const title = String(data.title || '').trim() || '小说详情'

  isUnlocked.value = isUnlocked
  showPaywall.value = !isUnlocked && normalizeBoolean(data.can_buy)
  displayContent.value = richTextContent
  emptyContentText.value = showPaywall.value ? '购买后可阅读全文' : '暂无小说内容'
  specialChapterContext.value = specialContext
  if (specialContext && Number(specialContext.page || 0) > 0) {
    chapterPage.value = Number(specialContext.page || 1)
  }
  detail.value = {
    id: Number(data.id || 0),
    title,
    price: formatMoney(specialContext ? specialContext.price : data.price),
    wordCountText: formatCount(wordCount),
    readDurationText: estimateReadDuration(wordCount),
    chapterCountText: totalChapters > 0 ? `${totalChapters}章` : '',
    progressPercent,
    progressText: `${progressPercent}%`,
    chapterProgressText: totalChapters > 0 ? `第${currentNumber}/${totalChapters}章` : '第1/0章'
  }
  syncPageTitle(title)
}

/**
 * 同步自定义导航标题与原生页面标题。
 *
 * @param {string} title 页面标题。
 * @returns {void}
 */
function syncPageTitle(title = '') {
  const nextTitle = String(title || '').trim() || '小说详情'
  pageTitle.value = nextTitle
  uni.setNavigationBarTitle({
    title: nextTitle
  })
}

/**
 * 返回上一页。
 *
 * @returns {void}
 */
function handleBack() {
  const pages = getCurrentPages()
  if (Array.isArray(pages) && pages.length > 1) {
    uni.navigateBack()
    return
  }
  uni.switchTab({ url: '/pages/category/index' })
}

/**
 * 创建内容立即购买临时购物车并进入确认页。
 *
 * @returns {Promise<void>}
 */
async function handleUnlock() {
  if (buyLoading.value || !showPaywall.value) {
    return
  }

  buyLoading.value = true
  try {
    const res = await buyNow({
      goods_id: isFromSpecial.value
        ? Number(specialId.value || 0)
        : Number(detail.value.id || articleId.value || 0),
      cart_num: 1,
      product_attr_unique: '',
      order_type: isFromSpecial.value ? SPECIAL_ORDER_TYPE : CONTENT_ORDER_TYPE
    })

    if (!res || res.code !== 200 || !res.data) {
      return
    }

    const cartIds = collectCartIds(res.data)
    if (!cartIds.length) {
      uni.showToast({ title: '未获取到结算商品', icon: 'none' })
      return
    }

    uni.navigateTo({
      url: `/pages/orders/confirm/index?cart_ids=${encodeURIComponent(cartIds.join(','))}&is_new=1`
    })
  } catch (error) {
    console.error('[novel-detail] handleUnlock failed', error)
  } finally {
    buyLoading.value = false
  }
}

/**
 * 模拟会员解锁。
 *
 * @returns {void}
 */
function handleMemberUnlock() {
  uni.showToast({ title: '会员解锁开发中', icon: 'none' })
}

/**
 * 切换到专题中的上一章。
 *
 * @returns {void}
 */
function handlePrevChapter() {
  openChapter(prevChapter.value, '已经是第一章')
}

/**
 * 切换到专题中的下一章。
 *
 * @returns {void}
 */
function handleNextChapter() {
  openChapter(nextChapter.value, '已经是最后一章')
}

/**
 * 打开指定专题章节。
 *
 * @param {Object|null} chapter 章节项。
 * @param {string} emptyText 无章节时的提示。
 * @returns {void}
 */
function openChapter(chapter, emptyText = '') {
  const articleId = Number(chapter && (chapter.article_id || chapter.id) || 0)
  if (!showChapterNavigation.value || articleId <= 0) {
    uni.showToast({ title: emptyText || '暂无可切换章节', icon: 'none' })
    return
  }

  uni.redirectTo({
    url: `/pages/contents/content/novel/index?id=${articleId}&source=special&special_id=${specialId.value}&chapter_page=${chapterPage.value}&chapter_limit=${chapterLimit.value}`
  })
}
</script>

<style lang="scss">
page {
  background: #f4f1ea;
}
</style>

<style scoped lang="scss">
.novel-detail-page {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  background: #f4f1ea;
  color: #1a1c1c;
}

.novel-detail-page__safe-top {
  flex-shrink: 0;
}

.novel-detail-page__scroll {
  flex: 1;
  min-height: 0;
}

.novel-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx 40rpx;
  box-sizing: border-box;
}

.novel-state__text {
  font-size: 28rpx;
  line-height: 40rpx;
  color: #7f776f;
  text-align: center;
}

.novel-state__button {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 180rpx;
  height: 72rpx;
  margin-top: 28rpx;
  padding: 0 32rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  font-size: 26rpx;
  font-weight: 700;
  color: #ffffff;
}

.novel-detail-page__content {
  padding: 8rpx 32rpx 260rpx;
  box-sizing: border-box;
}

.novel-header {
  margin-bottom: 52rpx;
  text-align: center;
}

.novel-header__title {
  display: block;
  font-size: 42rpx;
  font-weight: 700;
  line-height: 58rpx;
  color: #1a1c1c;
}

.novel-header__meta {
  margin-top: 12rpx;
}

.novel-header__meta-item {
  display: inline-block;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #7f776f;
}

.novel-header__meta-item + .novel-header__meta-item {
  margin-left: 28rpx;
}

.novel-reading__content {
  display: block;
  font-size: 36rpx;
  line-height: 70rpx;
  letter-spacing: 2rpx;
  color: #2d2824;
  text-align: justify;
}

.novel-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 280rpx;
  padding: 48rpx 24rpx;
  box-sizing: border-box;
}

.novel-empty__text {
  font-size: 28rpx;
  line-height: 42rpx;
  color: #8e867f;
  text-align: center;
}

.novel-paywall {
  position: relative;
  margin: 28rpx -32rpx 0;
  padding: 260rpx 32rpx 0;
}

.novel-paywall__fade {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 360rpx;
  background: linear-gradient(180deg, rgba(244, 241, 234, 0) 0%, rgba(244, 241, 234, 0.82) 42%, #f4f1ea 100%);
}

.unlock-card {
  position: relative;
  padding: 48rpx 40rpx 36rpx;
  border-radius: 32rpx;
  background: #ffffff;
  box-shadow: 0 20rpx 50rpx rgba(0, 0, 0, 0.1);
  text-align: center;
}

.unlock-card__lock {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 112rpx;
  height: 112rpx;
  margin: 0 auto 28rpx;
  border-radius: 50%;
  background: #f7f7f5;
}

.unlock-card__lock-icon {
  font-size: 42rpx;
  line-height: 1;
  color: #1a1c1c;
}

.unlock-card__title {
  display: block;
  font-size: 42rpx;
  font-weight: 700;
  line-height: 58rpx;
  color: #1a1c1c;
}

.unlock-card__price-row {
  margin-top: 20rpx;
}

.unlock-card__price-symbol,
.unlock-card__price-value {
  display: inline-block;
  line-height: 1;
  color: var(--brand-primary, #ea580c);
}

.unlock-card__price-symbol {
  font-size: 34rpx;
  font-weight: 700;
}

.unlock-card__price-value {
  margin-left: 8rpx;
  font-size: 72rpx;
  font-weight: 800;
}

.unlock-card__primary {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 92rpx;
  margin-top: 40rpx;
  border-radius: 24rpx;
  background: var(--brand-primary, #ea580c);
  font-size: 30rpx;
  font-weight: 700;
  color: #ffffff;
  box-shadow: 0 14rpx 28rpx rgba(243, 90, 0, 0.24);
}

.unlock-card__secondary {
  margin-top: 28rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: #8e867f;
}

.chapter-actions {
  margin-top: 36rpx;
}

.chapter-actions__row {
  display: flex;
  align-items: center;
}

.chapter-actions__button {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  height: 88rpx;
  border-radius: 999rpx;
  font-size: 28rpx;
  font-weight: 700;
}

.chapter-actions__button--prev {
  background: rgba(255, 255, 255, 0.66);
  color: #948d86;
}

.chapter-actions__button--next {
  margin-left: 20rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  box-shadow: 0 12rpx 24rpx rgba(243, 90, 0, 0.18);
}

.chapter-actions__button--disabled {
  opacity: 0.42;
}

.chapter-actions__tip {
  display: block;
  margin-top: 18rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #a39b92;
  text-align: center;
}

.novel-progress-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  padding: 12rpx 32rpx 18rpx;
  box-sizing: border-box;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 -2rpx 14rpx rgba(0, 0, 0, 0.06);
}

.novel-progress-bar__track {
  height: 6rpx;
  border-radius: 999rpx;
  background: rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.novel-progress-bar__value {
  height: 100%;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
}

.novel-progress-bar__body {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 14rpx;
}

.novel-progress-bar__action {
  width: 120rpx;
  text-align: center;
}

.novel-progress-bar__action--disabled {
  opacity: 0.38;
}

.novel-progress-bar__action-icon {
  display: block;
  font-size: 28rpx;
  line-height: 1;
  color: #b0aaa3;
}

.novel-progress-bar__action-text {
  display: block;
  margin-top: 6rpx;
  font-size: 18rpx;
  line-height: 24rpx;
  color: #b0aaa3;
}

.novel-progress-bar__center {
  flex: 1;
  text-align: center;
}

.novel-progress-bar__percent {
  display: inline-block;
  font-size: 34rpx;
  font-weight: 700;
  line-height: 40rpx;
  color: var(--brand-primary, #ea580c);
}

.novel-progress-bar__label {
  display: inline-block;
  margin-left: 12rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #9f978f;
}

::v-deep .app-safe-top__nav {
  min-height: 80rpx;
  padding: 0 112rpx;
}

::v-deep .app-safe-top__back {
  left: 16rpx;
  width: 64rpx;
  height: 64rpx;
  border-radius: 40rpx;
  background: rgba(255, 255, 255, 0.58);
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.04);
}

/* #ifdef H5 */
.novel-detail-page__content {
  padding-bottom: calc(var(--window-bottom) + 260rpx);
}

.novel-progress-bar {
  padding-bottom: calc(var(--window-bottom) + 18rpx);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.novel-detail-page__content {
  padding-bottom: calc(env(safe-area-inset-bottom) + 260rpx);
}

.novel-progress-bar {
  padding-bottom: calc(env(safe-area-inset-bottom) + 18rpx);
}
/* #endif */
</style>
