<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="comment-page">
    <app-safe-top title="商品评价" background="#ffffff" show-back emit-back @back="handleBack">
    </app-safe-top>

    <view class="comment-page__body">
      <view class="rate-panel">
        <view class="rate-panel__summary">
          <text class="rate-panel__label">商品好评率</text>
          <view class="rate-panel__score-row">
            <text class="rate-panel__score">{{ goodRateText }}%</text>
            <text class="rate-panel__good">好评</text>
          </view>
          <text class="rate-panel__count">共 {{ commentTotal }} 条评价</text>
        </view>

        <view class="rate-panel__bars">
          <view v-for="item in rateRows" :key="item.id" class="rate-row">
            <text class="rate-row__label">{{ item.label }}</text>
            <text class="rate-row__count">({{ item.count }})</text>
            <view class="rate-row__track">
              <view class="rate-row__fill" :style="item.style"></view>
            </view>
            <text class="rate-row__percent">{{ item.percent }}%</text>
          </view>
        </view>
      </view>

      <view class="filter-panel">
        <view class="filter-row">
          <view
            v-for="item in primaryFilters"
            :key="item.id"
            class="filter-chip"
            :class="{ 'filter-chip--active': activeRatingFilter === item.id }"
            hover-class="filter-chip--hover"
            @click="selectRatingFilter(item.id)"
          >
            <text>{{ item.title }}</text>
          </view>
          <view
            class="filter-chip filter-chip--image"
            :class="{ 'filter-chip--active': activeRatingFilter === imageFilter.id }"
            hover-class="filter-chip--hover"
            @click="selectRatingFilter(imageFilter.id)"
          >
            <text>{{ imageFilter.title }}</text>
            <text class="iconfont icon-triangledownfill filter-chip__arrow"></text>
          </view>
        </view>

        <view class="filter-row filter-row--secondary">
          <view
            v-for="item in secondaryFilters"
            :key="item.id"
            class="filter-chip filter-chip--small"
            :class="{ 'filter-chip--active': activeSortFilter === item.id, 'filter-chip--wide': item.wide }"
            hover-class="filter-chip--hover"
            @click="selectSortFilter(item.id)"
          >
            <text>{{ item.title }}</text>
          </view>
        </view>
      </view>

      <view class="comment-list">
        <view v-for="item in filteredComments" :key="item.id" class="comment-card">
          <view class="comment-card__header">
            <image class="comment-card__avatar" :src="item.avatar" mode="aspectFill"></image>
            <view class="comment-card__user">
              <view class="comment-card__name-row">
                <text class="comment-card__name">{{ item.name }}</text>
                <text v-if="item.vip" class="comment-card__vip">{{ item.vip }}</text>
              </view>
              <view class="comment-card__stars">
                <text v-for="star in item.stars" :key="star" class="iconfont icon-favorfill comment-card__star"></text>
              </view>
            </view>
            <view class="comment-card__date-row">
              <text class="comment-card__date">{{ item.date }}</text>
              <text class="iconfont icon-moreandroid comment-card__more"></text>
            </view>
          </view>

          <text class="comment-card__content">{{ item.content }}</text>

          <scroll-view v-if="item.images.length" scroll-x class="comment-card__image-scroll" :show-scrollbar="false">
            <view class="comment-card__image-row">
              <image
                v-for="image in item.images"
                :key="image"
                class="comment-card__image"
                :src="image"
                mode="aspectFill"
              ></image>
            </view>
          </scroll-view>

          <view class="comment-card__footer">
            <text v-if="item.sku" class="comment-card__sku">{{ item.sku }}</text>
            <text v-if="item.sku && item.weight" class="comment-card__divider">|</text>
            <text v-if="item.weight" class="comment-card__weight">{{ item.weight }}</text>
            <view class="comment-card__actions">
              <text class="comment-card__useful">有用 {{ item.useful }}</text>
              <text
                class="iconfont icon-like comment-card__action-icon"
                hover-class="comment-card__action-icon--hover"
                @click="toggleUseful(item)"
              ></text>
              <text class="comment-card__reply" hover-class="comment-card__reply--hover" @click="openReplyPopup(item)">回复</text>
              <text
                class="iconfont icon-comment comment-card__action-icon"
                hover-class="comment-card__action-icon--hover"
                @click="openReplyPopup(item)"
              ></text>
            </view>
          </view>
        </view>
      </view>

      <text class="comment-page__end">{{ commentEndText }}</text>
    </view>

    <view v-if="showReplyPopup" class="reply-mask" @click="closeReplyPopup">
      <view class="reply-dialog" @click.stop>
        <view class="reply-dialog__header">
          <text class="reply-dialog__title">评论回复</text>
          <text class="iconfont icon-close reply-dialog__close" hover-class="reply-dialog__close--hover" @click="closeReplyPopup"></text>
        </view>

        <view class="reply-dialog__body">
          <text class="reply-dialog__section-title">当前评论</text>
          <view v-if="currentComment.id" class="current-comment">
            <image v-if="currentComment.avatar" class="current-comment__avatar" :src="currentComment.avatar" mode="aspectFill"></image>
            <view v-else class="current-comment__avatar"></view>
            <view class="current-comment__content">
              <view class="current-comment__name-row">
                <text class="current-comment__name">{{ currentComment.fullName }}</text>
                <text class="current-comment__level">{{ currentComment.level }}</text>
              </view>
              <text class="current-comment__text">{{ currentComment.replyContent }}</text>
              <view class="current-comment__meta-row">
                <text>{{ currentComment.replyDate }}</text>
                <text>{{ currentComment.location }}</text>
                <view class="current-comment__like">
                  <text class="iconfont icon-like current-comment__like-icon"></text>
                  <text>{{ currentComment.replyLikes }}</text>
                </view>
              </view>
            </view>
          </view>

          <view class="reply-list__header">
            <text class="reply-list__title">全部回复（{{ replyCount }}）</text>
            <view class="reply-list__sort" hover-class="reply-list__sort--hover" @click="toggleSort">
              <text>{{ sortText }}</text>
              <text class="iconfont icon-triangledownfill reply-list__sort-icon"></text>
            </view>
          </view>

          <scroll-view scroll-y class="reply-list" :show-scrollbar="false">
            <view
              v-for="reply in sortedReplies"
              :key="reply.id"
              class="reply-item"
              :class="{ 'reply-item--last': reply.last }"
            >
              <image class="reply-item__avatar" :src="reply.avatar" mode="aspectFill"></image>
              <view class="reply-item__content">
                <view class="reply-item__name-row">
                  <text class="reply-item__name">{{ reply.name }}</text>
                  <text class="reply-item__level">{{ reply.level }}</text>
                </view>
                <text class="reply-item__text">{{ reply.content }}</text>
                <view class="reply-item__meta-row">
                  <text>{{ reply.time }}</text>
                  <text>{{ reply.location }}</text>
                  <view class="reply-item__like" @click="toggleReplyLike(reply)">
                    <text class="iconfont icon-like reply-item__like-icon"></text>
                    <text>{{ reply.likes }}</text>
                  </view>
                </view>
              </view>
            </view>
          </scroll-view>
        </view>

        <view class="reply-dialog__footer">
          <input
            v-model="replyText"
            class="reply-dialog__input"
            placeholder="回复 小橙子..."
            placeholder-class="reply-dialog__placeholder"
            confirm-type="send"
            @confirm="sendReply"
          />
          <view class="reply-dialog__send" hover-class="reply-dialog__send--hover" @click="sendReply">
            <text>发送</text>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getProductReviews } from '@/api/product'

