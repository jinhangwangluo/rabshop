<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="special-detail-page">
    <scroll-view v-if="!loading && !errorMessage" scroll-y class="special-detail-page__scroll" :show-scrollbar="false">
      <view class="special-detail-page__body" :class="{ 'special-detail-page__body--learning': isLearningState }">
        <view class="special-hero" :class="heroToneClass">
          <image class="special-hero__image" :src="detail.image" mode="aspectFill" />
          <view class="special-hero__mask"></view>
          <view class="special-hero__back" @click="handleGoBack">
            <text class="iconfont icon-back special-hero__back-icon"></text>
          </view>
          <view class="special-hero__content">
            <text class="special-hero__badge">{{ detail.badge }}</text>
            <text class="special-hero__title">{{ detail.title }}</text>
            <text class="special-hero__desc">{{ detail.summary }}</text>
          </view>
        </view>

        <view class="special-stats">
          <view v-for="item in detail.stats" :key="item.label" class="special-stats__item">
            <text class="special-stats__value">{{ item.value }}</text>
            <text class="special-stats__label">{{ item.label }}</text>
          </view>
        </view>

        <view class="special-section">
          <view class="special-section__head">
            <text class="special-section__title">专题内容</text>
            <text class="special-section__meta">共 {{ detail.lessonCount }} 课时</text>
          </view>

          <view class="special-content-list">
            <view v-if="!detail.contentList.length" class="special-content-empty">
              <text>暂无专题内容</text>
            </view>
            <view
              v-for="item in detail.contentList"
              :key="item.id"
              class="special-content-card"
              :class="item.cardClass"
              @click="handleContentClick(item)"
            >
              <image class="special-content-card__cover" :src="item.cover" mode="aspectFill" />
              <view class="special-content-card__body">
                <view class="special-content-card__text">
                  <text class="special-content-card__title">{{ item.title }}</text>
                  <text class="special-content-card__desc">{{ item.desc }}</text>
                </view>

                <view class="special-content-card__foot">
                  <view class="special-content-card__price-row">
                    <text
                      class="special-content-card__tag"
                      :class="{
                        'special-content-card__tag--free': item.accessType === 'free',
                        'special-content-card__tag--paid': item.accessType === 'paid'
                      }"
                    >{{ item.accessType === 'free' ? '免费' : '收费' }}</text>
                    <text v-if="item.priceText" class="special-content-card__price">{{ item.priceText }}</text>
                  </view>
                  <view
                    class="special-content-card__action"
                    :class="{
                      'special-content-card__action--free': item.accessType === 'free',
                      'special-content-card__action--paid': item.accessType === 'paid'
                    }"
                  >
                    <view v-if="item.accessType === 'free'" class="special-content-card__play-icon"></view>
                    <view v-else class="special-content-card__lock-icon">
                      <view class="special-content-card__lock-head"></view>
                      <view class="special-content-card__lock-body"></view>
                    </view>
                  </view>
                </view>
              </view>
            </view>
          </view>
        </view>

        <view class="special-guess">
          <guess-you-like
            :show-more="false"
            :exclude-id="detail.id"
            :hide-when-empty="true"
          />
        </view>
      </view>
    </scroll-view>

    <view v-else-if="loading" class="special-state">
      <six-petal-spiral
        :active="true"
        :size="96"
        text=""
        :color="themePrimaryColor"
        :stroke-width="1.4"
        :particle-count="36"
        :trail-span="0.28"
      />
      <text class="special-state__text">专题加载中...</text>
    </view>

    <view v-else class="special-state">
      <text class="special-state__title">{{ errorMessage || '专题暂不可访问' }}</text>
      <view class="special-state__button" @click="fetchDetail">
        <text>重新加载</text>
      </view>
    </view>

    <view v-if="showLearningBar" class="special-bottom-bar special-bottom-bar--single">
      <view class="special-bottom-bar__primary special-bottom-bar__primary--full" @click="handlePrimaryAction">
        <text>开始学习</text>
      </view>
    </view>

    <view v-else-if="showPurchaseBar" class="special-bottom-bar">
      <view class="special-bottom-bar__icon-button" @click="handleBackHome">
        <text class="iconfont icon-home"></text>
      </view>
      <view
        class="special-bottom-bar__icon-button"
        :class="{ 'special-bottom-bar__icon-button--active': isFavorite }"
        @click="handleToggleFavorite"
      >
        <text class="iconfont" :class="isFavorite ? 'icon-favorfill' : 'icon-favor'"></text>
      </view>
      <view class="special-bottom-bar__price-block">
        <text class="special-bottom-bar__price-label">专题总价</text>
        <text class="special-bottom-bar__price-value">{{ topicTotalPriceText }}</text>
      </view>
      <view class="special-bottom-bar__actions">
        <view class="special-bottom-bar__ghost" @click="handleAddToCart">
          <text>加入购物车</text>
        </view>
        <view class="special-bottom-bar__primary" @click="handlePrimaryAction">
          <text>立即购买</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getSpecialDetail, recordSpecialPreview, toggleSpecialFavorite } from '@/api/special'
