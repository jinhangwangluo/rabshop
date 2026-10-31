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

        <view v-if="relatedProducts.length" class="special-section special-related">
          <view class="special-related__head">
            <text class="special-section__title">{{ RELATED_PRODUCT_TITLE }}</text>
            <view v-if="relatedHasMore" class="special-related__more" hover-class="special-related__more--hover" @click="handleRelatedMore">
              <text class="special-related__more-text">查看更多</text>
              <text class="iconfont icon-right special-related__more-arrow"></text>
            </view>
          </view>

          <view class="special-related__list">
            <view
              v-for="item in relatedProducts"
              :key="item.renderKey"
              class="related-card"
              hover-class="related-card--hover"
              @click="handleProductClick(item)"
            >
              <view class="related-card__cover">
                <image v-if="item.image" class="related-card__image" :src="item.image" mode="aspectFill" />
                <view v-else class="related-card__placeholder">
                  <text>商品</text>
                </view>
              </view>

              <view class="related-card__body">
                <text class="related-card__title"><text
                  v-if="item.firstTagText"
                  class="related-card__title-badge"
                  :style="item.firstTagStyle"
                >{{ item.firstTagText }}</text>{{ item.title }}</text>
                <text class="related-card__desc">{{ item.salesText }}</text>

                <view class="related-card__foot">
                  <text class="related-card__price">{{ item.priceText }}</text>
                  <view class="related-card__buy" hover-class="related-card__buy--hover" @click.stop="handleProductClick(item)">
                    <text class="related-card__buy-text">购买</text>
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
import { getSpecialDetail, getSpecialProducts, recordSpecialPreview, toggleSpecialFavorite } from '@/api/special'
import { addCart, buyNow } from '@/api/cart'
import { normalizeDisplayTags } from '@/utils/tag'

const SPECIAL_ORDER_TYPE = 3
const CONTENT_TYPE_NOVEL = 3
const CONTENT_TYPE_AUDIO = 5
const CONTENT_TYPE_VIDEO = 6

// 「关联商品」区块标题，接口返回空列表时整个区块隐藏。
const RELATED_PRODUCT_TITLE = '关联商品'
// 首页展示的商品数量，超出部分通过「查看更多」进入商品列表页。
const RELATED_PRODUCT_LIMIT = 4

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

/**
 * 把后端标签对象转换为可内联渲染的样式串。
 * 标签颜色由后台配置，必须走行内样式，不能写死在 scss 里。
 *
 * @param {Object} tag 规范化后的标签对象。
 * @returns {string} 行内样式字符串。
 */
function buildInlineTagStyle(tag = {}) {
  const style = []
  const textColor = String(tag.text_color || '').trim()
  const bgColor = String(tag.bg_color || '').trim()
  const borderColor = String(tag.border_color || '').trim()

  if (textColor) {
    style.push(`color: ${textColor};`)
  }
  if (bgColor) {
    style.push(`background-color: ${bgColor};`)
  }
  if (borderColor) {
    style.push('border-style: solid;')
    style.push(`border-color: ${borderColor};`)
  }

  return style.join(' ')
}

/**
 * 格式化商品销量文案，与 DIY 商品组件保持一致。
 *
 * @param {number} value 展示销量。
 * @returns {string} 销量文案。
 */
function formatSalesText(value = 0) {
  const count = Number(value || 0)
  if (count >= 10000) {
    return `${(count / 10000).toFixed(1)}w`
  }
  return String(count)
}

/**
 * 规范化关联商品卡片字段。
 *
 * 使用场景：专题详情页「关联商品」区块渲染；字段直接沿用 `special/products`
 * 返回结构，缺省时用本地兜底，避免后端字段缺失导致空白卡片。
 *
 * @param {Object} item 接口返回的单个商品。
 * @param {number} index 列表下标，用于生成稳定 key。
 * @returns {Object} 可直接渲染的商品卡片对象。
 */