defineOptions({
  name: 'GoodsCommentPage'
})

const PAGE_LIMIT = 20
const ratingStars = Object.freeze([1, 2, 3, 4, 5])
const emptySummary = Object.freeze({
  total: 0,
  good_count: 0,
  medium_count: 0,
  bad_count: 0,
  image_count: 0,
  good_rate: 0
})
const productId = ref(0)
const loading = ref(false)
const activeRatingFilter = ref('all')
const activeSortFilter = ref('default')
const showReplyPopup = ref(false)
const replyText = ref('')
const sortMode = ref('hot')
const selectedCommentId = ref(0)
const reviewSummary = ref({ ...emptySummary })

const primaryFilters = computed(() => [
  { id: 'all', title: `全部（${reviewSummary.value.total}）` },
  { id: 'good', title: `好评（${reviewSummary.value.good_count}）` },
  { id: 'medium', title: `中评（${reviewSummary.value.medium_count}）` },
  { id: 'bad', title: `差评（${reviewSummary.value.bad_count}）` }
])
const imageFilter = computed(() => ({ id: 'image', title: `有图（${reviewSummary.value.image_count}）` }))
const secondaryFilters = [
  { id: 'default', title: '默认' },
  { id: 'latest', title: '最新' },
  { id: 'follow', title: '追评' },
  { id: 'video', title: '有图/视频', wide: true }
]
const rateRows = computed(() => {
  return [
    buildRateRow('good', '好评', reviewSummary.value.good_count),
    buildRateRow('medium', '中评', reviewSummary.value.medium_count),
    buildRateRow('bad', '差评', reviewSummary.value.bad_count)
  ]
})
const comments = ref([])
const replies = ref([])