import { addCart, buyNow } from '@/api/cart'

const SPECIAL_ORDER_TYPE = 3
const CONTENT_TYPE_NOVEL = 3
const CONTENT_TYPE_AUDIO = 5
const CONTENT_TYPE_VIDEO = 6

function createEmptyDetail() {
  return {
    id: 0,
    title: '',
    badge: '',
    summary: '',
    image: '',
    lessonCount: 0,
    price: 0,
    originalPrice: 0,
    isFree: false,
    canBuy: false,
    canLearn: false,
    stats: [],
    contentList: []
  }
}

function formatPriceText(amount = 0) {
  return `¥${Number(amount || 0).toFixed(2)}`
}

function formatCount(value = 0) {
  const count = Number(value || 0)
  if (count >= 10000) {
    return `${(count / 10000).toFixed(count % 10000 === 0 ? 0 : 1).replace(/\.0$/, '')}w`
  }
  if (count >= 1000) {
    return `${(count / 1000).toFixed(count % 1000 === 0 ? 0 : 1).replace(/\.0$/, '')}k`
  }
  return String(count)
}

function getHeroToneClassName(detail = {}) {
  if (!detail.id) {
    return ''
  }

  const tones = ['cyan', 'slate', 'gold', 'gray', 'coffee', 'emerald', 'teal', 'navy']
  return `special-hero--${tones[Number(detail.id || 0) % tones.length]}`
}

function getContentCardClassName(item = {}) {
  const accessType = String(item.accessType || '').trim()
  return accessType ? `special-content-card--${accessType}` : ''
}

function getContentDetailPath(contentType = 0) {
  switch (Number(contentType || 0)) {
    case CONTENT_TYPE_NOVEL:
      return '/pages/contents/content/novel/index'
    case CONTENT_TYPE_AUDIO:
      return '/pages/contents/content/aud/index'
    case CONTENT_TYPE_VIDEO:
      return '/pages/contents/content/vid/index'
    default:
      return '/pages/contents/content/detail/index'
  }
}

function countAudioContents(list = []) {
  if (!Array.isArray(list)) {
    return 0
  }

  return list.filter((item) => {
    return Number(item && item.type || 0) === CONTENT_TYPE_AUDIO
      && Number(item && item.articleId || 0) > 0
      && Number(item && item.status || 0) === 1
      && Number(item && item.auditStatus || 0) === 1
  }).length
}

function normalizeContentItem(item = {}, specialCanLearn = false) {
  const article = item.article && typeof item.article === 'object' ? item.article : {}
  const articleId = Number(item.article_id || article.id || 0)
  const price = Number(article.price || 0)
  const isTrial = Number(item.is_trial || article.is_trial || 0) === 1
  const accessType = specialCanLearn || isTrial || price <= 0 ? 'free' : 'paid'
  const normalized = {
    id: Number(item.id || articleId || 0),
    articleId,
    type: Number(item.type || article.content_type || 0),
    title: String(article.title || '').trim() || '内容标题待完善',
    desc: String(article.description || '').trim() || '暂无内容简介',
    cover: String(article.cover || '').trim(),
    accessType,
    price,
    priceText: accessType === 'paid' && price > 0 ? formatPriceText(price) : '',
    isTrial,
    status: Number(article.status || 0),
    auditStatus: Number(article.audit_status || 0)
  }

  return {
    ...normalized,
    cardClass: getContentCardClassName(normalized)
  }
}

