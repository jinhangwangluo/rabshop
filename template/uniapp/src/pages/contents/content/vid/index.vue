<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="video-detail-page" :class="{ 'video-detail-page--with-bar': showCommentBar }">
    <!-- 加载状态 -->
    <view v-if="loading" class="video-state">
      <text class="video-state__text">加载中...</text>
    </view>

    <!-- 错误状态 -->
    <view v-else-if="errorMessage" class="video-state">
      <text class="video-state__text">{{ errorMessage }}</text>
      <view class="video-state__button" @click="fetchDetail">
        <text>重新加载</text>
      </view>
    </view>

    <template v-else>
      <view class="video-hero">
        <image v-if="!videoSrc" class="video-hero__poster" :src="detail.cover" mode="aspectFill"></image>
        <!-- 缓冲 loading -->
        <view v-if="videoLoading" class="video-loading-mask">
          <text class="video-loading-mask__text">加载中...</text>
        </view>
        <!-- 加载失败 -->
        <view v-if="videoError" class="video-error-mask" @click="retryVideo">
          <text class="video-error-mask__text">视频加载失败，点击重试</text>
        </view>
        <!-- 试看结束引导 -->
        <view v-if="showTrialEndGuide" class="trial-end-mask">
          <text class="trial-end-mask__title">试看结束</text>
          <text class="trial-end-mask__desc">解锁完整版 {{ durationText }}</text>
          <view class="trial-end-mask__button" @click="handleUnlockClick">
            <text>{{ buyLoading ? '处理中...' : '立即解锁' }}</text>
          </view>
        </view>

        <video
          id="videoPlayer"
          v-if="videoSrc && !videoError"
          :src="videoSrc"
          :poster="detail.cover"
          :controls="false"
          :autoplay="true"
          :show-center-play-btn="false"
          :show-fullscreen-btn="false"
          :enable-progress-gesture="false"
          objectFit="contain"
          class="video-player"
          @timeupdate="handleVideoTimeUpdate"
          @ended="handleVideoEnded"
          @play="handleVideoPlay"
          @pause="handleVideoPause"
          @error="handleVideoError"
          @waiting="handleVideoWaiting"
          @loadeddata="handleVideoReady"
        />
        <view class="video-hero__mask"></view>

        <!-- 返回按钮（cover-view 兼容小程序原生层） -->
        <cover-view class="video-back-button" :style="videoBackButtonStyle" @click="handleBack">
          <text class="iconfont icon-back video-top-button__icon"></text>
        </cover-view>


        <!-- 锁屏面板 -->
        <view class="video-hero__body" v-if="!videoSrc">
          <view v-if="!isUnlocked" class="lock-panel">
            <view class="lock-panel__center">
              <view class="lock-panel__button" :class="{ 'lock-panel__button--disabled': buyLoading }" @click="handleUnlockClick">
                <text class="iconfont icon-lock lock-panel__button-icon"></text>
                <text class="lock-panel__button-text">{{ buyLoading ? '处理中...' : '解锁视频' }}</text>
              </view>
              <text v-if="detail.is_trial" class="lock-panel__trial" @click="handleTrial">免费试看</text>
            </view>
            <view class="lock-panel__footer">
              <view class="lock-panel__meta-item">
                <text class="iconfont icon-recordlight lock-panel__meta-icon"></text>
                <text class="lock-panel__meta-text">完整版 {{ durationText }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>

      <!-- 播放控制栏 -->
      <cover-view v-if="videoSrc" class="player-control-bar">
        <cover-view class="player-control-bar__btn" @click="handleTogglePlay">
          <text class="iconfont player-control-bar__play-icon" :class="isPlaying ? 'icon-stop' : 'icon-bofang'"></text>
        </cover-view>
        <text class="player-control-bar__time">{{ currentTimeText }}</text>
        <cover-view
          class="player-control-bar__track"
          @touchstart="handleProgressTouchStart"
          @touchmove="handleProgressTouchMove"
          @touchend="handleProgressTouchEnd"
          @click="handleProgressClick"
        >
          <cover-view class="player-control-bar__track-bg"></cover-view>
          <cover-view class="player-control-bar__track-value" :style="progressBarStyle"></cover-view>
          <cover-view class="player-control-bar__track-dot" :style="progressDotStyle"></cover-view>
        </cover-view>
        <text class="player-control-bar__time">{{ durationText }}</text>
        <cover-view class="player-control-bar__btn" @click="handleFullscreen">
          <text class="iconfont icon-xuanzhuanpingmu player-control-bar__fullscreen-icon"></text>
        </cover-view>
      </cover-view>

      <view class="video-detail-page__body">
        <!-- 信息卡片 -->
        <view class="card-block info-card">
          <text class="info-card__title">{{ detail.title }}</text>
          <text v-if="detail.description" class="info-card__subtitle">{{ detail.description }}</text>
          <view class="info-card__meta-row">
            <text class="info-card__meta-text">{{ viewCountText }}</text>
            <text class="info-card__meta-text">{{ detail.create_time }}</text>
          </view>
        </view>

        <!-- 互动栏 -->
        <view class="card-block action-card">
          <view class="action-card__item" @click="handleAction('like')">
            <text class="iconfont action-card__icon" :class="isLiked ? 'icon-likefill action-card__icon--active' : 'icon-like'"></text>
            <text class="action-card__text" :class="{ 'action-card__text--active': isLiked }">{{ detail.like_count || 0 }}</text>
          </view>
          <view class="action-card__item" @click="handleAction('comment')">
            <text class="iconfont icon-comment action-card__icon"></text>
            <text class="action-card__text">{{ commentTotal }}</text>
          </view>
          <view class="action-card__item" @click="handleAction('collect')">
            <text class="iconfont action-card__icon" :class="isCollected ? 'icon-favorfill action-card__icon--active' : 'icon-favor'"></text>
            <text class="action-card__text" :class="{ 'action-card__text--active': isCollected }">收藏</text>
          </view>
          <view class="action-card__item" @click="handleAction('cache')">
            <text class="iconfont icon-record action-card__icon" :class="{ 'action-card__icon--active': cachedVideoPath }"></text>
            <text class="action-card__text" :class="{ 'action-card__text--active': cachedVideoPath }">{{ cacheButtonText }}</text>
          </view>
        </view>

        <!-- 作者卡片 -->
        <view class="card-block author-card">
          <view class="author-card__left">
            <image class="author-card__avatar" :src="authorAvatar" mode="aspectFill"></image>
            <view class="author-card__meta">
              <text class="author-card__name">{{ authorName }}</text>
            </view>
          </view>
          <view
            class="author-card__button"
            :class="{ 'author-card__button--active': isFollowing, 'author-card__button--loading': followLoading }"
            @click="handleToggleFollow"
          >
            <text>{{ followButtonText }}</text>
          </view>
        </view>

        <!-- 评论区 -->
        <view class="card-block comment-card">
          <view class="comment-card__head">
            <text class="comment-card__title">评论 ({{ commentTotal }})</text>
            <view class="comment-card__sorts">
              <text
                v-for="item in commentSortOptions"
                :key="item.value"
                class="comment-card__sort"
                :class="{ 'comment-card__sort--active': commentSort === item.value }"
                @click="handleChangeCommentSort(item.value)"
              >{{ item.label }}</text>
            </view>
          </view>

          <view v-if="commentLoading" class="video-state">
            <text class="video-state__text">评论加载中...</text>
          </view>

          <view v-else-if="commentError" class="comment-empty">
            <text class="comment-empty__text">{{ commentError }}</text>
            <view class="comment-empty__button" @click="fetchComments">
              <text>重新加载</text>
            </view>
          </view>

          <view v-else-if="!comments.length" class="comment-empty">
            <text class="comment-empty__text">暂无评论</text>
          </view>

          <view v-else class="comment-list">
            <view v-for="comment in sortedComments" :key="comment.id" class="comment-item">
              <image class="comment-item__avatar" :src="(comment.user && comment.user.avatar) || DEFAULT_AVATAR" mode="aspectFill"></image>
              <view class="comment-item__main">
                <view class="comment-item__top">
                  <text class="comment-item__name">{{ (comment.user && comment.user.nickname) || '匿名' }}</text>
                </view>
                <text class="comment-item__content">{{ comment.content }}</text>
                <view class="comment-item__meta">
                  <text class="comment-item__time">{{ comment.create_time }}</text>
                  <text class="comment-item__reply" @click="handleReply(comment, comment)">回复</text>
                </view>

                <view v-if="comment.children && comment.children.length" class="reply-list">
                  <view v-for="reply in comment.children" :key="reply.id" class="reply-item">
                    <image class="reply-item__avatar" :src="(reply.user && reply.user.avatar) || DEFAULT_AVATAR" mode="aspectFill"></image>
                    <view class="reply-item__body">
                      <view class="reply-item__top">
                        <text class="reply-item__name">{{ (reply.user && reply.user.nickname) || '匿名' }}</text>
                        <text v-if="reply.reply_to_nickname" class="reply-item__reply-to">回复 @{{ reply.reply_to_nickname }}</text>
                      </view>
                      <text class="reply-item__content">{{ reply.content }}</text>
                      <view class="reply-item__meta">
                        <text class="reply-item__time">{{ reply.create_time }}</text>
                        <text class="reply-item__reply" @click="handleReply(reply, comment)">回复</text>
                      </view>
                    </view>
                  </view>
                </view>
              </view>
            </view>
          </view>
        </view>
      </view>

      <view class="comment-bar">
        <view class="comment-bar__field">
          <input
            v-model="commentText"
            class="comment-bar__input"
            :placeholder="commentInputPlaceholder"
            placeholder-class="comment-bar__placeholder"
            confirm-type="send"
            @confirm="handlePublishComment"
          />
          <text v-if="replyTarget" class="comment-bar__cancel" @click="clearReplyTarget">取消</text>
        </view>
        <text
          class="comment-bar__submit"
          :class="{ 'comment-bar__submit--disabled': commentSubmitting }"
          @click="handlePublishComment"
        >{{ commentSubmitting ? '发布中' : '发布' }}</text>
      </view>
    </template>
  </view>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, ref } from 'vue'