const filteredComments = computed(() => {
  if (activeRatingFilter.value === 'medium' || activeRatingFilter.value === 'bad') {
    return []
  }
  if (activeRatingFilter.value === 'image' || activeSortFilter.value === 'video') {
    return comments.value.filter((item) => item.hasImage)
  }
  return comments.value
})
const currentComment = computed(() => {
  return comments.value.find((item) => item.id === selectedCommentId.value) || {}
})
const sortText = computed(() => (sortMode.value === 'hot' ? '最热' : '最新'))
const commentTotal = computed(() => reviewSummary.value.total || comments.value.length)
const goodRateText = computed(() => formatPercent(reviewSummary.value.good_rate))
const commentEndText = computed(() => loading.value ? '评价加载中...' : (comments.value.length ? '没有更多评价了' : '暂无评价'))
const replyCount = computed(() => replies.value.length)
const sortedReplies = computed(() => {
  const list = replies.value.map((item, index) => ({
    ...item,
    last: index === replies.value.length - 1
  }))
  if (sortMode.value === 'latest') {
    return list.slice().reverse().map((item, index, arr) => ({
      ...item,
      last: index === arr.length - 1
    }))
  }
  return list
})

onLoad((options = {}) => {
  productId.value = Number(options.id || options.product_id || options.goods_id || 0)
  fetchReviews()
})

/**
 * 拉取商品评价列表。
 *
 * @returns {Promise<void>}
 */
async function fetchReviews() {
  if (!productId.value || loading.value) {
    return
  }

  loading.value = true
  try {
    const res = await getProductReviews({
      id: productId.value,
      page: 1,
      limit: PAGE_LIMIT
    }, { showError: false })

    if (!res || res.code !== 200 || !res.data) {
      comments.value = []
      reviewSummary.value = { ...emptySummary }
      return
    }

    comments.value = normalizeReviews(res.data.list || [])
    reviewSummary.value = normalizeReviewSummary(res.data.summary || {})
  } catch (error) {
    console.error('加载商品评价失败', error)
    comments.value = []
    reviewSummary.value = { ...emptySummary }
  } finally {
    loading.value = false
  }
}

/**
 * 构建评分统计行。
 *
 * @param {string} id 评分类型。
 * @param {string} label 展示标签。
 * @param {number} count 当前类型数量。
 * @returns {Object}
 */
function buildRateRow(id, label, count) {
  const total = Math.max(Number(reviewSummary.value.total || 0), 0)
  const currentCount = Math.max(Number(count || 0), 0)
  const percent = total > 0 ? Math.round((currentCount / total) * 100) : 0
  return {
    id,
    label,
    count: currentCount,
    percent,
    style: `width: ${percent}%;`
  }
}

/**
 * 规范化评价统计。
 *
 * @param {Object} summary 接口返回评价统计。
 * @returns {Object}
 */
function normalizeReviewSummary(summary = {}) {
  const total = Number(summary.total || 0)
  const goodCount = Number(summary.good_count || summary.good || 0)
  const mediumCount = Number(summary.medium_count || summary.medium || 0)
  const badCount = Number(summary.bad_count || summary.bad || 0)
  const imageCount = Number(summary.image_count || summary.has_image_count || 0)
  return {
    total: Number.isFinite(total) ? total : 0,
    good_count: Number.isFinite(goodCount) ? goodCount : 0,
    medium_count: Number.isFinite(mediumCount) ? mediumCount : 0,
    bad_count: Number.isFinite(badCount) ? badCount : 0,
    image_count: Number.isFinite(imageCount) ? imageCount : 0,
    good_rate: Number(summary.good_rate || 0)
  }
}

/**
 * 规范化评价列表。
 *
 * @param {Array<Object>} list 接口返回评价列表。
 * @returns {Array<Object>}
 */