function normalizeDetail(payload = {}) {
  const price = Number(payload.price || 0)
  const isFree = Boolean(payload.is_free) || Number(payload.special_type || 0) === 0 || price <= 0
  const canLearn = Boolean(payload.can_learn) || String(payload.access_state || '') === 'learning'
  const contents = Array.isArray(payload.contents) ? payload.contents : []
  const contentList = contents.map((item) => normalizeContentItem(item, canLearn))
  const contentCount = Number(payload.content_count || contentList.length || 0)
  const buyCount = Number(payload.buy_count || 0)
  const viewCount = Number(payload.view_count || 0)

  return {
    id: Number(payload.id || 0),
    title: String(payload.title || '').trim(),
    badge: Boolean(payload.is_hot) ? '热门专题' : (isFree ? '免费专题' : '精品专题'),
    summary: String(payload.description || '').trim() || '暂无专题简介',
    image: String(payload.cover || '').trim(),
    lessonCount: contentCount,
    price,
    originalPrice: Number(payload.original_price || 0),
    isFree,
    isPurchased: Boolean(payload.is_purchased),
    isFavorite: Boolean(payload.is_favorite),
    canBuy: Boolean(payload.can_buy),
    canLearn,
    accessState: String(payload.access_state || (canLearn ? 'learning' : 'purchase')),
    stats: [
      { label: '章节内容', value: formatCount(contentCount) },
      { label: '购买人数', value: formatCount(buyCount) },
      { label: '浏览量', value: formatCount(viewCount) }
    ],
    contentList
  }
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
  name: 'SpecialDetailPage'
})

const specialId = ref(0)
const detail = ref(createEmptyDetail())
const detailState = ref('purchase')
const isFavorite = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const favoriteLoading = ref(false)
const cartLoading = ref(false)
const buyLoading = ref(false)

const isLearningState = computed(() => {
  return detailState.value === 'learning'
})
const showLearningBar = computed(() => {
  return !loading.value && !errorMessage.value && isLearningState.value
})
const showPurchaseBar = computed(() => {
  return !loading.value && !errorMessage.value && !isLearningState.value
})
const heroToneClass = computed(() => {
  return getHeroToneClassName(detail.value)
})
const topicTotalPriceText = computed(() => {
  return detail.value.isFree ? '免费' : formatPriceText(detail.value.price)
})

onLoad((options = {}) => {
  initPage(options)
  fetchDetail()
})

