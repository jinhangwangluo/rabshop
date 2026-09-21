<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="content-detail-page">
    <view class="detail-back-button" :style="detailBackButtonStyle" @click="handleBack">
      <uni-icons type="left" size="22" color="#ffffff"></uni-icons>
    </view>

    <view v-if="loading" class="detail-state">
      <text class="detail-state__text">加载中...</text>
    </view>

    <view v-else-if="errorMessage" class="detail-state">
      <text class="detail-state__text">{{ errorMessage }}</text>
      <view class="detail-state__button" @click="fetchDetail">
        <text>重新加载</text>
      </view>
    </view>

    <scroll-view v-else scroll-y class="detail-scroll" :show-scrollbar="false">
      <view class="detail-scroll__body">
        <image class="hero-cover" :src="detail.cover" mode="aspectFill"></image>

        <view class="article-card" :class="{ 'article-card--image': isImageContent }">
          <text class="article-card__title">{{ detail.title }}</text>

          <view class="author-row">
            <view class="author-row__left">
              <image class="author-row__avatar" :src="authorAvatar" mode="aspectFill"></image>
              <view class="author-row__meta">
                <text class="author-row__name">{{ authorName }}</text>
                <text class="author-row__time">{{ detail.create_time }}</text>
              </view>
            </view>
            <view
              class="follow-button"
              :class="{ 'follow-button--active': isFollowed, 'follow-button--loading': followLoading }"
              @click="handleToggleFollow"
            >
              <text>{{ followButtonText }}</text>
            </view>
          </view>

          <!-- 图片类型：预览图片列表 -->
          <view v-if="isImageContent" class="image-stream-section">
            <view
              v-for="(img, idx) in displayTrialImages"
              :key="'img-preview-' + idx"
              class="image-stream-item"
              @click="handleOpenImageViewer(idx)"
            >
              <image
                class="image-stream-item__image"
                :src="img"
                mode="widthFix"
              ></image>
            </view>
          </view>

          <!-- 图文类型：富文本 -->
          <view v-else class="detail-content">
            <rich-text v-if="!isUnlocked && detail.trial_content" :nodes="detail.trial_content"></rich-text>
            <rich-text v-else-if="isUnlocked && detail.content" :nodes="detail.content"></rich-text>
          </view>

          <view v-if="!isUnlocked" class="paywall-section">
            <view class="paywall-section__fade"></view>
            <view class="paywall-card">
              <view class="paywall-card__lock">
                <uni-icons type="locked-filled" size="20" color="#1a1c1c"></uni-icons>
              </view>
              <text class="paywall-card__title">付费解锁全文内容</text>
              <text class="paywall-card__desc">{{ detail.description }}</text>

              <view class="plan-card plan-card--light">
                <view class="plan-card__info">
                  <text class="plan-card__label">单篇解锁</text>
                  <text class="plan-card__price plan-card__price--primary">¥ {{ singlePriceText }}</text>
                </view>
                <view class="plan-card__button plan-card__button--primary" @click="handleUnlock">
                  <text>{{ buyLoading ? '处理中...' : '立即解锁' }}</text>
                </view>
              </view>

              <view v-if="memberPriceAvailable" class="plan-card plan-card--dark">
                <view class="plan-card__info">
                  <text class="plan-card__label plan-card__label--dark">会员价</text>
                  <text class="plan-card__price plan-card__price--dark">¥ {{ memberPriceText }}</text>
                </view>
                <view class="plan-card__button plan-card__button--light" @click="handleMemberPrice">
                  <text>{{ memberButtonText }}</text>
                </view>
              </view>

              <view class="paywall-card__foot">
                <uni-icons type="checkmarkempty" size="12" color="#b3b3b8"></uni-icons>
                <text>安全支付 · 永久回看</text>
              </view>
            </view>
          </view>

          <view v-else class="unlocked-section">
            <!-- 图片类型：解锁后的图片列表 -->
            <view v-if="isImageContent" class="image-stream-section image-stream-section--unlocked">
              <view
                v-for="(img, idx) in displayFullImages"
                :key="'img-full-' + idx"
                class="image-stream-item"
                @click="handleOpenImageViewer(displayTrialImages.length + idx)"
              >
                <image
                  class="image-stream-item__image"
                  :src="img"
                  mode="widthFix"
                ></image>
              </view>
            </view>

            <!-- 图文类型解锁后已在上方 rich-text 中展示 -->
          </view>
        </view>

        <view class="comments-card">
          <text class="comments-card__title">评论 ({{ commentTotal }})</text>

          <view v-if="commentLoading" class="detail-state">
            <text class="detail-state__text">评论加载中...</text>
          </view>

          <view v-else-if="commentError" class="comment-state">
            <text class="comment-state__text">{{ commentError }}</text>
            <view class="comment-state__button" @click="fetchComments">
              <text>重新加载</text>
            </view>
          </view>

          <view v-else-if="!comments.length" class="comment-state">
            <text class="comment-state__text">暂无评论</text>
          </view>

          <view v-else class="comments-list">
            <view v-for="comment in comments" :key="comment.id" class="comment-item">
              <view class="comment-item__main">
                <image
                  class="comment-item__avatar"
                  :src="(comment.user && comment.user.avatar) || DEFAULT_AVATAR"
                  mode="aspectFill"
                ></image>
                <view class="comment-item__content">
                  <text class="comment-item__name">{{ (comment.user && comment.user.nickname) || '匿名' }}</text>
                  <text class="comment-item__text">{{ comment.content }}</text>
                  <view class="comment-item__meta">
                    <text class="comment-item__meta-text">{{ comment.create_time }}</text>
                    <text class="comment-item__meta-link" @click="handleReply(comment, comment)">回复</text>
                  </view>

                  <view v-if="comment.children && comment.children.length" class="comment-reply-list">
                    <view v-for="reply in comment.children" :key="reply.id" class="comment-reply-item">
                      <image
                        class="comment-reply-item__avatar"
                        :src="(reply.user && reply.user.avatar) || DEFAULT_AVATAR"
                        mode="aspectFill"
                      ></image>
                      <view class="comment-reply-item__content">
                        <text class="comment-reply-item__name">{{ (reply.user && reply.user.nickname) || '匿名' }}</text>
                        <text class="comment-reply-item__text">
                          <text v-if="reply.reply_to_nickname" class="comment-reply-item__reply-to">@{{ reply.reply_to_nickname }} </text>
                          {{ reply.content }}
                        </text>
                        <view class="comment-item__meta">
                          <text class="comment-item__meta-text">{{ reply.create_time }}</text>
                          <text class="comment-item__meta-link" @click="handleReply(reply, comment)">回复</text>
                        </view>
                      </view>
                    </view>
                  </view>
                </view>
              </view>
            </view>
          </view>

          <view class="comment-input">
            <view class="comment-input__field">
              <input
                v-model="commentText"
                class="comment-input__control"
                :placeholder="commentInputPlaceholder"
                placeholder-class="comment-input__placeholder"
                confirm-type="send"
                @confirm="handlePublishComment"
              />
            </view>
            <view class="comment-input__actions">
              <text v-if="replyTarget" class="comment-input__action" @click="clearReplyTarget">取消</text>
              <text
                class="comment-input__submit"
                :class="{ 'comment-input__submit--disabled': commentSubmitting }"
                @click="handlePublishComment"
              >{{ commentSubmitting ? '发送中' : '发送' }}</text>
            </view>
          </view>
        </view>

        <view class="content-guess">
          <guess-you-like :show-more="false" />
        </view>
      </view>
    </scroll-view>

    <view v-if="showImageViewer" class="image-viewer">
      <view class="image-viewer__mask"></view>

      <view class="image-viewer__toolbar">
        <view class="image-viewer__toolbar-spacer"></view>
        <view class="image-viewer__counter">{{ imageViewerIndex + 1 }} / {{ viewerImages.length }}</view>
        <view class="image-viewer__close" @click="handleCloseImageViewer">
          <uni-icons type="closeempty" size="26" color="#ffffff"></uni-icons>
        </view>
      </view>

      <swiper
        class="image-viewer__swiper"
        :current="imageViewerIndex"
        circular
        @change="handleViewerChange"
      >
        <swiper-item v-for="item in viewerImages" :key="item.id" class="image-viewer__item">
          <!-- #ifdef H5 -->
          <view
            class="image-viewer__slide"
            @touchstart="handleViewerPointerStart"
            @touchend="handleViewerPointerEnd"
            @mousedown="handleViewerPointerStart"
            @mouseup="handleViewerPointerEnd"
          >
            <image
              v-if="item.shouldLoad"
              class="image-viewer__main-image"
              :src="item.src"
              mode="aspectFit"
              lazy-load
              :data-index="item.index"
              @load="handleViewerImageLoad"
              @error="handleViewerImageError"
            ></image>
            <view v-else class="image-viewer__placeholder"></view>
            <view v-if="item.loading" class="image-viewer__loading">
              <text>图片加载中...</text>
            </view>
            <view v-if="item.error" class="image-viewer__loading">
              <text>图片加载失败</text>
            </view>
          </view>
          <!-- #endif -->
          <!-- #ifndef H5 -->
          <view
            class="image-viewer__slide"
            @touchstart="handleViewerPointerStart"
            @touchend="handleViewerPointerEnd"
          >
            <image
              v-if="item.shouldLoad"
              class="image-viewer__main-image"
              :src="item.src"
              mode="aspectFit"
              lazy-load
              :data-index="item.index"
              @load="handleViewerImageLoad"
              @error="handleViewerImageError"
            ></image>
            <view v-else class="image-viewer__placeholder"></view>
            <view v-if="item.loading" class="image-viewer__loading">
              <text>图片加载中...</text>
            </view>
            <view v-if="item.error" class="image-viewer__loading">
              <text>图片加载失败</text>
            </view>
          </view>
          <!-- #endif -->
        </swiper-item>
      </swiper>

      <view class="image-viewer__footer">
        <view class="image-viewer__progress">
          <view class="image-viewer__progress-track"></view>
          <view class="image-viewer__progress-value" :style="viewerProgressStyle"></view>
        </view>

        <view v-if="viewerImages.length <= 8 && viewerImages.length > 1" class="image-viewer__dots">
          <text
            v-for="item in viewerImages"
            :key="'dot-' + item.id"
            class="image-viewer__dot"
            :class="{ 'is-active': imageViewerIndex === item.index }"
          ></text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { buyNow } from '@/api/cart'