function normalizeReviews(list = []) {
  if (!Array.isArray(list)) {
    return []
  }

  return list.map((item) => {
    const user = item.user && typeof item.user === 'object' ? item.user : {}
    const star = Math.max(0, Math.min(5, Number(item.star || 0)))
    const images = Array.isArray(item.images) ? item.images.filter(Boolean) : []
    return {
      id: Number(item.id || 0),
      name: String(user.nickname || item.nickname || ''),
      fullName: String(user.nickname || item.nickname || ''),
      vip: String(item.vip_name || item.member_level || ''),
      level: String(item.user_level || ''),
      avatar: String(user.avatar || item.avatar || ''),
      date: String(item.create_time || ''),
      content: String(item.content || ''),
      replyContent: String(item.content || ''),
      replyDate: String(item.create_time || ''),
      location: String(item.location || ''),
      replyLikes: Number(item.like_count || item.likes || 0),
      sku: String(item.sku_name || ''),
      weight: String(item.weight || ''),
      useful: Number(item.like_count || item.likes || 0),
      star,
      stars: ratingStars.slice(0, star),
      hasImage: images.length > 0,
      images
    }
  }).filter((item) => item.id > 0)
}

/**
 * 格式化百分比。
 *
 * @param {number|string} value 原始百分比。
 * @returns {string}
 */
function formatPercent(value) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) {
    return '0'
  }
  return String(Math.max(0, Math.min(100, Math.round(number))))
}

/**
 * 返回上一页；无历史页面时回到商品列表。
 *
 * @returns {void}
 */
function handleBack() {
  uni.navigateBack({
    fail() {
      uni.navigateTo({
        url: '/pages/goods/list/index'
      })
    }
  })
}

/**
 * 打开购物车页面。
 *
 * @returns {void}
 */
function openCart() {
  uni.switchTab({
    url: '/pages/cart/index'
  })
}

/**
 * 打开顶部更多菜单的占位交互。
 *
 * @returns {void}
 */
function openMenu() {
  uni.showToast({
    title: '更多功能暂未开放',
    icon: 'none'
  })
}

/**
 * 切换当前评价类型筛选项。
 *
 * @param {string} filterId 筛选项标识。
 * @returns {void}
 */
function selectRatingFilter(filterId) {
  activeRatingFilter.value = filterId
}

/**
 * 切换当前排序或内容类型筛选项。
 *
 * @param {string} filterId 筛选项标识。
 * @returns {void}
 */
function selectSortFilter(filterId) {
  activeSortFilter.value = filterId
}

/**
 * 切换评论“有用”状态。
 *
 * @param {Object} item 评论项。
 * @returns {void}
 */
function toggleUseful(item) {
  if (!item) {
    return
  }
  item.useful += 1
}

/**
 * 打开评论回复弹窗。
 *
 * @param {Object} item 当前评论项。
 * @returns {void}
 */
function openReplyPopup(item) {
  if (!item || !item.id) {
    return
  }
  selectedCommentId.value = item.id
  showReplyPopup.value = true
}

/**
 * 关闭评论回复弹窗。
 *
 * @returns {void}
 */
function closeReplyPopup() {
  showReplyPopup.value = false
  replyText.value = ''
}

/**
 * 切换回复排序方式。
 *
 * @returns {void}
 */
function toggleSort() {
  sortMode.value = sortMode.value === 'hot' ? 'latest' : 'hot'
}

/**
 * 切换单条回复点赞数。
 *
 * @param {Object} reply 回复项。
 * @returns {void}
 */
function toggleReplyLike(reply) {
  if (!reply) {
    return
  }
  reply.likes += 1
}

/**
 * 发送回复。
 *
 * @returns {void}
 */
function sendReply() {
  const content = replyText.value.trim()
  if (!content) {
    uni.showToast({
      title: '请输入回复内容',
      icon: 'none'
    })
    return
  }

  replies.value.unshift({
    id: Date.now(),
    name: '我',
    level: 'LV1',
    avatar: '',
    content,
    time: '刚刚',
    location: '',
    likes: 0
  })
  replyText.value = ''
}
</script>

<style lang="scss">
page {
  min-height: 100%;
  background: #ffffff;
}
</style>

<style lang="scss" scoped>
$comment-red: $brand-primary;
$comment-red-soft: $brand-primary-pale;
$comment-text: #1f2024;
$comment-muted: #7b7d86;
$comment-border: #f0edf0;