function initPage(options = {}) {
  specialId.value = Number(options.id || options.special_id || 0)
  detail.value = createEmptyDetail()
  detailState.value = 'purchase'
  isFavorite.value = false
  errorMessage.value = specialId.value > 0 ? '' : '专题ID不能为空'
}
async function fetchDetail() {
  if (specialId.value <= 0) {
    errorMessage.value = '专题ID不能为空'
    return
  }

  loading.value = true
  errorMessage.value = ''
  try {
    const res = await getSpecialDetail({ id: specialId.value }, {
      showError: false,
      loginRedirect: false
    })

    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '专题加载失败'
      return
    }

    const detailData = normalizeDetail(res.data)
    detail.value = detailData
    detailState.value = detailData.canLearn ? 'learning' : 'purchase'
    isFavorite.value = detailData.isFavorite
    reportPreviewRecord()
  } catch (error) {
    console.error('[special-detail] fetchDetail failed', error)
    errorMessage.value = '专题加载失败'
  } finally {
    loading.value = false
  }
}
async function reportPreviewRecord() {
  const specialIdValue = Number(detail.value.id || specialId.value || 0)
  if (specialIdValue <= 0) {
    return
  }

  try {
    await recordSpecialPreview({ special_id: specialIdValue }, {
      showError: false,
      loginRedirect: false
    })
  } catch (error) {
    console.error('[special-detail] reportPreviewRecord failed', error)
  }
}
function handleBackHome() {
  uni.switchTab({ url: '/pages/index/index' })
}
function handleGoBack() {
  if (getCurrentPages().length > 1) {
    uni.navigateBack()
    return
  }

  uni.switchTab({ url: '/pages/index/index' })
}
async function handleToggleFavorite() {
  const specialIdValue = Number(detail.value.id || specialId.value || 0)
  if (specialIdValue <= 0 || favoriteLoading.value) {
    return
  }

  favoriteLoading.value = true
  try {
    const res = await toggleSpecialFavorite({ special_id: specialIdValue })
    if (!res || res.code !== 200 || !res.data) {
      return
    }

    isFavorite.value = Number(res.data.is_favorite || 0) === 1
    uni.showToast({
      title: isFavorite.value ? '已收藏' : '已取消收藏',
      icon: 'none'
    })
  } catch (error) {
    console.error('[special-detail] handleToggleFavorite failed', error)
  } finally {
    favoriteLoading.value = false
  }
}
function buildSpecialCartPayload() {
  return {
    goods_id: Number(detail.value.id || specialId.value || 0),
    cart_num: 1,
    product_attr_unique: '',
    order_type: SPECIAL_ORDER_TYPE
  }
}
async function handleAddToCart() {
  if (cartLoading.value || isLearningState.value) {
    return
  }

  cartLoading.value = true
  try {
    const res = await addCart(buildSpecialCartPayload())
    if (!res || res.code !== 200) {
      return
    }

    uni.showToast({ title: '已加入购物车', icon: 'none' })
  } catch (error) {
    console.error('[special-detail] handleAddToCart failed', error)
  } finally {
    cartLoading.value = false
  }
}
async function handlePrimaryAction() {
  if (isLearningState.value) {
    openFirstContent()
    return
  }

  if (buyLoading.value) {
    return
  }

  buyLoading.value = true
  try {
    const res = await buyNow(buildSpecialCartPayload())
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
    console.error('[special-detail] handlePrimaryAction failed', error)
  } finally {
    buyLoading.value = false
  }
}
function openFirstContent() {
  const firstContent = (detail.value.contentList || []).find((item) => Number(item.articleId || 0) > 0)
  if (!firstContent) {
    uni.showToast({ title: '暂无可学习内容', icon: 'none' })
    return
  }

  handleContentClick(firstContent)
}
function handleContentClick(item = {}) {
  const targetId = Number(item.articleId || item.id || 0)
  if (!targetId) {
    uni.showToast({ title: item.title || '内容暂不可访问', icon: 'none' })
    return
  }

  if (!isLearningState.value && item.accessType === 'paid') {
    uni.showToast({ title: '购买专题后可学习', icon: 'none' })
    return
  }

  const query = [`id=${targetId}`]
  if (Number(item.type || 0) === CONTENT_TYPE_NOVEL && specialId.value > 0) {
    query.push('source=special')
    query.push(`special_id=${specialId.value}`)
  }

  if (Number(item.type || 0) === CONTENT_TYPE_AUDIO && specialId.value > 0) {
    query.push('source=special')
    query.push(`special_id=${specialId.value}`)
    query.push(`audio_count=${countAudioContents(detail.value.contentList)}`)
  }

  uni.navigateTo({
    url: `${getContentDetailPath(item.type)}?${query.join('&')}`
  })
}
</script>

<style lang="scss" scoped>
.special-detail-page {
  min-height: 100vh;
  background: #f6f6f7;

  &__scroll {
    height: 100vh;
  }

  &__body {
    padding-bottom: calc(220rpx + env(safe-area-inset-bottom));

    &--learning {
      padding-bottom: calc(160rpx + env(safe-area-inset-bottom));
    }
  }
}

.special-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 48rpx;
  flex-direction: column;
  box-sizing: border-box;

  &__text,
  &__title {
    margin-top: 24rpx;
    font-size: 26rpx;
    line-height: 40rpx;
    color: #71717a;
    text-align: center;
  }

  &__title {
    margin-top: 0;
    font-size: 30rpx;
    font-weight: 700;
    color: #18181b;
  }

  &__button {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 72rpx;
    margin-top: 28rpx;
    padding: 0 34rpx;
    border-radius: 8rpx;
    background: var(--brand-primary, #ea580c);
    font-size: 26rpx;
    font-weight: 700;
    color: #ffffff;
  }
}