import { onHide, onLoad, onShow, onUnload } from '@dcloudio/uni-app'
import { buyNow } from '@/api/cart'
import { getContentDetail, toggleContentFavorite, toggleContentLike } from '@/api/content'
import { createComment, getCommentList, replyComment } from '@/api/comment'
import { getMerchantFollowStatus, toggleMerchantFollow } from '@/api/merchant'
import { getUserInfo } from '@/utils/auth'
import { resolveAssetUrl } from '@/utils/runtime_config'

const DEFAULT_AVATAR = '/static/images/avatar_default.jpg'
const CONTENT_ORDER_TYPE = 1

function formatTime(seconds = 0) {
  const safeSeconds = Math.max(Number(seconds) || 0, 0)
  const minutes = Math.floor(safeSeconds / 60)
  const remainSeconds = safeSeconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(remainSeconds).padStart(2, '0')}`
}

function formatViewCount(count = 0) {
  const num = Number(count || 0)
  if (num >= 10000) {
    return (num / 10000).toFixed(1).replace(/\.0$/, '') + '万次观看'
  }
  return num + '次观看'
}

function normalizeImageUrl(image = '') {
  if (typeof image === 'string') {
    return resolveAssetUrl(image)
  }

  if (image && typeof image === 'object') {
    return resolveAssetUrl(image.src)
  }

  return ''
}

function normalizeFollowStatus(value) {
  const normalized = String(value).trim().toLowerCase()
  return value === true || normalized === 'true' || Number(value) === 1
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

defineOptions({ name: 'ContentVidPage' })

const instance = getCurrentInstance()

const detail = ref({})
const loading = ref(true)
const errorMessage = ref('')
const articleId = ref(0)
const isUnlocked = ref(false)
const isPlaying = ref(false)
const isFollowing = ref(false)
const followLoading = ref(false)
const isCollected = ref(false)
const collectSubmitting = ref(false)
const cacheLoading = ref(false)
const cachedVideoPath = ref('')
const isLiked = ref(false)
const likeSubmitting = ref(false)
const currentSeconds = ref(0)
const videoSrc = ref('')
const trialPlaying = ref(false)
const videoError = ref(false)
const videoLoading = ref(false)
const showTrialEndGuide = ref(false)
const savedSeconds = ref(0)
const wasPlaying = ref(false)
const videoContext = ref(null)
const commentSort = ref('latest')
const commentText = ref('')
const replyTarget = ref(null)
const statusBarHeight = ref(0)
const comments = ref([])
const commentTotal = ref(0)
const commentLoading = ref(false)
const commentError = ref('')
const commentSubmitting = ref(false)
const buyLoading = ref(false)
const shouldRefreshOnShow = ref(false)
const commentSortOptions = ref([
  { label: '最热', value: 'hot' },
  { label: '最新', value: 'latest' }
])

const showCommentBar = computed(() => {
  return true
})

const commentInputPlaceholder = computed(() => {
  if (!replyTarget.value) {
    return '说点什么...'
  }
  return `回复 ${(replyTarget.value.user && replyTarget.value.user.nickname) || '用户'}`
})

const cacheButtonText = computed(() => {
  if (cacheLoading.value) {
    return '缓存中'
  }
  if (cachedVideoPath.value) {
    return '已缓存'
  }
  return '缓存'
})

const currentTimeText = computed(() => {
  return formatTime(currentSeconds.value)
})

const durationText = computed(() => {
  return formatTime(detail.value.media_duration)
})

const viewCountText = computed(() => {
  return formatViewCount(detail.value.view_count)
})

const currentMerchantInfo = computed(() => {
  const merchantInfo = detail.value.merchant_info
  const storeInfo = detail.value.store_info
  const merchant = detail.value.merchant
  if (merchantInfo && typeof merchantInfo === 'object') {
    return merchantInfo
  }
  if (storeInfo && typeof storeInfo === 'object') {
    return storeInfo
  }
  return merchant && typeof merchant === 'object' ? merchant : {}
})

const authorName = computed(() => {
  const merchant = currentMerchantInfo.value
  const name = merchant.name || merchant.mer_name || detail.value.author
  return String(name || (isPlatformContent.value ? '平台' : '店铺'))
})

const authorAvatar = computed(() => {
  const merchant = currentMerchantInfo.value
  return normalizeImageUrl(merchant.avatar || merchant.logo || detail.value.avatar || DEFAULT_AVATAR) || DEFAULT_AVATAR
})

const isPlatformContent = computed(() => {
  return Number(detail.value.mer_id || 0) === 0
})

const followMerchantId = computed(() => {
  const merchantId = Number(detail.value.mer_id || 0)
  return merchantId > 0 ? merchantId : 0
})

const followButtonText = computed(() => {
  if (followLoading.value) {
    return '处理中'
  }
  if (isFollowing.value) {
    return '已关注'
  }
  return isPlatformContent.value ? '关注平台' : '关注店铺'
})

const progressPercent = computed(() => {
  const total = Number(detail.value.media_duration || 0) || 1
  const current = Math.min(currentSeconds.value, total)
  return Math.max((current / total) * 100, 0)
})

const progressBarStyle = computed(() => {
  return `width: ${progressPercent.value}%;`
})

const progressDotStyle = computed(() => {
  const safePercent = Math.max(Math.min(progressPercent.value, 100), 0)
  return `left: ${safePercent}%;`
})

const sortedComments = computed(() => {
  const list = Array.isArray(comments.value) ? comments.value.slice() : []
  if (commentSort.value === 'hot') {
    return list.sort((a, b) => Number(b.is_hot || 0) - Number(a.is_hot || 0))
  }
  return list
})

const videoBackButtonStyle = computed(() => {
  const safeTop = Number(statusBarHeight.value || 0)
  return { top: `${safeTop + 16}px` }
})

onLoad((options = {}) => {
  syncStatusBarHeight()
  articleId.value = Number(options.id || 1)
  fetchDetail()
})

onShow(() => {
  if (shouldRefreshOnShow.value && articleId.value && !loading.value) {
    shouldRefreshOnShow.value = false
    fetchDetail()
    return
  }

  if (videoSrc.value && wasPlaying.value) {
    nextTick(() => {
      videoContext.value = uni.createVideoContext('videoPlayer')
      videoContext.value.play()
      isPlaying.value = true
    })
  }
})

onHide(() => {
  wasPlaying.value = isPlaying.value
  savedSeconds.value = currentSeconds.value
  if (videoContext.value) {
    videoContext.value.pause()
  }
  isPlaying.value = false
})

onUnload(() => {
  if (videoContext.value) {
    videoContext.value.stop()
    videoContext.value = null
  }
})

function syncStatusBarHeight() {
  const systemInfo = typeof uni.getSystemInfoSync === 'function' ? uni.getSystemInfoSync() : {}
  statusBarHeight.value = Number(systemInfo.statusBarHeight || 0)
}

function getVideoContext() {
  if (!videoContext.value) {
    videoContext.value = uni.createVideoContext('videoPlayer')
  }
  return videoContext.value
}

async function fetchDetail() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await getContentDetail({ id: articleId.value }, {
      showError: false,
      loginRedirect: false
    })
    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '内容加载失败'
      return
    }
    const data = res.data
    if (Number(data.content_type) !== 6) {
      errorMessage.value = '不支持的内容类型'
      return
    }
    detail.value = data
    isFollowing.value = false
    isUnlocked.value = String(data.access_state || '') === 'learning' || Boolean(data.can_learn)
    isLiked.value = Boolean(data.is_liked)
    isCollected.value = Boolean(data.is_favorite)
    restoreCachedVideoPath()
    if (isUnlocked.value) {
      videoSrc.value = data.media_url || ''
      trialPlaying.value = false
      showTrialEndGuide.value = false
    } else {
      videoSrc.value = ''
    }
    syncFollowStatus()
    fetchComments()
  } catch (e) {
    errorMessage.value = '内容加载失败'
  } finally {
    loading.value = false
  }
}

async function fetchComments() {
  commentLoading.value = true
  commentError.value = ''
  try {
    const res = await getCommentList({ article_id: articleId.value }, {
      showError: false
    })
    if (res && res.code === 200 && res.data) {
      comments.value = Array.isArray(res.data.list) ? res.data.list : []
      commentTotal.value = Number(res.data.count || 0)
      return
    }
    commentError.value = (res && res.msg) || '评论加载失败'
  } catch (e) {
    commentError.value = '评论加载失败'
  } finally {
    commentLoading.value = false
  }
}

function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.switchTab({ url: '/pages/category/index' })
    }
  })
}

function handleMore() {
  uni.showToast({ title: '更多操作开发中', icon: 'none' })
}

async function syncFollowStatus() {
  try {
    const res = await getMerchantFollowStatus({ merchant_id: followMerchantId.value }, {
      showError: false,
      loginRedirect: false
    })
    if (res && res.code === 200 && res.data) {
      isFollowing.value = normalizeFollowStatus(res.data.is_followed)
    }
  } catch (e) {
    isFollowing.value = false
  }
}

function handleTogglePlay() {
  if (videoError.value) {
    retryVideo()
    return
  }
  const ctx = getVideoContext()
  if (isPlaying.value) {
    ctx.pause()
  } else {
    ctx.play()
  }
}

function handleFullscreen() {
  const ctx = getVideoContext()
  ctx.requestFullScreen({ direction: 0 })
}

function buildContentCartPayload() {
  return {
    goods_id: Number(detail.value.id || articleId.value || 0),
    cart_num: 1,
    product_attr_unique: '',
    order_type: CONTENT_ORDER_TYPE
  }
}

function playUnlockedVideo() {
  const mediaUrl = String(detail.value.media_url || '').trim()
  if (!mediaUrl) {
    uni.showToast({ title: '视频资源暂不可用', icon: 'none' })
    return
  }

  showTrialEndGuide.value = false
  savedSeconds.value = currentSeconds.value
  trialPlaying.value = false
  videoSrc.value = ''
  videoError.value = false
  nextTick(() => {
    videoSrc.value = mediaUrl
  })
}

async function handleUnlockClick() {
  if (buyLoading.value) {
    return
  }

  if (isUnlocked.value) {
    playUnlockedVideo()
    return
  }

  if (!detail.value.can_buy) {
    uni.showToast({ title: '当前视频暂不可购买', icon: 'none' })
    return
  }

  buyLoading.value = true
  try {
    const res = await buyNow(buildContentCartPayload(), {
      showError: false,
      redirect: `/pages/contents/content/vid/index?id=${articleId.value}`
    })

    if (!res || res.code !== 200 || !res.data) {
      if (res && res.code === 401) {
        return
      }
      uni.showToast({ title: (res && res.msg) || '购买入口打开失败', icon: 'none' })
      return
    }

    const cartIds = collectCartIds(res.data)
    if (!cartIds.length) {
      uni.showToast({ title: '未获取到结算商品', icon: 'none' })
      return
    }

    shouldRefreshOnShow.value = true
    showTrialEndGuide.value = false
    savedSeconds.value = currentSeconds.value
    uni.navigateTo({
      url: `/pages/orders/confirm/index?cart_ids=${encodeURIComponent(cartIds.join(','))}&is_new=1`
    })
  } catch (e) {
    uni.showToast({ title: '购买入口打开失败', icon: 'none' })
  } finally {
    buyLoading.value = false
  }
}

function handleTrial() {
  if (!detail.value.trial_media_url) {
    uni.showToast({ title: '暂无试看内容', icon: 'none' })
    return
  }
  showTrialEndGuide.value = false
  trialPlaying.value = true
  videoSrc.value = detail.value.trial_media_url
  videoError.value = false
}

// --- video events ---

function handleVideoTimeUpdate(event = {}) {
  currentSeconds.value = Math.floor(Number(event.detail && event.detail.currentTime) || 0)
}

function handleVideoEnded() {
  isPlaying.value = false
  if (trialPlaying.value && !isUnlocked.value) {
    showTrialEndGuide.value = true
  }
}

function handleVideoPlay() {
  isPlaying.value = true
  videoLoading.value = false
}

function handleVideoPause() {
  isPlaying.value = false
}

function handleVideoError() {
  isPlaying.value = false
  videoLoading.value = false
  videoError.value = true
  uni.showToast({ title: '视频加载失败，请重试', icon: 'none' })
}

function handleVideoWaiting() {
  videoLoading.value = true
}

function handleVideoReady() {
  videoLoading.value = false
  videoContext.value = uni.createVideoContext('videoPlayer')
  // 续播：解锁后 seek 到之前位置
  if (savedSeconds.value > 0 && isUnlocked.value) {
    nextTick(() => {
      videoContext.value.seek(savedSeconds.value)
      savedSeconds.value = 0
    })
  }
}

function retryVideo() {
  videoError.value = false
  videoLoading.value = true
  const src = videoSrc.value
  videoSrc.value = ''
  nextTick(() => {
    videoSrc.value = src
  })
}

function handleProgressClick(event = {}) {
  seekByTouch(event)
}

function handleProgressTouchStart(event = {}) {
  seekByTouch(event)
}

function handleProgressTouchMove(event = {}) {
  seekByTouch(event)
}

function handleProgressTouchEnd(event = {}) {
  seekByTouch(event)
}

function seekByTouch(event = {}) {
  if (!videoSrc.value) return
  const touch = (event.touches && event.touches[0])
    || (event.changedTouches && event.changedTouches[0])
    || (event.detail)
  const touchX = Number(touch && touch.x) || 0
  if (!touchX) return
  const query = uni.createSelectorQuery().in(instance.proxy)
  query.select('.player-control-bar__track').boundingClientRect((rect) => {
    if (!rect) return
    const percent = Math.max(0, Math.min(1, (touchX - rect.left) / rect.width))
    const targetTime = Math.floor(percent * (detail.value.media_duration || 0))
    currentSeconds.value = targetTime
    const ctx = getVideoContext()
    ctx.seek(targetTime)
  }).exec()
}

function getCacheStorageKey() {
  return `content_video_cache_${Number(articleId.value || 0)}`
}

function restoreCachedVideoPath() {
  const cachedPath = String(uni.getStorageSync(getCacheStorageKey()) || '').trim()
  cachedVideoPath.value = cachedPath
}

function downloadVideoFile(mediaUrl = '') {
  return new Promise((resolve, reject) => {
    uni.downloadFile({
      url: mediaUrl,
      success: resolve,
      fail: reject
    })
  })
}

function saveVideoFile(tempFilePath = '') {
  return new Promise((resolve, reject) => {
    uni.saveFile({
      tempFilePath,
      success: resolve,
      fail: reject
    })
  })
}

async function handleAction(type = '') {
  if (type === 'like') {
    await handleToggleLike()
    return
  }
  if (type === 'collect') {
    await handleToggleCollect()
    return
  }
  if (type === 'comment') {
    uni.showToast({ title: '评论区在下方', icon: 'none' })
    return
  }
  if (type === 'cache') {
    await handleCacheVideo()
  }
}

async function handleToggleCollect() {
  if (collectSubmitting.value) {
    return
  }

  collectSubmitting.value = true
  try {
    const res = await toggleContentFavorite(
      { article_id: articleId.value },
      {
        showError: false,
        redirect: `/pages/contents/content/vid/index?id=${articleId.value}`
      }
    )
    if (!res || res.code !== 200 || !res.data) {
      if (res && res.code === 401) {
        return
      }
      uni.showToast({ title: (res && res.msg) || '收藏操作失败', icon: 'none' })
      return
    }

    isCollected.value = Boolean(res.data.is_favorite)
    uni.showToast({ title: isCollected.value ? '已收藏' : '已取消收藏', icon: 'none' })
  } catch (e) {
    uni.showToast({ title: '收藏操作失败', icon: 'none' })
  } finally {
    collectSubmitting.value = false
  }
}

async function handleCacheVideo() {
  if (cacheLoading.value) {
    return
  }

  if (cachedVideoPath.value) {
    uni.showToast({ title: '视频已缓存', icon: 'none' })
    return
  }

  if (!isUnlocked.value) {
    uni.showToast({ title: '请先解锁视频', icon: 'none' })
    return
  }

  const mediaUrl = String(detail.value.media_url || '').trim()
  if (!mediaUrl) {
    uni.showToast({ title: '视频资源暂不可用', icon: 'none' })
    return
  }

  // #ifdef H5
  uni.showToast({ title: '当前端暂不支持缓存视频', icon: 'none' })
  return
  // #endif

  // #ifndef H5
  if (typeof uni.downloadFile !== 'function' || typeof uni.saveFile !== 'function') {
    uni.showToast({ title: '当前端暂不支持缓存视频', icon: 'none' })
    return
  }

  cacheLoading.value = true
  uni.showLoading({
    title: '缓存中...',
    mask: true
  })
  try {
    const downloadResult = await downloadVideoFile(mediaUrl)
    const tempFilePath = String(downloadResult.tempFilePath || '').trim()
    if (Number(downloadResult.statusCode || 0) !== 200 || !tempFilePath) {
      uni.showToast({ title: '视频缓存失败', icon: 'none' })
      return
    }

    const saveResult = await saveVideoFile(tempFilePath)
    const savedFilePath = String(saveResult.savedFilePath || '').trim()
    if (!savedFilePath) {
      uni.showToast({ title: '视频缓存失败', icon: 'none' })
      return
    }

    cachedVideoPath.value = savedFilePath
    uni.setStorageSync(getCacheStorageKey(), savedFilePath)
    uni.showToast({ title: '缓存完成', icon: 'success' })
  } catch (e) {
    uni.showToast({ title: '视频缓存失败', icon: 'none' })
  } finally {
    cacheLoading.value = false
    uni.hideLoading()
  }
  // #endif
}

async function handleToggleLike() {
  if (likeSubmitting.value) {
    return
  }

  likeSubmitting.value = true
  try {
    const res = await toggleContentLike({ article_id: articleId.value }, {
      showError: false
    })
    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({ title: (res && res.msg) || '点赞失败', icon: 'none' })
      return
    }

    isLiked.value = Boolean(res.data.is_liked)
    detail.value.like_count = Number(res.data.like_count || 0)
    uni.showToast({ title: isLiked.value ? '已点赞' : '已取消点赞', icon: 'none' })
  } catch (e) {
    uni.showToast({ title: '点赞失败', icon: 'none' })
  } finally {
    likeSubmitting.value = false
  }
}

async function handleToggleFollow() {
  if (followLoading.value) {
    return
  }

  followLoading.value = true
  try {
    const res = await toggleMerchantFollow(
      { merchant_id: followMerchantId.value },
      {
        showError: false,
        redirect: `/pages/contents/content/vid/index?id=${articleId.value}`
      }
    )
    if (!res || res.code !== 200 || !res.data) {
      if (res && res.code === 401) {
        return
      }
      uni.showToast({
        title: (res && res.msg) || '关注操作失败',
        icon: 'none'
      })
      return
    }

    isFollowing.value = normalizeFollowStatus(res.data.is_followed)
    uni.showToast({
      title: isFollowing.value
        ? (isPlatformContent.value ? '已关注平台' : '已关注店铺')
        : '已取消关注',
      icon: 'none'
    })
  } catch (e) {
    uni.showToast({
      title: '关注操作失败',
      icon: 'none'
    })
  } finally {
    followLoading.value = false
  }
}

function handleChangeCommentSort(value = 'latest') {
  commentSort.value = value
}

function clearReplyTarget() {
  replyTarget.value = null
}

function handleReply(target = {}, root = {}) {
  const rootId = Number(root.id || target.root_id || target.pid || target.id || 0)
  if (!rootId) {
    uni.showToast({ title: '回复目标无效', icon: 'none' })
    return
  }
  replyTarget.value = {
    ...target,
    pid: rootId
  }
}

function handleAt() {
  uni.showToast({ title: '@ 功能开发中', icon: 'none' })
}

function getCurrentCommentUser() {
  const userInfo = getUserInfo()
  return {
    uid: Number(userInfo.uid || userInfo.id || 0),
    nickname: String(userInfo.nickname || userInfo.name || ''),
    avatar: String(userInfo.avatar || '')
  }
}

function appendCreatedComment(comment = {}, options = {}) {
  if (!comment || !comment.id) {
    return
  }

  const nextComment = {
    ...comment,
    user: comment.user || getCurrentCommentUser(),
    children: Array.isArray(comment.children) ? comment.children : []
  }
  if (options.isReply && !nextComment.reply_to_nickname && options.replyToNickname) {
    nextComment.reply_to_nickname = options.replyToNickname
  }

  if (options.isReply) {
    const rootId = Number(options.replyRootId || nextComment.pid || nextComment.root_id || 0)
    comments.value = comments.value.map((item) => {
      if (Number(item.id || 0) !== rootId) {
        return item
      }
      const children = Array.isArray(item.children) ? item.children.slice() : []
      return {
        ...item,
        children: children.concat(nextComment)
      }
    })
    return
  }

  comments.value = [nextComment].concat(comments.value || [])
  commentTotal.value += 1
}

async function handlePublishComment() {
  const content = String(commentText.value || '').trim()
  if (!content) {
    uni.showToast({ title: '请输入评论内容', icon: 'none' })
    return
  }
  if (commentSubmitting.value) {
    return
  }

  commentSubmitting.value = true
  try {
    const payload = {
      article_id: articleId.value,
      content,
      images: []
    }
    const res = replyTarget.value
      ? await replyComment({ ...payload, pid: Number(replyTarget.value.pid || 0) }, { showError: false })
      : await createComment(payload, { showError: false })

    if (!res || res.code !== 200) {
      uni.showToast({ title: (res && res.msg) || '评论提交失败', icon: 'none' })
      return
    }

    const isReply = !!replyTarget.value
    const replyRootId = Number(replyTarget.value && replyTarget.value.pid || 0)
    const replyToNickname = String(replyTarget.value && replyTarget.value.user && replyTarget.value.user.nickname || '')
    commentText.value = ''
    commentError.value = ''
    appendCreatedComment(res.data || {}, {
      isReply,
      replyRootId,
      replyToNickname
    })
    clearReplyTarget()
    uni.showToast({ title: isReply ? '回复成功' : '评论成功', icon: 'success' })
  } catch (e) {
    uni.showToast({ title: '评论提交失败', icon: 'none' })
  } finally {
    commentSubmitting.value = false
  }
}
</script>

<style lang="scss">
page {
  background: #f3f4f6;
}
</style>

<style lang="scss" scoped>
.video-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400rpx;
  padding: 80rpx 32rpx;

  &__text {
    font-size: 28rpx;
    line-height: 40rpx;
    color: #8b8b92;
  }

  &__button {
    margin-top: 24rpx;
    padding: 16rpx 48rpx;
    border-radius: 999rpx;
    background: var(--brand-primary, #ea580c);

    text {
      font-size: 26rpx;
      line-height: 1;
      font-weight: 700;
      color: #ffffff;
    }
  }
}

.video-detail-page {
  min-height: 100%;
  background: #f3f4f6;

  &--with-bar {
    .video-detail-page__body {
      padding-bottom: 180rpx;
    }
  }

  &__body {
    padding: 24rpx;
    box-sizing: border-box;
  }
}

.video-hero {
  position: relative;
  height: 422rpx;
  overflow: hidden;
  background: #000000;

  &__poster,
  &__mask,
  &__body {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
  }

  &__poster {
    width: 100%;
    height: 100%;
  }

  &__mask {
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.62) 0%, rgba(0, 0, 0, 0.08) 32%, rgba(0, 0, 0, 0.16) 58%, rgba(0, 0, 0, 0.82) 100%);
  }

  &__body {
    z-index: 2;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 0 24rpx 24rpx;
    box-sizing: border-box;
  }
}

.video-player {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1;
  width: 100%;
  height: 100%;
}

/* 缓冲遮罩 */
.video-loading-mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);

  &__text {
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.8);
  }
}

/* 错误遮罩 */
.video-error-mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.78);

  &__text {
    padding: 20rpx 40rpx;
    border-radius: 999rpx;
    font-size: 26rpx;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.15);
  }
}

/* 试看结束引导 */
.trial-end-mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 3;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.82);

  &__title {
    font-size: 38rpx;
    font-weight: 700;
    color: #ffffff;
  }

  &__desc {
    margin-top: 14rpx;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.7);
  }

  &__button {
    margin-top: 36rpx;
    padding: 18rpx 64rpx;
    border-radius: 999rpx;
    background: var(--brand-primary, #ea580c);

    text {
      font-size: 28rpx;
      font-weight: 700;
      color: #ffffff;
    }
  }
}

/* 返回按钮 */
.video-back-button,
.video-more-button {
  position: absolute;
  z-index: 3;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
}

.video-back-button {
  left: 32rpx;
}

.video-more-button {
  right: 32rpx;
}

.video-top-button__icon {
  font-size: 40rpx;
  line-height: 1;
  color: #ffffff;
}

/* 播放控制栏 */
.player-control-bar {
  display: flex;
  align-items: center;
  min-height: 68rpx;
  padding: 10rpx 20rpx;
  background: rgba(0, 0, 0, 0.82);

  &__btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48rpx;
    height: 48rpx;
    flex-shrink: 0;
  }

  &__play-icon {
    font-size: 32rpx;
    line-height: 1;
    color: #ffffff;
  }

  &__fullscreen-icon {
    font-size: 30rpx;
    line-height: 1;
    color: rgba(255, 255, 255, 0.92);
  }

  &__time {
    flex-shrink: 0;
    min-width: 72rpx;
    font-size: 20rpx;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.82);
    text-align: center;
  }

  &__track {
    position: relative;
    flex: 1;
    height: 44rpx;
    margin: 0 12rpx;
    display: flex;
    align-items: center;
	overflow: unset;
  }

  &__track-bg {
    position: absolute;
    left: 0;
    right: 0;
    height: 6rpx;
    border-radius: 999rpx;
    background: rgba(255, 255, 255, 0.24);
  }

  &__track-value {
    position: absolute;
    left: 0;
    height: 6rpx;
    border-radius: 999rpx;
    background: #ffffff;
  }

  &__track-dot {
    position: absolute;
    top: 50%;
    width: 18rpx;
    height: 18rpx;
    margin-left: -9rpx;
    margin-top: -9rpx;
    border-radius: 50%;
    background: #ffffff;
  }
}

.lock-panel {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: space-between;
  padding-top: 74rpx;
  box-sizing: border-box;

  &__center {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  &__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 88rpx;
    padding: 0 42rpx;
    border-radius: 24rpx;
    background: var(--brand-primary, #ea580c);
    box-shadow: 0 8rpx 24rpx rgba(243, 90, 0, 0.24);
  }

  &__button--disabled {
    opacity: 0.72;
  }

  &__button-icon {
    font-size: 30rpx;
    color: #ffffff;
  }

  &__button-text {
    margin-left: 12rpx;
    font-size: 30rpx;
    font-weight: 700;
    color: #ffffff;
  }

  &__trial {
    margin-top: 22rpx;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: underline;
  }

  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__meta-item {
    display: flex;
    align-items: center;
    color: rgba(255, 255, 255, 0.74);
  }

  &__meta-icon {
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.7);
  }

  &__meta-text {
    margin-left: 10rpx;
    font-size: 20rpx;
  }
}

.card-block {
  margin-bottom: 24rpx;
  padding: 28rpx;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 32rpx rgba(15, 23, 42, 0.05);
  box-sizing: border-box;
}

.info-card {
  &__title {
    display: block;
    font-size: 42rpx;
    line-height: 58rpx;
    font-weight: 700;
    color: var(--text-title, #1a1c1c);
  }

  &__subtitle {
    display: block;
    margin-top: 12rpx;
    font-size: 26rpx;
    line-height: 40rpx;
    color: #5b6066;
  }

  &__meta-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 18rpx;
  }

  &__meta-text {
    margin-right: 18rpx;
    font-size: 20rpx;
    color: #7c8086;
  }
}

.action-card {
  display: flex;
  align-items: center;
  justify-content: space-around;
  padding-top: 20rpx;
  padding-bottom: 20rpx;

  &__item {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  &__icon {
    font-size: 42rpx;
    color: #60656b;

    &--active {
      color: var(--brand-primary, #ea580c);
    }
  }

  &__text {
    margin-top: 12rpx;
    font-size: 20rpx;
    color: #60656b;

    &--active {
      color: var(--brand-primary, #ea580c);
    }
  }
}

.author-card {
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__left {
    display: flex;
    align-items: center;
    min-width: 0;
  }

  &__avatar {
    width: 72rpx;
    height: 72rpx;
    border-radius: 50%;
    flex-shrink: 0;
    background: #ececec;
  }

  &__meta {
    min-width: 0;
    margin-left: 20rpx;
  }

  &__name {
    display: block;
    font-size: 30rpx;
    font-weight: 700;
    color: #1f2328;
  }

  &__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 116rpx;
    height: 64rpx;
    padding: 0 28rpx;
    border-radius: 999rpx;
    font-size: 24rpx;
    font-weight: 700;
    color: #ffffff;
    background: #000000;

    &--active {
      color: #1f2328;
      background: #eceff2;
    }

    &--loading {
      opacity: 0.72;
    }
  }
}

.comment-card {
  border-radius: 20rpx;

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28rpx;
  }

  &__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #1f2328;
  }

  &__sorts {
    display: flex;
    align-items: center;
  }

  &__sort {
    margin-left: 20rpx;
    font-size: 20rpx;
    color: #8a9098;

    &--active {
      font-weight: 700;
      color: #1f2328;
    }
  }
}

.comment-list {
  display: flex;
  flex-direction: column;
}

.comment-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 220rpx;
  padding: 36rpx 24rpx;
  box-sizing: border-box;

  &__text {
    font-size: 26rpx;
    line-height: 38rpx;
    color: #8a9098;
  }

  &__button {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 18rpx;
    padding: 14rpx 32rpx;
    border-radius: 999rpx;
    background: var(--brand-primary, #ea580c);

    text {
      font-size: 24rpx;
      font-weight: 700;
      color: #ffffff;
    }
  }
}

.comment-item {
  display: flex;

  & + & {
    margin-top: 34rpx;
  }

  &__avatar {
    width: 64rpx;
    height: 64rpx;
    margin-right: 18rpx;
    border-radius: 50%;
    flex-shrink: 0;
    background: #ececec;
  }

  &__main {
    flex: 1;
    min-width: 0;
  }

  &__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
  }

  &__name {
    flex: 1;
    font-size: 26rpx;
    font-weight: 700;
    color: #1f2328;
  }

  &__content {
    display: block;
    margin-top: 10rpx;
    font-size: 26rpx;
    line-height: 38rpx;
    color: #2f3439;
  }

  &__meta {
    display: flex;
    align-items: center;
    margin-top: 14rpx;
  }

  &__time,
  &__reply {
    font-size: 20rpx;
    color: #8a9098;
  }

  &__reply {
    margin-left: 22rpx;
    font-weight: 700;
  }
}

.reply-list {
  margin-top: 20rpx;
  margin-left: 8rpx;
  padding-left: 18rpx;
  border-left: 4rpx solid #eceff2;
}

.reply-item {
  display: flex;

  & + & {
    margin-top: 18rpx;
  }

  &__avatar {
    width: 44rpx;
    height: 44rpx;
    margin-right: 14rpx;
    border-radius: 50%;
    flex-shrink: 0;
    background: #ececec;
  }

  &__body {
    flex: 1;
    min-width: 0;
  }

  &__top {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }

  &__name {
    font-size: 22rpx;
    font-weight: 700;
    color: #1f2328;
  }

  &__reply-to {
    margin-left: 10rpx;
    font-size: 20rpx;
    color: #8a9098;
  }

  &__content {
    display: block;
    margin-top: 6rpx;
    font-size: 22rpx;
    line-height: 34rpx;
    color: #2f3439;
  }

  &__meta {
    display: flex;
    align-items: center;
    margin-top: 8rpx;
  }

  &__time,
  &__reply {
    font-size: 18rpx;
    color: #8a9098;
  }

  &__reply {
    margin-left: 18rpx;
    font-weight: 700;
  }
}

.comment-bar {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  padding: 18rpx 24rpx 24rpx;
  background: rgba(255, 255, 255, 0.96);
  border-top: 2rpx solid #eceff2;
  box-sizing: border-box;

  &__field {
    display: flex;
    flex: 1;
    align-items: center;
    height: 76rpx;
    padding: 0 24rpx;
    border-radius: 999rpx;
    background: #f1f3f5;
    box-sizing: border-box;
  }

  &__input {
    display: block;
    flex: 1;
    min-width: 0;
    font-size: 24rpx;
    line-height: 32rpx;
    color: #1f2328;
  }

  &__input--placeholder {
    color: #8a9098;
  }

  &__placeholder {
    color: #8a9098;
  }

  &__cancel {
    margin-left: 16rpx;
    font-size: 22rpx;
    font-weight: 700;
    color: #737980;
  }

  &__submit {
    margin-left: 24rpx;
    font-size: 26rpx;
    font-weight: 700;
    color: var(--brand-primary, #ea580c);
  }

  &__submit--disabled {
    opacity: 0.55;
  }
}
</style>