.comment-page {
  min-height: 100%;
  background: #ffffff;
}

.comment-nav {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  width: 140rpx;
  height: 64rpx;
  margin-left: -76rpx;
}

.comment-nav__cart,
.comment-nav__more {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 62rpx;
  height: 62rpx;
}

.comment-nav__more {
  margin-left: 16rpx;
}

.comment-nav__item--hover {
  opacity: 0.72;
}

.comment-nav__icon {
  color: #111111;
  font-size: 42rpx;
  line-height: 1;
}

.comment-nav__more-icon {
  color: #111111;
  font-size: 42rpx;
  line-height: 1;
}

.comment-nav__badge {
  position: absolute;
  top: 0;
  right: 4rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 28rpx;
  height: 28rpx;
  padding: 0 6rpx;
  border-radius: 999rpx;
  background: $comment-red;
  color: #ffffff;
  font-size: 19rpx;
  line-height: 28rpx;
  font-weight: 700;
}

.comment-page__body {
  padding: 22rpx 26rpx 54rpx;
}

.rate-panel {
  display: flex;
  padding: 28rpx 12rpx 34rpx;
  border-radius: 22rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 34rpx rgba(65, 41, 46, 0.05);
}

.rate-panel__summary {
  width: 250rpx;
  flex-shrink: 0;
}

.rate-panel__label {
  color: $comment-text;
  font-size: 25rpx;
  line-height: 36rpx;
  font-weight: 700;
}

.rate-panel__score-row {
  display: flex;
  align-items: flex-end;
  margin-top: 14rpx;
}

.rate-panel__score {
  color: $comment-red;
  font-size: 58rpx;
  line-height: 64rpx;
  font-weight: 800;
}

.rate-panel__good {
  margin-bottom: 8rpx;
  margin-left: 10rpx;
  color: $comment-text;
  font-size: 24rpx;
  line-height: 32rpx;
  font-weight: 700;
}

.rate-panel__count {
  display: block;
  margin-top: 14rpx;
  color: $comment-text;
  font-size: 25rpx;
  line-height: 36rpx;
}

.rate-panel__bars {
  flex: 1;
  min-width: 0;
  padding-top: 2rpx;
}

.rate-row {
  display: flex;
  align-items: center;
  height: 46rpx;
}

.rate-row + .rate-row {
  margin-top: 8rpx;
}

.rate-row__label,
.rate-row__count,
.rate-row__percent {
  color: $comment-text;
  font-size: 25rpx;
  line-height: 34rpx;
  font-weight: 700;
}

.rate-row__count {
  width: 66rpx;
  margin-left: 8rpx;
  font-weight: 400;
}

.rate-row__track {
  flex: 1;
  height: 14rpx;
  margin-left: 14rpx;
  overflow: hidden;
  border-radius: 999rpx;
  background: #eeecee;
}

.rate-row__fill {
  height: 14rpx;
  border-radius: 999rpx;
  background: $comment-red;
}

.rate-row__percent {
  width: 58rpx;
  margin-left: 14rpx;
  color: $comment-red;
  text-align: right;
}

.filter-panel {
  margin-top: 34rpx;
}

.filter-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}

.filter-row--secondary {
  margin-top: 20rpx;
}

.filter-chip {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 122rpx;
  height: 48rpx;
  margin-right: 14rpx;
  margin-bottom: 16rpx;
  border-radius: 999rpx;
  background: #f0eded;
  color: $comment-text;
  font-size: 24rpx;
  line-height: 34rpx;
  font-weight: 700;
  padding: 0 10rpx;
}

.filter-chip--small {
  width: 86rpx;
}

.filter-chip--wide {
  width: 128rpx;
}

.filter-chip--image {
  width: 138rpx;
  margin-right: 0;
}

.filter-chip--active {
  background: $comment-red-soft;
  color: $comment-red;
}

.filter-chip--hover {
  opacity: 0.76;
}

.filter-chip__arrow {
  margin-left: 10rpx;
  font-size: 18rpx;
  line-height: 1;
}

.comment-list {
  margin-top: 10rpx;
}

.comment-card {
  padding: 26rpx 20rpx 22rpx;
  border-radius: 22rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 34rpx rgba(65, 41, 46, 0.06);
}

.comment-card + .comment-card {
  margin-top: 22rpx;
}