.special-hero {
  position: relative;
  height: 548rpx;
  padding: calc(var(--status-bar-height, 0px) + 54rpx) 28rpx 34rpx;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
  background: radial-gradient(circle at center, rgba(18, 212, 255, 0.32), rgba(0, 16, 27, 1) 72%);

  &--cyan {
    background: radial-gradient(circle at center, rgba(18, 212, 255, 0.32), rgba(0, 16, 27, 1) 72%);
  }

  &--gold {
    background: radial-gradient(circle at center, rgba(255, 177, 73, 0.32), rgba(20, 22, 34, 1) 72%);
  }

  &--slate {
    background: linear-gradient(135deg, #dae1ea, #a8b4c5);
  }

  &--gray {
    background: linear-gradient(135deg, #d7d7d7, #bfbfbf);
  }

  &--coffee {
    background: linear-gradient(135deg, #ddd6cf, #b9b1a8);
  }

  &--emerald {
    background: radial-gradient(circle at center, rgba(72, 201, 176, 0.26), rgba(8, 29, 24, 1) 72%);
  }

  &--teal {
    background: radial-gradient(circle at center, rgba(38, 182, 184, 0.28), rgba(8, 20, 28, 1) 72%);
  }

  &--navy {
    background: radial-gradient(circle at center, rgba(171, 212, 244, 0.26), rgba(11, 25, 52, 1) 72%);
  }

  &__image {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0.92;
    transform: scale(1.04);
  }

  &__mask {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background:
      radial-gradient(circle at 82% 22%, rgba(255, 255, 255, 0.1), transparent 26%),
      linear-gradient(180deg, rgba(0, 0, 0, 0.02), rgba(0, 0, 0, 0.76));
  }

  &__back {
    position: absolute;
    left: 28rpx;
    top: calc(var(--status-bar-height, 0px) + 23px);
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64rpx;
    height: 64rpx;
    border-radius: 999rpx;
    background: rgba(12, 16, 26, 0.72);
    border: 1rpx solid rgba(255, 255, 255, 0.14);
  }

  &__back-icon {
    font-size: 28rpx;
    line-height: 1;
    color: #ffffff;
  }

  &__content {
    position: relative;
    z-index: 1;
    width: 100%;
  }

  &__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100rpx;
    height: 40rpx;
    padding: 0 18rpx;
    border-radius: 8rpx;
    background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
    font-size: 18rpx;
    font-weight: 700;
    color: #ffffff;
    box-shadow: 0 8rpx 20rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
  }

  &__title {
    display: block;
    margin-top: 18rpx;
    font-size: 44rpx;
    line-height: 1.28;
    font-weight: 700;
    color: #ffffff;
  }

  &__desc {
    display: -webkit-box;
    margin-top: 12rpx;
    max-width: 94%;
    font-size: 24rpx;
    line-height: 1.66;
    color: rgba(255, 255, 255, 0.8);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
  }
}

.special-stats {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: -32rpx 32rpx 0;
  padding: 24rpx 0;
  border-radius: 16rpx;
  border: 1rpx solid rgba(228, 228, 231, 0.95);
  background: #ffffff;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.04);

  &__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6rpx;
    position: relative;

    & + &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 6rpx;
      bottom: 6rpx;
      width: 1rpx;
      background: rgba(244, 244, 245, 0.95);
    }
  }

  &__value {
    font-size: 28rpx;
    line-height: 40rpx;
    font-weight: 700;
    color: #18181b;
  }

  &__label {
    font-size: 20rpx;
    line-height: 28rpx;
    color: #71717a;
  }
}

.special-section {
  padding: 48rpx 32rpx 0;

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32rpx;
  }

  &__title {
    font-size: 32rpx;
    line-height: 48rpx;
    font-weight: 700;
    color: #18181b;
  }

  &__meta {
    font-size: 20rpx;
    line-height: 28rpx;
    color: #a1a1aa;
  }
}

.special-content-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.special-content-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 180rpx;
  border-radius: 8px;
  background: #ffffff;
  font-size: 26rpx;
  color: #71717a;
}