import { getContentDetail } from '@/api/content'
import { createComment, getCommentList, replyComment } from '@/api/comment'
import { getMerchantFollowStatus, toggleMerchantFollow } from '@/api/merchant'
import { getUserInfo } from '@/utils/auth'
import { resolveAssetUrl } from '@/utils/runtime_config'
import { themeState } from '@/utils/theme'

const DEFAULT_AVATAR = '/static/images/avatar_default.jpg'
const CONTENT_ORDER_TYPE = 1

function normalizeImageUrl(image = '') {
  if (typeof image === 'string') {
    return resolveAssetUrl(image)
  }

  if (image && typeof image === 'object') {
    return resolveAssetUrl(image.src)
  }

  return ''
}

function normalizeImageList(images = []) {
  if (!Array.isArray(images)) {
    return []
  }

  return images
    .map((image) => normalizeImageUrl(image))
    .filter(Boolean)
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

defineOptions({ name: 'ContentDetailPage' })

const detail = ref({})
const loading = ref(true)
const errorMessage = ref('')
const articleId = ref(0)
const isFollowed = ref(false)
const showImageViewer = ref(false)
const imageViewerIndex = ref(0)
const viewerImages = ref([])
const viewerPointerStartX = ref(0)
const safeTopInset = ref(0)
const comments = ref([])
const commentTotal = ref(0)
const commentText = ref('')
const replyTarget = ref(null)
const commentLoading = ref(false)
const commentError = ref('')
const commentSubmitting = ref(false)
const buyLoading = ref(false)
const followLoading = ref(false)

const commentInputPlaceholder = computed(() => {
  if (!replyTarget.value) {
    return '善语结善缘，恶言伤人心'
  }
  return `回复 ${(replyTarget.value.user && replyTarget.value.user.nickname) || '用户'}`
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

const detailBackButtonStyle = computed(() => {
  return `top: ${safeTopInset.value}px;`
})

const isImageContent = computed(() => {
  return Number(detail.value.content_type) === 4
})

const isUnlocked = computed(() => {
  return String(detail.value.access_state || '') === 'learning'
})

const isMemberRequired = computed(() => {
  return String(detail.value.access_state || '') === 'member_required'
})

const isCurrentMember = computed(() => {
  return Boolean(detail.value.is_member)
})

const singlePriceText = computed(() => {
  return formatMoney(detail.value.price)
})

const memberPriceAvailable = computed(() => {
  const price = Number(detail.value.price || 0)
  const memberPrice = Number(detail.value.member_price || 0)
  const available = detail.value.member_price_available === true || Number(detail.value.member_price_available) === 1
  return available
    && Number.isFinite(price)
    && Number.isFinite(memberPrice)
    && memberPrice >= 0
    && memberPrice < price
})

const memberPriceText = computed(() => {
  return formatMoney(detail.value.member_price)
})

const memberButtonText = computed(() => {
  if (isCurrentMember.value) {
    return '会员价购买'
  }

  if (!detail.value.can_purchase && !detail.value.member_enabled) {
    return '暂未开放'
  }

  return '成为会员'
})

const displayTrialImages = computed(() => {
  return normalizeImageList(detail.value.trial_images)
})

const displayFullImages = computed(() => {
  return normalizeImageList(detail.value.images)
})

const visibleImageItems = computed(() => {
  const items = []
  const trialImages = displayTrialImages.value
  const fullImages = displayFullImages.value
  const images = isUnlocked.value ? trialImages.concat(fullImages) : trialImages
  images.forEach((src, idx) => {
    items.push({ id: idx, src })
  })
  return items
})

const viewerProgressStyle = computed(() => {
  const total = viewerImages.value.length || 1
  const percentage = ((imageViewerIndex.value + 1) / total) * 100
  return `width: ${percentage}%;`
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
  if (isFollowed.value) {
    return '已关注'
  }
  return isPlatformContent.value ? '关注平台' : '关注店铺'
})

onLoad((options = {}) => {
  const systemInfo = typeof uni.getSystemInfoSync === 'function' ? uni.getSystemInfoSync() : {}
  safeTopInset.value = Number(systemInfo.statusBarHeight || 0)
  articleId.value = Number(options.id || 1)
  fetchDetail()
})

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
    detail.value = res.data
    isFollowed.value = false
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

async function syncFollowStatus() {
  try {
    const res = await getMerchantFollowStatus({ merchant_id: followMerchantId.value }, {
      showError: false,
      loginRedirect: false
    })
    if (res && res.code === 200 && res.data) {
      isFollowed.value = Boolean(res.data.is_followed)
    }
  } catch (e) {
    isFollowed.value = false
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
        redirect: `/pages/contents/content/detail/index?id=${articleId.value}`
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

    isFollowed.value = Boolean(res.data.is_followed)
    uni.showToast({
      title: isFollowed.value
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

function formatMoney(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) {
    return '0'
  }
  return amount % 1 === 0 ? String(Math.trunc(amount)) : amount.toFixed(2)
}

function buildContentCartPayload() {
  return {
    goods_id: Number(detail.value.id || articleId.value || 0),
    cart_num: 1,
    product_attr_unique: '',
    order_type: CONTENT_ORDER_TYPE
  }
}

async function handleUnlock() {
  if (buyLoading.value) {
    return
  }

  if (isMemberRequired.value && !isCurrentMember.value) {
    promptOpenMember()
    return
  }

  if (!detail.value.can_buy) {
    uni.showToast({ title: '当前内容暂不可购买', icon: 'none' })
    return
  }

  buyLoading.value = true
  try {
    const res = await buyNow(buildContentCartPayload())

    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({ title: (res && res.msg) || '购买入口打开失败', icon: 'none' })
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
    console.error('[content-detail] handleUnlock failed', error)
    uni.showToast({ title: '购买入口打开失败', icon: 'none' })
  } finally {
    buyLoading.value = false
  }
}

function handleMemberPrice() {
  if (!memberPriceAvailable.value) {
    uni.showToast({ title: '暂无会员优惠', icon: 'none' })
    return
  }

  if (isCurrentMember.value) {
    handleUnlock()
    return
  }

  if (!detail.value.can_purchase && !detail.value.member_enabled) {
    uni.showToast({ title: '会员功能暂未开放', icon: 'none' })
    return
  }

  if (!isCurrentMember.value) {
    promptOpenMember()
    return
  }
}

function promptOpenMember() {
  uni.showModal({
    title: '成为会员',
    content: `开通会员后可享会员价 ¥${memberPriceText.value}，是否前往会员中心？`,
    confirmText: '去开通',
    cancelText: '再看看',
    confirmColor: themeState.primary_color,
    success: ({ confirm }) => {
      if (!confirm) {
        return
      }
      uni.navigateTo({
        url: '/pages/users/member/index'
      })
    }
  })
}

function handleOpenImageViewer(index = 0) {
  const sourceImages = visibleImageItems.value.map((item) => item.src).filter(Boolean)
  const images = sourceImages.map((src, idx) => ({
    id: `viewer-${idx}`,
    index: idx,
    src,
    shouldLoad: false,
    loading: false,
    error: false
  }))
  if (!images.length) {
    return
  }

  const safeIndex = Math.max(Math.min(Number(index || 0), images.length - 1), 0)
  viewerImages.value = images
  imageViewerIndex.value = safeIndex
  showImageViewer.value = true
  updateViewerLazyImages(safeIndex)
}

function handleCloseImageViewer() {
  showImageViewer.value = false
  viewerImages.value = []
  imageViewerIndex.value = 0
  viewerPointerStartX.value = 0
}

function handleViewerChange(event = {}) {
  const current = Number(event.detail && event.detail.current)
  const nextIndex = Number.isNaN(current) ? 0 : current
  imageViewerIndex.value = nextIndex
  updateViewerLazyImages(nextIndex)
}

function getViewerPointerClientX(event = {}) {
  const touches = event.changedTouches || event.touches
  if (touches && touches.length) {
    return Number(touches[0].clientX || 0)
  }
  return Number(event.clientX || 0)
}

function handleViewerPointerStart(event = {}) {
  viewerPointerStartX.value = getViewerPointerClientX(event)
}

function handleViewerPointerEnd(event = {}) {
  const endX = getViewerPointerClientX(event)
  const deltaX = endX - viewerPointerStartX.value
  if (Math.abs(deltaX) < 40) {
    return
  }
  handleViewerStep(deltaX < 0 ? 1 : -1)
}

function handleViewerStep(step = 1) {
  const total = viewerImages.value.length
  if (total <= 1) {
    return
  }

  const next = (imageViewerIndex.value + Number(step || 0) + total) % total
  imageViewerIndex.value = next
  updateViewerLazyImages(next)
}

function updateViewerLazyImages(activeIndex = 0) {
  const total = viewerImages.value.length
  if (!total) {
    return
  }

  const prevIndex = (activeIndex - 1 + total) % total
  const nextIndex = (activeIndex + 1) % total
  viewerImages.value = viewerImages.value.map((item, idx) => {
    const shouldLoad = idx === activeIndex || idx === prevIndex || idx === nextIndex
    const loading = shouldLoad && !item.error && !item.loaded
    return {
      ...item,
      shouldLoad,
      loading
    }
  })
}

function getViewerEventIndex(event = {}) {
  return Number(event.currentTarget && event.currentTarget.dataset && event.currentTarget.dataset.index)
}

function handleViewerImageLoad(event = {}) {
  const targetIndex = getViewerEventIndex(event)
  viewerImages.value = viewerImages.value.map((item) => {
    if (item.index !== targetIndex) {
      return item
    }
    return {
      ...item,
      loaded: true,
      loading: false,
      error: false
    }
  })
}

function handleViewerImageError(event = {}) {
  const targetIndex = getViewerEventIndex(event)
  viewerImages.value = viewerImages.value.map((item) => {
    if (item.index !== targetIndex) {
      return item
    }
    return {
      ...item,
      loading: false,
      error: true
    }
  })
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
  background: #f9f9f9;
}
</style>

<style scoped lang="scss">
.content-detail-page {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  background: #f9f9f9;
  position: relative;
}

.detail-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
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

.detail-back-button {
  position: absolute;
  left: 28rpx;
  z-index: 20;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
  box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 10rpx;
}

.detail-scroll {
  flex: 1;
  min-height: 0;

  &__body {
    padding-bottom: 48rpx;
  }
}

.hero-cover {
  display: block;
  width: 100%;
  height: 500rpx;
  background: #dadada;
}

.article-card {
  padding: 32rpx;

  &--image {
    padding-bottom: 40rpx;
  }

  &__title {
    display: block;
    font-size: 36rpx;
    line-height: 50rpx;
    font-weight: 700;
    color: #1a1c1c;
  }
}

.author-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 28rpx;
  padding-bottom: 28rpx;
  border-bottom: 1rpx solid #f0f0f2;

  &__left {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
  }

  &__avatar {
    width: 56rpx;
    height: 56rpx;
    border-radius: 50%;
    flex-shrink: 0;
    background: #d8d8dc;
  }

  &__meta {
    flex: 1;
    min-width: 0;
    margin-left: 18rpx;
  }

  &__name,
  &__time {
    display: block;
  }

  &__name {
    font-size: 24rpx;
    line-height: 34rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__time {
    margin-top: 4rpx;
    font-size: 20rpx;
    line-height: 30rpx;
    color: #8b8b92;
  }
}

.follow-button {
  min-width: 120rpx;
  height: 56rpx;
  padding: 0 20rpx;
  border-radius: 999rpx;
  background: #000000;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  text {
    font-size: 22rpx;
    line-height: 1;
    font-weight: 700;
    color: #ffffff;
  }

  &--active {
    background: #f3f3f4;

    text {
      color: #5b5f64;
    }
  }

  &--loading {
    opacity: 0.72;
  }
}

.detail-content {
  padding-top: 28rpx;
}

.image-stream-section {
  margin-top: 28rpx;
  overflow: hidden;

  &--unlocked {
    margin-top: 24rpx;
  }
}

.image-stream-item {
  display: block;

  &__image {
    display: block;
    width: 100%;
    background: #e2e2e2;
  }
}

.paywall-section {
  position: relative;
  margin-top: 24rpx;
  padding: 68rpx 0 10rpx;

  &__fade {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 120rpx;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, #ffffff 100%);
  }
}

.paywall-card {
  position: relative;
  z-index: 1;
  padding: 34rpx 24rpx 22rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.06);

  &__lock {
    width: 72rpx;
    height: 72rpx;
    margin: 0 auto;
    border-radius: 50%;
    background: #f3f3f4;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  &__title,
  &__desc {
    display: block;
    text-align: center;
  }

  &__title {
    margin-top: 18rpx;
    font-size: 34rpx;
    line-height: 46rpx;
    font-weight: 700;
    color: #000000;
  }

  &__desc {
    margin-top: 12rpx;
    font-size: 22rpx;
    line-height: 34rpx;
    color: #8b8b92;
  }

  &__foot {
    margin-top: 18rpx;
    display: flex;
    align-items: center;
    justify-content: center;

    text {
      margin-left: 8rpx;
      font-size: 20rpx;
      line-height: 28rpx;
      color: #b3b3b8;
    }
  }
}

.plan-card {
  margin-top: 20rpx;
  padding: 22rpx 20rpx;
  border-radius: 18rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;

  &--light {
    background: #fafafa;
    border: 1rpx solid #f0f0f2;
  }

  &--dark {
    background: #17181b;
  }

  &__info {
    flex: 1;
    min-width: 0;
  }

  &__label,
  &__price {
    display: block;
  }

  &__label {
    font-size: 20rpx;
    line-height: 28rpx;
    color: #b3b3b8;
  }

  &__label--dark {
    color: #787b84;
  }

  &__price {
    margin-top: 8rpx;
    font-size: 36rpx;
    line-height: 1;
    font-weight: 700;
  }

  &__price--primary {
    color: var(--brand-primary, #ea580c);
  }

  &__price--dark {
    color: #ffffff;
  }

  &__button {
    min-width: 150rpx;
    height: 64rpx;
    padding: 0 24rpx;
    margin-left: 20rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    text {
      font-size: 24rpx;
      line-height: 1;
      font-weight: 700;
    }
  }

  &__button--primary {
    background: var(--brand-primary, #ea580c);

    text {
      color: #ffffff;
    }
  }

  &__button--light {
    background: #ffffff;

    text {
      color: #17181b;
    }
  }
}

.unlocked-section {
  padding-top: 32rpx;
}

.comments-card {
  margin: 0 24rpx;
  padding: 28rpx 24rpx 24rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.04);

  &__title {
    display: block;
    font-size: 32rpx;
    line-height: 44rpx;
    font-weight: 700;
    color: #1a1c1c;
  }
}

.comments-list {
  margin-top: 24rpx;
}

.comment-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 220rpx;
  padding: 40rpx 24rpx;
  box-sizing: border-box;
}

.comment-state__text {
  font-size: 26rpx;
  line-height: 38rpx;
  color: #8b8b92;
}

.comment-state__button {
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

.comment-item + .comment-item {
  margin-top: 28rpx;
}

.comment-item__main {
  display: flex;
  align-items: flex-start;
}

.comment-item__avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  flex-shrink: 0;
  background: #e8e8e8;
}

.comment-item__content {
  flex: 1;
  min-width: 0;
  margin-left: 20rpx;
}

.comment-item__name,
.comment-item__text,
.comment-reply-item__name,
.comment-reply-item__text {
  display: block;
}

.comment-item__name,
.comment-reply-item__name {
  font-size: 24rpx;
  line-height: 34rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.comment-item__text,
.comment-reply-item__text {
  margin-top: 8rpx;
  font-size: 26rpx;
  line-height: 40rpx;
  color: #2f3131;
}

.comment-item__meta {
  display: flex;
  align-items: center;
  margin-top: 10rpx;
}

.comment-item__meta-text,
.comment-item__meta-link,
.comment-reply-list__text,
.comment-reply-item__reply-to {
  font-size: 20rpx;
  line-height: 28rpx;
}

.comment-item__meta-text {
  color: #a0a0a6;
}

.comment-item__meta-link {
  margin-left: 24rpx;
  font-weight: 700;
  color: #5b5f64;
}

.comment-reply-list {
  margin-top: 18rpx;
}

.comment-reply-item {
  display: flex;
  align-items: flex-start;
}

.comment-reply-item + .comment-reply-item {
  margin-top: 18rpx;
}

.comment-reply-item__avatar {
  width: 48rpx;
  height: 48rpx;
  border-radius: 50%;
  flex-shrink: 0;
  background: #eeeeee;
}

.comment-reply-item__content {
  flex: 1;
  min-width: 0;
  margin-left: 14rpx;
}

.comment-reply-item__reply-to {
  color: #5b5f64;
  font-weight: 700;
}

.comment-input {
  display: flex;
  align-items: center;
  margin-top: 28rpx;
  padding-top: 22rpx;
  border-top: 1rpx solid #f5f5f6;

  &__field {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    height: 72rpx;
    padding: 0 24rpx;
    border-radius: 999rpx;
    background: #f3f3f4;
  }

  &__control {
    flex: 1;
    min-width: 0;
    height: 72rpx;
    font-size: 24rpx;
    color: #1a1c1c;
  }

  &__placeholder {
    font-size: 24rpx;
    color: #b3b3b8;
  }

  &__actions {
    display: flex;
    align-items: center;
    margin-left: 20rpx;
  }

  &__action {
    margin-left: 16rpx;
    font-size: 22rpx;
    line-height: 1;
    font-weight: 700;
    text-align: center;
    color: #5b5f64;
  }

  &__action:first-child {
    margin-left: 0;
  }

  &__submit {
    margin-left: 18rpx;
    font-size: 24rpx;
    line-height: 1;
    font-weight: 700;
    color: var(--brand-primary, #ea580c);
  }

  &__submit--disabled {
    opacity: 0.55;
  }
}

.content-guess {
  margin-top: 28rpx;
  padding: 0 32rpx;
}

.image-viewer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 120;
  overflow: hidden;
  background: #000000;
}

.image-viewer__mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: rgba(0, 0, 0, 0.95);
}

.image-viewer__toolbar {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 32rpx 0;
}

/* #ifdef MP-WEIXIN */
.image-viewer__toolbar {
  padding-top: calc(var(--status-bar-height, 0px) + 24rpx);
}
/* #endif */

.image-viewer__toolbar-spacer,
.image-viewer__close {
  width: 72rpx;
  height: 72rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.image-viewer__counter {
  padding: 12rpx 28rpx;
  border-radius: 999rpx;
  background: rgba(0, 0, 0, 0.45);
  font-size: 24rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}

.image-viewer__swiper {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1;
  width: 100%;
  height: 100%;
}

.image-viewer__item {
  height: 100%;
}

.image-viewer__slide {
  position: relative;
  width: 100%;
  height: 100%;
  padding: 120rpx 0 140rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.image-viewer__main-image {
  width: 100%;
  height: 100%;
}

.image-viewer__placeholder {
  width: 100%;
  height: 100%;
}

.image-viewer__loading {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  z-index: 2;
  margin-top: -20rpx;
  text-align: center;

  text {
    font-size: 26rpx;
    line-height: 40rpx;
    color: rgba(255, 255, 255, 0.72);
  }
}

.image-viewer__footer {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 2;
  padding: 120rpx 32rpx 32rpx;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, 0.86) 100%);
}

/* #ifdef MP-WEIXIN */
.image-viewer__footer {
  padding-bottom: calc(env(safe-area-inset-bottom) + 24rpx);
}
/* #endif */

/* #ifdef H5 */
.image-viewer__footer {
  padding-bottom: calc(var(--window-bottom, 0px) + 24rpx);
}
/* #endif */

.image-viewer__progress {
  position: relative;
  height: 4rpx;
  margin-bottom: 34rpx;
}

.image-viewer__progress-track,
.image-viewer__progress-value {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  border-radius: 999rpx;
}

.image-viewer__progress-track {
  width: 100%;
  background: rgba(255, 255, 255, 0.12);
}

.image-viewer__progress-value {
  background: rgba(255, 255, 255, 0.62);
}

.image-viewer__dots {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 28rpx;
}

.image-viewer__dot {
  width: 12rpx;
  height: 12rpx;
  margin: 0 8rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.26);
}

.image-viewer__dot.is-active {
  background: #ffffff;
}
</style>