.comment-card__header {
  display: flex;
  align-items: flex-start;
}

.comment-card__avatar {
  width: 62rpx;
  height: 62rpx;
  flex-shrink: 0;
  border-radius: 50%;
  background: #f3e6e8;
}

.comment-card__user {
  flex: 1;
  min-width: 0;
  margin-left: 18rpx;
}

.comment-card__name-row {
  display: flex;
  align-items: center;
  height: 28rpx;
}

.comment-card__name {
  color: #111111;
  font-size: 26rpx;
  line-height: 28rpx;
  font-weight: 800;
}

.comment-card__vip {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 26rpx;
  margin-left: 10rpx;
  padding: 0 10rpx;
  border-radius: 999rpx;
  background: #2f2110;
  color: #ffd44e;
  font-size: 18rpx;
  line-height: 26rpx;
  font-weight: 800;
}

.comment-card__stars {
  display: flex;
  align-items: center;
  height: 30rpx;
  margin-top: 7rpx;
}

.comment-card__star {
  color: $comment-red;
  font-size: 24rpx;
  line-height: 1;
}

.comment-card__date-row {
  display: flex;
  align-items: center;
  margin-left: 18rpx;
}

.comment-card__date {
  color: $comment-muted;
  font-size: 23rpx;
  line-height: 32rpx;
}

.comment-card__more {
  margin-left: 14rpx;
  color: #595b63;
  font-size: 26rpx;
  line-height: 1;
}

.comment-card__content {
  display: block;
  margin-top: 26rpx;
  margin-left: 80rpx;
  color: $comment-text;
  font-size: 26rpx;
  line-height: 40rpx;
  font-weight: 700;
}

.comment-card__image-scroll {
  width: 100%;
  height: 136rpx;
  margin-top: 18rpx;
}

.comment-card__image-row {
  display: flex;
  height: 136rpx;
  padding-left: 80rpx;
  white-space: nowrap;
}

.comment-card__image {
  width: 180rpx;
  height: 136rpx;
  margin-right: 16rpx;
  border-radius: 10rpx;
  background: #f3e6e8;
}

.comment-card__footer {
  display: flex;
  align-items: center;
  margin-top: 18rpx;
  margin-left: 80rpx;
  color: #2f3033;
  font-size: 24rpx;
  line-height: 34rpx;
}

.comment-card__sku,
.comment-card__weight {
  color: #2f3033;
}

.comment-card__divider {
  margin: 0 14rpx;
  color: #b9bbc2;
}

.comment-card__actions {
  display: flex;
  align-items: center;
  margin-left: auto;
  color: #62646d;
}

.comment-card__useful {
  color: #62646d;
}

.comment-card__reply {
  margin-left: 24rpx;
  color: #62646d;
}

.comment-card__reply--hover,
.comment-card__action-icon--hover {
  color: $comment-red;
}

.comment-card__action-icon {
  margin-left: 10rpx;
  color: #62646d;
  font-size: 28rpx;
  line-height: 1;
}

.comment-page__end {
  display: block;
  padding: 44rpx 0 14rpx;
  color: #92949c;
  font-size: 26rpx;
  line-height: 36rpx;
  text-align: center;
}

.reply-mask {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 80;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(25, 30, 38, 0.68);
  animation: reply-mask-in 180ms ease-out both;
}

.reply-dialog {
  position: relative;
  width: 100%;
  height: 1000rpx;
  overflow: hidden;
  border-top-left-radius: 32rpx;
  border-top-right-radius: 32rpx;
  background: #ffffff;
  animation: reply-dialog-in 240ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
}