.special-content-card {
  display: flex;
  align-items: stretch;
  gap: 12px;
  height: 120px;
  min-height: 120px;
  padding: 12px;
  border-radius: 8px;
  background: #ffffff;
  box-sizing: border-box;
  box-shadow: 0 4rpx 12rpx var(--shadow-soft);

  &__cover {
    width: 96px;
    height: 96px;
    flex-shrink: 0;
    border-radius: 6px;
    background: #f4f4f5;
  }

  &__body {
    display: flex;
    flex: 1;
    min-width: 0;
    height: 96px;
    min-height: 96px;
    align-self: stretch;
    flex-direction: column;
    justify-content: space-between;
  }

  &__text {
    display: flex;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    justify-content: flex-start;
  }

  &__title {
    display: -webkit-box;
    font-size: 14px;
    line-height: 20px;
    font-weight: 700;
    color: #18181b;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    overflow: hidden;
  }

  &__desc {
    display: -webkit-box;
    margin-top: 4px;
    min-height: 32px;
    font-size: 12px;
    line-height: 16px;
    color: #71717a;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow: hidden;
  }

  &__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 18px;
    margin-top: 0;
  }

  &__price-row {
    display: flex;
    align-items: center;
    min-height: 18px;
    flex-wrap: wrap;
    gap: 4px;
  }

  &__tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 16px;
    padding: 0 8px;
    border-radius: 4px;
    font-size: 10px;
    line-height: 12px;
    font-weight: 700;

    &--free {
      background: #f3f4f6;
      color: #8a8a93;
    }

    &--paid {
      background: rgba(233, 89, 15, 0.08);
      color: var(--brand-primary, #ea580c);
    }
  }

  &__price {
    font-size: 12px;
    line-height: 12px;
    font-weight: 700;
    color: var(--brand-primary, #ea580c);
  }

  &__action {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    flex-shrink: 0;

    &--free {
      border-radius: 50%;
      background: rgba(244, 244, 245, 0.9);
    }
  }

  &__play-icon {
    width: 0;
    height: 0;
    margin-left: 2px;
    border-top: 3.5px solid transparent;
    border-bottom: 3.5px solid transparent;
    border-left: 5px solid #a1a1aa;
  }

  &__lock-icon {
    position: relative;
    width: 11px;
    height: 12px;
  }

  &__lock-head {
    position: absolute;
    left: 50%;
    top: 0;
    width: 6px;
    height: 5px;
    border: 1.5px solid #a1a1aa;
    border-bottom: 0;
    border-radius: 5px 5px 0 0;
    transform: translateX(-50%);
    box-sizing: border-box;
  }

  &__lock-body {
    position: absolute;
    left: 50%;
    bottom: 0;
    width: 8px;
    height: 7px;
    border-radius: 2px;
    background: #a1a1aa;
    transform: translateX(-50%);
  }
}

.special-guess {
  margin: 48rpx 24rpx 0;
  position: relative;
  z-index: 0;
}

.special-bottom-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 10rpx;
  padding: 16rpx 24rpx calc(16rpx + env(safe-area-inset-bottom));
  border-top: 1rpx solid rgba(15, 23, 42, 0.06);
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(18rpx);
  box-shadow: 0 -10rpx 28rpx rgba(15, 23, 42, 0.05);

  &--single {
    display: block;
  }

  &__icon-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 68rpx;
    height: 68rpx;
    flex-shrink: 0;
    border-radius: 16rpx;
    border: 1rpx solid rgba(15, 23, 42, 0.08);
    background: #ffffff;
    color: #7a7f88;
    box-shadow: 0 8rpx 18rpx rgba(15, 23, 42, 0.04);

    &--active {
      color: var(--brand-primary, #ea580c);
      border-color: rgba(233, 89, 15, 0.16);
      background: rgba(233, 89, 15, 0.06);
    }

    .iconfont {
      font-size: 34rpx;
    }
  }

  &__price-block {
    display: flex;
    flex-shrink: 0;
    align-items: flex-end;
    flex-direction: column;
    justify-content: center;
    min-width: 132rpx;
    margin-left: auto;
    gap: 4rpx;
    text-align: right;
  }

  &__price-label {
    font-size: 18rpx;
    line-height: 1;
    color: #8a8f99;
  }

  &__price-value {
    font-size: 34rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--brand-primary, #ea580c);
  }

  &__actions {
    display: flex;
    flex: 0 0 340rpx;
    width: 340rpx;
    min-width: 340rpx;
    height: 80rpx;
    overflow: hidden;
    border-radius: 999rpx;
    border: 1rpx solid var(--brand-primary, #ea580c);
    background: #ffffff;
    box-shadow: 0 14rpx 28rpx rgba(233, 89, 15, 0.12);
  }

  &__ghost,
  &__primary {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 28rpx;
    font-weight: 700;
  }

  &__ghost {
    flex: 1;
    color: var(--brand-primary, #ea580c);
    background: #ffffff;
  }

  &__primary {
    flex: 1;
    background: var(--brand-primary, #ea580c);
    color: #ffffff;

    &--full {
      width: 100%;
      height: 84rpx;
      border-radius: 12rpx;
      background: #000000;
      box-shadow: 0 10rpx 20rpx rgba(0, 0, 0, 0.14);
    }
  }
}
</style>