function normalizeRelatedProduct(item = {}, index = 0) {
  const id = Number(item.id || 0)
  const price = Number(item.price || 0)
  const realSales = Number(item.sales || 0)
  const virtualSales = Number(item.virtual_sales || 0)
  const displaySales = Number(
    item.display_sales !== undefined ? item.display_sales : (realSales + virtualSales)
  ) || 0
  const displayTags = normalizeDisplayTags(item.tag_list, 1)
  const firstTag = displayTags.length > 0 ? displayTags[0] : null

  return {
    id,
    title: String(item.name || item.title || '').trim() || '商品',
    image: String(item.cover || item.image || '').trim(),
    priceText: String(item.price_text || '').trim() || formatPriceText(price),
    salesText: String(item.sales_text || '').trim() || `已售${formatSalesText(displaySales)}件`,
    detailUrl: String(item.detail_url || '').trim() || `/pages/goods/detail/index?id=${id}`,
    firstTagText: firstTag ? firstTag.name : '',
    firstTagStyle: firstTag ? buildInlineTagStyle(firstTag) : '',
    renderKey: `related-product-${id || index}-${index}`
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
const relatedProducts = ref([])
const relatedTotal = ref(0)
const relatedExpanded = ref(false)
const relatedLoadingMore = ref(false)

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
// 首屏只展示固定数量，剩余商品通过「查看更多」就地展开全部。
const relatedHasMore = computed(() => {
  return !relatedExpanded.value && relatedTotal.value > relatedProducts.value.length
})

onLoad((options = {}) => {
  initPage(options)
  fetchDetail()
  fetchRelatedProducts()
})

function initPage(options = {}) {
  specialId.value = Number(options.id || options.special_id || 0)
  detail.value = createEmptyDetail()
  detailState.value = 'purchase'
  isFavorite.value = false
  relatedProducts.value = []
  relatedTotal.value = 0
  relatedExpanded.value = false
  relatedLoadingMore.value = false
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
/**
 * 获取专题关联商品。
 *
 * 使用场景：专题详情页进入时拉取首屏商品，「查看更多」时拉取全部；
 * 接口失败或返回空列表时整个区块隐藏，不展示空状态。
 *
 * @param {Object} params 拉取参数。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页数量。
 * @param {boolean} params.append 是否追加到现有列表。
 */
async function fetchRelatedProducts({ page = 1, limit = RELATED_PRODUCT_LIMIT, append = false } = {}) {
  if (specialId.value <= 0) {
    return
  }

  try {
    const res = await getSpecialProducts({
      id: specialId.value,
      page,
      limit
    }, {
      showError: false,
      loginRedirect: false
    })

    // 关联商品属于弱依赖数据，失败时静默降级，不影响专题详情主流程。
    if (!res || res.code !== 200 || !res.data) {
      return
    }

    const list = Array.isArray(res.data.list) ? res.data.list : []
    const baseIndex = append ? relatedProducts.value.length : 0
    const normalized = list.map((item, index) => normalizeRelatedProduct(item, baseIndex + index))
    relatedProducts.value = append ? relatedProducts.value.concat(normalized) : normalized
    relatedTotal.value = Number(res.data.total || relatedProducts.value.length || 0)
  } catch (error) {
    console.error('[special-detail] fetchRelatedProducts failed', error)
  }
}
/**
 * 打开关联商品详情页。
 *
 * @param {Object} item 规范化后的商品卡片对象。
 */
function handleProductClick(item = {}) {
  const productId = Number(item.id || 0)
  if (productId <= 0) {
    uni.showToast({ title: '商品暂不可访问', icon: 'none' })
    return
  }

  const url = String(item.detailUrl || '').trim() || `/pages/goods/detail/index?id=${productId}`
  uni.navigateTo({ url })
}
/**
 * 查看更多关联商品。
 *
 * 商品列表页暂无「按专题筛选」入参，直接跳转会展示全量商品形成误导，
 * 因此就地按分页补齐剩余商品，保证展示内容仍属于当前专题。
 */
async function handleRelatedMore() {
  const total = Number(relatedTotal.value || 0)
  if (total <= 0 || relatedLoadingMore.value) {
    return
  }

  relatedLoadingMore.value = true
  try {
    // 剩余数量补齐一轮即可覆盖单页上限，接口 limit 最大 20。
    const rest = total - relatedProducts.value.length
    if (rest > 0) {
      await fetchRelatedProducts({
        page: 2,
        limit: Math.min(rest, 20),
        append: true
      })
    }
    relatedExpanded.value = true
  } finally {
    relatedLoadingMore.value = false
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

.special-related {
  padding-top: 40rpx;
}

.special-related__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.special-related__more {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  height: 40rpx;
  padding-left: 16rpx;
  color: #8b8b93;

  &--hover {
    opacity: 0.7;
  }
}

.special-related__more-text {
  font-size: 24rpx;
  line-height: 34rpx;
  color: #8b8b93;
}

.special-related__more-arrow {
  margin-left: 4rpx;
  font-size: 20rpx;
  line-height: 1;
  color: #b0b0b8;
}

.special-related__list {
  display: flex;
  flex-wrap: wrap;
  /* 用负边距抵消子项 padding，避免 calc(50% - 18rpx) + 左右 margin 导致总宽超出行宽、第二列被挤出屏幕 */
  margin-left: -8rpx;
  margin-right: -8rpx;
}

.related-card {
  display: flex;
  width: calc(50% - 16rpx);
  margin: 0 8rpx 16rpx;
  padding: 14rpx;
  border-radius: 16rpx;
  background: #ffffff;
  box-sizing: border-box;
  box-shadow: 0 4rpx 12rpx var(--shadow-soft);

  &--hover {
    opacity: 0.92;
  }
}

.related-card__cover {
  position: relative;
  flex-shrink: 0;
  width: 112rpx;
  height: 112rpx;
  margin-right: 10rpx;
  border-radius: 12rpx;
  overflow: hidden;
  background: #f3f5f7;
}

.related-card__image,
.related-card__placeholder {
  width: 100%;
  height: 100%;
}

.related-card__placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #b6bdc6;
  font-size: 22rpx;
}

.related-card__body {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.related-card__title {
  display: -webkit-box;
  height: 68rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  font-weight: 600;
  color: #1f1f1f;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* 商品标签颜色由后台配置，主色作为兜底，样式通过行内 style 覆盖 */
.related-card__title-badge {
  display: inline-block;
  max-width: 92rpx;
  height: 30rpx;
  margin-right: 6rpx;
  padding: 0 8rpx;
  border-width: 1rpx;
  border-style: none;
  border-color: transparent;
  border-radius: 6rpx;
  box-sizing: border-box;
  color: #ffffff;
  background: var(--brand-primary, #ea580c);
  font-size: 18rpx;
  line-height: 30rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
  white-space: nowrap;
}

.related-card__desc {
  margin-top: 4rpx;
  font-size: 22rpx;
  line-height: 30rpx;
  color: #8b8b8b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 卡片宽度只有半屏，价格独占左侧、按钮固定右下角，避免价格被压缩成省略号 */
.related-card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 4rpx;
}

.related-card__price {
  min-width: 0;
  flex: 1 1 auto;
  margin-right: 8rpx;
  font-size: 28rpx;
  line-height: 34rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.related-card__buy {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 68rpx;
  /* 高度需大于文字行高，否则中文字形上下会被裁切 */
  height: 36rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  box-shadow: 0 6rpx 14rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));

  &--hover {
    opacity: 0.86;
  }
}

.related-card__buy-text {
  font-size: 18rpx;
  line-height: 26rpx;
  font-weight: 700;
  color: #ffffff;
  white-space: nowrap;
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