@keyframes reply-mask-in {
  0% {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

@keyframes reply-dialog-in {
  0% {
    transform: translateY(100%);
  }

  100% {
    transform: translateY(0);
  }
}

.reply-dialog__header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 96rpx;
  border-bottom: 1px solid $comment-border;
}

.reply-dialog__title {
  color: $comment-text;
  font-size: 34rpx;
  line-height: 48rpx;
  font-weight: 800;
}

.reply-dialog__close {
  position: absolute;
  right: 28rpx;
  top: 28rpx;
  color: #4c4f57;
  font-size: 34rpx;
  line-height: 40rpx;
}

.reply-dialog__close--hover {
  opacity: 0.7;
}

.reply-dialog__body {
  height: 788rpx;
  padding: 28rpx 30rpx 0;
}

.reply-dialog__section-title {
  display: block;
  color: $comment-text;
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 800;
}

.current-comment {
  display: flex;
  margin-top: 18rpx;
  padding: 26rpx 22rpx 24rpx;
  border-radius: 18rpx;
  background: #f4f7ff;
}

.current-comment__avatar {
  width: 62rpx;
  height: 62rpx;
  flex-shrink: 0;
  border-radius: 50%;
  background: #ecedf2;
}

.current-comment__content {
  flex: 1;
  min-width: 0;
  margin-left: 22rpx;
}

.current-comment__name-row,
.reply-item__name-row {
  display: flex;
  align-items: center;
}

.current-comment__name,
.reply-item__name {
  color: $comment-text;
  font-size: 25rpx;
  line-height: 34rpx;
  font-weight: 800;
}

.current-comment__level,
.reply-item__level {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 26rpx;
  margin-left: 10rpx;
  padding: 0 8rpx;
  border-radius: 6rpx;
  background: #95b8f6;
  color: #ffffff;
  font-size: 18rpx;
  line-height: 26rpx;
  font-weight: 800;
}

.current-comment__level {
  background: #f2b354;
}

.current-comment__text {
  display: block;
  margin-top: 14rpx;
  color: $comment-text;
  font-size: 24rpx;
  line-height: 38rpx;
}

.current-comment__meta-row,
.reply-item__meta-row {
  display: flex;
  align-items: center;
  margin-top: 18rpx;
  color: $comment-muted;
  font-size: 23rpx;
  line-height: 32rpx;
}

.current-comment__meta-row text + text,
.reply-item__meta-row text + text {
  margin-left: 20rpx;
}

.current-comment__like,
.reply-item__like {
  display: flex;
  align-items: center;
  margin-left: auto;
}

.current-comment__like-icon,
.reply-item__like-icon {
  margin-right: 10rpx;
  color: #4f525b;
  font-size: 28rpx;
  line-height: 1;
}

.reply-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 30rpx;
  margin-bottom: 18rpx;
}

.reply-list__title {
  color: $comment-text;
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 800;
}

.reply-list__sort {
  display: flex;
  align-items: center;
  color: #4f525b;
  font-size: 23rpx;
  line-height: 32rpx;
}

.reply-list__sort--hover {
  opacity: 0.72;
}

.reply-list__sort-icon {
  margin-left: 12rpx;
  font-size: 18rpx;
  line-height: 1;
}

.reply-list {
  height: 388rpx;
}

.reply-item {
  display: flex;
  padding: 0 0 22rpx;
}

.reply-item + .reply-item {
  padding-top: 24rpx;
  border-top: 1px solid $comment-border;
}

.reply-item--last {
  padding-bottom: 8rpx;
}

.reply-item__avatar {
  width: 58rpx;
  height: 58rpx;
  flex-shrink: 0;
  border-radius: 50%;
  background: #ecedf2;
}

.reply-item__content {
  flex: 1;
  min-width: 0;
  margin-left: 22rpx;
}

.reply-item__text {
  display: block;
  margin-top: 12rpx;
  color: $comment-text;
  font-size: 24rpx;
  line-height: 36rpx;
}

.reply-dialog__footer {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  height: 116rpx;
  padding: 18rpx 30rpx;
  border-top: 1px solid $comment-border;
  background: #ffffff;
}

/* #ifndef H5 */
.reply-dialog {
  height: 1034rpx;
}

.reply-dialog__body {
  height: 788rpx;
}

.reply-dialog__footer {
  height: 150rpx;
  padding-bottom: 52rpx;
}
/* #endif */

.reply-dialog__input {
  flex: 1;
  min-width: 0;
  height: 70rpx;
  padding: 0 28rpx;
  border-radius: 999rpx;
  background: #f8f8f8;
  color: $comment-text;
  font-size: 25rpx;
  line-height: 70rpx;
}

.reply-dialog__placeholder {
  color: #b5b6bd;
}

.reply-dialog__send {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 118rpx;
  height: 70rpx;
  margin-left: 18rpx;
  border-radius: 999rpx;
  background: linear-gradient(90deg, $brand-primary-light 0%, $brand-primary 100%);
  color: #ffffff;
  font-size: 25rpx;
  line-height: 34rpx;
  font-weight: 700;
}

.reply-dialog__send--hover {
  opacity: 0.82;
}
</style>
