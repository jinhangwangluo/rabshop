<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="order-review-page">
    <app-safe-top
      title="订单评价"
      background="#fff"
      mp-extra="8rpx"
      show-back
      emit-back
      @back="handleBack"
    ></app-safe-top>

    <view v-if="loading && !pageReady" class="review-state review-state--loading">
      <six-petal-spiral />
      <text class="review-state__text">加载中...</text>
    </view>

    <view v-else-if="loadError" class="review-state">
      <text class="iconfont icon-notice review-state__icon"></text>
      <text class="review-state__title">评价信息加载失败</text>
      <text class="review-state__text">{{ loadError }}</text>
      <view class="review-state__button" hover-class="review-state__button--hover" @click="loadReviewPage">
        重新加载
      </view>
    </view>

    <block v-else>
      <view class="review-content">
        <view class="review-card product-card">
          <view class="product-card__top">
            <image
              v-if="productImage"
              class="product-card__image"
              :src="productImage"
              mode="aspectFill"
            ></image>
            <view v-else class="product-card__image product-card__image--empty">
              <text class="iconfont icon-pic product-card__empty-icon"></text>
            </view>
            <view class="product-card__body">
              <text class="product-card__title">{{ productTitle }}</text>
              <text v-if="productMeta" class="product-card__meta">{{ productMeta }}</text>
            </view>
          </view>

          <view class="rating-line">
            <text class="rating-line__label">整体评价</text>
            <view class="rating-line__stars">
              <text
                v-for="star in starList"
                :key="star"
                class="star-button"
                :class="{ 'star-button--active': star <= form.star, 'star-button--disabled': !canEdit }"
                data-field="star"
                :data-star="star"
                @click="handleRatingTap"
              >
                {{ star <= form.star ? '★' : '☆' }}
              </text>
            </view>
            <text class="rating-line__text">{{ ratingText }}</text>
          </view>
        </view>

        <view class="review-card input-card">
          <textarea
            class="review-textarea"
            v-model="form.content"
            :disabled="!canEdit"
            :maxlength="300"
            placeholder="写下您的真实评价，帮助更多小伙伴参考~"
            placeholder-class="review-textarea__placeholder"
          ></textarea>

          <view class="image-list">
            <view
              v-for="(image, index) in displayImages"
              :key="image.key"
              class="image-item"
            >
              <image class="image-item__image" :src="image.url" mode="aspectFill"></image>
              <view
                v-if="canEdit"
                class="image-item__remove"
                hover-class="image-item__remove--hover"
                @click="handleRemoveImage(index)"
              >
                <uni-icons type="closeempty" size="14" color="#ffffff"></uni-icons>
              </view>
            </view>

            <view
              v-if="canEdit && form.images.length < maxImageCount"
              class="image-upload"
              hover-class="image-upload--hover"
              @click="handleUploadImage"
            >
              <text class="iconfont icon-camera image-upload__icon"></text>
              <text class="image-upload__text">{{ uploading ? '上传中' : '添加图片' }}</text>
            </view>
          </view>
        </view>

        <view class="review-card detail-rating-card">
          <view
            v-for="row in ratingRows"
            :key="row.field"
            class="detail-rating-row"
          >
            <text class="detail-rating-row__label">{{ row.label }}</text>
            <view class="detail-rating-row__stars">
              <text
                v-for="star in starList"
                :key="star"
                class="star-button star-button--small"
                :class="{ 'star-button--active': star <= row.value, 'star-button--disabled': !canEdit }"
                :data-field="row.field"
                :data-star="star"
                @click="handleRatingTap"
              >
                {{ star <= row.value ? '★' : '☆' }}
              </text>
            </view>
          </view>
        </view>

        <view class="review-card anonymous-card" hover-class="anonymous-card--hover" @click="handleToggleAnonymous">
          <view class="anonymous-card__left">
            <text class="iconfont icon-notice_forbid_fill anonymous-card__icon"></text>
            <text class="anonymous-card__text">匿名评价</text>
          </view>
          <view class="review-switch" :class="{ 'review-switch--active': form.isAnonymous === 1 }">
            <view class="review-switch__thumb"></view>
          </view>
        </view>

        <view v-if="!canReview && reasonText" class="review-tip">
          <text class="review-tip__text">{{ reasonText }}</text>
        </view>
      </view>

      <view class="review-footer">
        <button class="review-footer__button" :disabled="!canSubmit" @click="handleSubmit">
          {{ submitButtonText }}
        </button>
      </view>
    </block>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderReviewPage, submitOrderReview } from '@/api/order'
import { resolveAssetUrl } from '@/utils/runtime_config'
import uploadUtil from '@/utils/util'
import { themeState } from '@/utils/theme'

const MAX_IMAGE_COUNT = 6

defineOptions({ name: 'OrderReviewPage' })

const orderId = ref(0)
const orderGoodsId = ref(0)
const loading = ref(false)
const submitting = ref(false)
const uploading = ref(false)
const pageReady = ref(false)
const loadError = ref('')
const order = ref(null)
const targetGoods = ref(null)
const review = ref(null)
const canReview = ref(false)
const hasReviewed = ref(false)
const reason = ref('')
const maxImageCount = ref(MAX_IMAGE_COUNT)
const starList = ref([1, 2, 3, 4, 5])
const form = ref({
  star: 5,
  descriptionStar: 5,
  logisticsStar: 5,
  serviceStar: 5,
  content: '',
  images: [],
  isAnonymous: 1
})

const canEdit = computed(() => {
  return canReview.value && !hasReviewed.value && !submitting.value && !uploading.value
})
const canSubmit = computed(() => {
  return canReview.value && !hasReviewed.value && !submitting.value && !uploading.value
})
const submitButtonText = computed(() => {
  if (submitting.value) {
    return '提交中...'
  }
  if (hasReviewed.value) {
    return '已评价'
  }
  if (!canReview.value) {
    return '不可评价'
  }
  return '提交评价'
})
const firstGoods = computed(() => {
  if (targetGoods.value && Object.keys(targetGoods.value).length) {
    return targetGoods.value
  }

  const goods = order.value && Array.isArray(order.value.goods) ? order.value.goods : []
  if (orderGoodsId.value > 0) {
    const matched = goods.find(item => Number(item.order_goods_id || 0) === orderGoodsId.value)
    if (matched) {
      return matched
    }
  }

  return goods.length ? goods[0] : {}
})
const productImage = computed(() => {
  return resolveAssetUrl(firstGoods.value.goods_image || '')
})
const productTitle = computed(() => {
  const name = String(firstGoods.value.goods_name || '').trim()
  return name || '订单商品'
})
const productMeta = computed(() => {
  const goodsTotal = Number(order.value && order.value.goods_total_num ? order.value.goods_total_num : 0)
  const sku = String(firstGoods.value.sku_name || '').trim()
  const parts = []
  if (sku) {
    parts.push(sku)
  }
  if (!orderGoodsId.value && goodsTotal > 1) {
    parts.push(`等 ${goodsTotal} 件商品`)
  }
  return parts.join(' · ')
})
const ratingText = computed(() => {
  const map = {
    1: '非常差',
    2: '不满意',
    3: '一般',
    4: '非常好',
    5: '非常好'
  }
  return map[form.value.star] || '非常好'
})
const ratingRows = computed(() => {
  return [
    { label: '描述相符', field: 'descriptionStar', value: form.value.descriptionStar },
    { label: '物流服务', field: 'logisticsStar', value: form.value.logisticsStar },
    { label: '服务态度', field: 'serviceStar', value: form.value.serviceStar }
  ]
})
const displayImages = computed(() => {
  return form.value.images.map((image, index) => {
    const raw = String(image || '').trim()
    return {
      key: `${index}-${raw}`,
      url: resolveAssetUrl(raw)
    }
  }).filter(image => image.url)
})
const reasonText = computed(() => {
  if (hasReviewed.value) {
    return '该商品已完成评价，可在本页查看评价内容'
  }
  return reason.value || ''
})

onLoad((options = {}) => {
  const parsedOrderId = Number(options.orderId || options.order_id || options.id || 0)
  const parsedOrderGoodsId = Number(options.orderGoodsId || options.order_goods_id || 0)
  orderId.value = Number.isNaN(parsedOrderId) ? 0 : parsedOrderId
  orderGoodsId.value = Number.isNaN(parsedOrderGoodsId) ? 0 : parsedOrderGoodsId
  loadReviewPage()
})

/**
 * 返回上一页，无历史栈时回到订单列表。
 *
 * @returns {void}
 */
function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.navigateTo({ url: '/pages/orders/order/list/index' })
    }
  })
}

/**
 * 加载订单评价页面数据。
 *
 * @returns {Promise<void>}
 */
async function loadReviewPage() {
  if (loading.value) {
    return
  }
  if (!orderId.value) {
    loadError.value = '请传入订单ID'
    return
  }

  loading.value = true
  loadError.value = ''
  try {
    const params = { order_id: orderId.value }
    if (orderGoodsId.value > 0) {
      params.order_goods_id = orderGoodsId.value
    }
    const res = await getOrderReviewPage(params)
    if (!res || res.code !== 200 || !res.data) {
      loadError.value = res && res.msg ? res.msg : '获取评价信息失败'
      return
    }

    applyReviewPayload(res.data)
  } catch (error) {
    console.error('获取评价信息失败', error)
    loadError.value = '获取评价信息失败，请稍后重试'
  } finally {
    loading.value = false
  }
}

/**
 * 应用接口返回的评价页数据。
 *
 * @param {Object} payload 接口返回数据。
 * @returns {void}
 */
function applyReviewPayload(payload = {}) {
  order.value = payload.order && typeof payload.order === 'object' ? payload.order : null
  targetGoods.value = payload.target_goods && typeof payload.target_goods === 'object' ? payload.target_goods : null
  review.value = payload.review && typeof payload.review === 'object' ? payload.review : null
  canReview.value = !!payload.can_review
  hasReviewed.value = !!payload.has_reviewed
  reason.value = String(payload.reason || '')
  const targetOrderGoodsId = Number(targetGoods.value && targetGoods.value.order_goods_id ? targetGoods.value.order_goods_id : 0)
  if (targetOrderGoodsId > 0) {
    orderGoodsId.value = targetOrderGoodsId
  }
  pageReady.value = true

  if (review.value) {
    form.value.star = normalizeStar(review.value.star)
    form.value.descriptionStar = normalizeStar(review.value.description_star)
    form.value.logisticsStar = normalizeStar(review.value.logistics_star)
    form.value.serviceStar = normalizeStar(review.value.service_star)
    form.value.content = String(review.value.content || '')
    form.value.images = normalizeImages(review.value.images)
    form.value.isAnonymous = Number(review.value.is_anonymous || 0) === 1 ? 1 : 0
  }
}

/**
 * 处理星级点击事件，小程序端通过 dataset 传递评分字段和值。
 *
 * @param {Object} event 点击事件对象。
 * @returns {void}
 */
function handleRatingTap(event) {
  const dataset = event && event.currentTarget && event.currentTarget.dataset ? event.currentTarget.dataset : {}
  handleSetRating(String(dataset.field || ''), Number(dataset.star || 0))
}

/**
 * 设置评分。
 *
 * @param {string} field 表单字段。
 * @param {number} star 星级。
 * @returns {void}
 */
function handleSetRating(field, star) {
  if (!canEdit.value || !Object.prototype.hasOwnProperty.call(form.value, field)) {
    return
  }
  form.value[field] = normalizeStar(star)
}

/**
 * 切换匿名评价。
 *
 * @returns {void}
 */
function handleToggleAnonymous() {
  if (!canEdit.value) {
    return
  }
  form.value.isAnonymous = form.value.isAnonymous === 1 ? 0 : 1
}

/**
 * 上传评价图片。
 *
 * @returns {void}
 */
function handleUploadImage() {
  if (!canEdit.value || uploading.value) {
    return
  }

  const remain = MAX_IMAGE_COUNT - form.value.images.length
  if (remain <= 0) {
    uni.showToast({ title: '最多上传6张图片', icon: 'none' })
    return
  }

  uploading.value = true
  const finishUpload = () => {
    uploading.value = false
  }

  uploadUtil.uploadImageChange(
    {
      url: 'file/upload',
      name: 'file',
      count: remain,
      is_load: true
    },
    (res) => {
      const data = res && res.data && typeof res.data === 'object' ? res.data : res
      const imageUrl = data.url || data.filePath || data.path || ''
      if (imageUrl && form.value.images.length < MAX_IMAGE_COUNT) {
        form.value.images.push(imageUrl)
      }
    },
    () => {
      uni.showToast({ title: '图片上传失败', icon: 'none' })
    }
  ).then(finishUpload).catch(finishUpload)
}

/**
 * 删除已上传图片。
 *
 * @param {number} index 图片索引。
 * @returns {void}
 */
function handleRemoveImage(index) {
  if (!canEdit.value) {
    return
  }
  uni.showModal({
    title: '删除图片',
    content: '确认删除这张图片吗？',
    confirmColor: themeState.primary_color,
    success: (res) => {
      if (res.confirm) {
        form.value.images.splice(index, 1)
      }
    }
  })
}

/**
 * 提交评价。
 *
 * @returns {Promise<void>}
 */
async function handleSubmit() {
  if (!canSubmit.value) {
    return
  }
  if (!form.value.content.trim()) {
    uni.showToast({ title: '请输入评价内容', icon: 'none' })
    return
  }

  submitting.value = true
  try {
    const res = await submitOrderReview({
      order_id: orderId.value,
      order_goods_id: orderGoodsId.value,
      star: form.value.star,
      content: form.value.content.trim(),
      images: form.value.images,
      description_star: form.value.descriptionStar,
      logistics_star: form.value.logisticsStar,
      service_star: form.value.serviceStar,
      is_anonymous: form.value.isAnonymous
    })

    if (res && res.code === 200) {
      uni.showToast({ title: '评价成功', icon: 'success' })
      setTimeout(() => {
        handleBack()
      }, 400)
      return
    }

    uni.showToast({ title: res && res.msg ? res.msg : '评价提交失败', icon: 'none' })
  } catch (error) {
    console.error('评价提交失败', error)
    uni.showToast({ title: '评价提交失败，请稍后重试', icon: 'none' })
  } finally {
    submitting.value = false
  }
}

/**
 * 规范化星级。
 *
 * @param {number|string} value 星级值。
 * @returns {number} 1-5 星级。
 */
function normalizeStar(value) {
  const star = Number(value || 5)
  if (Number.isNaN(star)) {
    return 5
  }
  return Math.min(5, Math.max(1, Math.round(star)))
}

/**
 * 规范化图片列表。
 *
 * @param {Array<string>} images 图片列表。
 * @returns {Array<string>} 可提交图片列表。
 */
function normalizeImages(images = []) {
  if (!Array.isArray(images)) {
    return []
  }

  return images
    .map(image => String(image || '').trim())
    .filter(Boolean)
    .slice(0, MAX_IMAGE_COUNT)
}
</script>


<style lang="scss">
page {
  background: #f5f5f5;
}
</style>

<style lang="scss" scoped>
.order-review-page {
  min-height: 100%;
  background: #f5f5f5;
  color: #1a1c1c;
  --review-orange: var(--brand-primary, #ea580c);
  --review-text: #1a1c1c;
  --review-muted: #5b5f64;
  --review-border: #eeeeee;
  --review-card: #ffffff;
}

.review-content {
  padding: 24rpx 30rpx 210rpx;
  box-sizing: border-box;
}

.review-card {
  background: var(--review-card);
  border: 1px solid var(--review-border);
  border-radius: 32rpx;
  box-shadow: 0 4rpx 14rpx rgba(15, 23, 42, 0.05);
  box-sizing: border-box;
}

.review-card + .review-card {
  margin-top: 22rpx;
}

.product-card {
  padding: 32rpx 26rpx 30rpx;
}

.product-card__top {
  display: flex;
  align-items: flex-start;
}

.product-card__image {
  flex-shrink: 0;
  width: 128rpx;
  height: 128rpx;
  border-radius: 16rpx;
  background: #f3f3f3;
}

.product-card__image--empty {
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-card__empty-icon {
  font-size: 44rpx;
  color: #c3c7cc;
}

.product-card__body {
  flex: 1;
  min-width: 0;
  margin-left: 26rpx;
}

.product-card__title {
  display: block;
  overflow: hidden;
  max-width: 100%;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 800;
  color: #000000;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-card__meta {
  display: block;
  overflow: hidden;
  margin-top: 12rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--review-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rating-line {
  display: flex;
  align-items: center;
  margin-top: 42rpx;
}

.rating-line__label {
  flex-shrink: 0;
  font-size: 28rpx;
  line-height: 40rpx;
  color: #5b5f64;
}

.rating-line__stars {
  display: flex;
  align-items: center;
  margin-left: 22rpx;
}

.rating-line__text {
  flex-shrink: 0;
  margin-left: 24rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 800;
  color: var(--review-orange);
}

.star-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 50rpx;
  height: 50rpx;
  margin-right: 10rpx;
  font-size: 44rpx;
  line-height: 50rpx;
  color: #cfc4c5;
}

.star-button--small {
  width: 48rpx;
  height: 48rpx;
  margin-right: 14rpx;
  font-size: 40rpx;
  line-height: 48rpx;
}

.star-button--active {
  color: var(--review-orange);
}

.star-button--disabled {
  opacity: 0.85;
}

.input-card {
  padding: 28rpx 26rpx 30rpx;
}

.review-textarea {
  width: 100%;
  height: 250rpx;
  padding: 0;
  border: 0;
  background: transparent;
  box-sizing: border-box;
  font-size: 28rpx;
  line-height: 40rpx;
  color: var(--review-text);
}

.review-textarea__placeholder {
  color: #848484;
}

.image-list {
  display: flex;
  flex-wrap: wrap;
  margin-top: 28rpx;
}

.image-upload,
.image-item {
  width: 148rpx;
  height: 148rpx;
  margin-right: 18rpx;
  margin-bottom: 18rpx;
  border-radius: 16rpx;
  box-sizing: border-box;
}

.image-upload {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px dashed #cfc4c5;
  background: #f3f3f3;
}

.image-upload--hover {
  opacity: 0.78;
}

.image-upload__icon {
  font-size: 46rpx;
  line-height: 1;
  color: #5b5f64;
}

.image-upload__text {
  margin-top: 12rpx;
  font-size: 22rpx;
  line-height: 30rpx;
  color: #5b5f64;
}

.image-item {
  position: relative;
  overflow: hidden;
  background: #eeeeee;
}

.image-item__image {
  width: 148rpx;
  height: 148rpx;
  display: block;
}

.image-item__remove {
  position: absolute;
  top: 8rpx;
  right: 8rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34rpx;
  height: 34rpx;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.56);
}

.image-item__remove--hover {
  opacity: 0.72;
}

.detail-rating-card {
  padding: 22rpx 30rpx;
}

.detail-rating-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 78rpx;
}

.detail-rating-row__label {
  flex-shrink: 0;
  font-size: 30rpx;
  line-height: 42rpx;
  color: #000000;
}

.detail-rating-row__stars {
  display: flex;
  align-items: center;
}

.anonymous-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 102rpx;
  padding: 0 30rpx;
}

.anonymous-card--hover {
  opacity: 0.78;
}

.anonymous-card__left {
  display: flex;
  align-items: center;
}

.anonymous-card__icon {
  font-size: 42rpx;
  color: #5b5f64;
}

.anonymous-card__text {
  margin-left: 18rpx;
  font-size: 30rpx;
  line-height: 42rpx;
  color: #000000;
}

.review-switch {
  display: flex;
  align-items: center;
  width: 78rpx;
  height: 42rpx;
  padding: 4rpx;
  border-radius: 999rpx;
  background: #cfc4c5;
  box-sizing: border-box;
}

.review-switch__thumb {
  width: 34rpx;
  height: 34rpx;
  border-radius: 50%;
  background: #ffffff;
}

.review-switch--active {
  justify-content: flex-end;
  background: var(--review-orange);
}

.review-tip {
  margin-top: 20rpx;
  padding: 22rpx 28rpx;
  border-radius: 20rpx;
  background: #f3f3f3;
}

.review-tip__text {
  font-size: 24rpx;
  line-height: 34rpx;
  color: #5b5f64;
}

.review-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  padding: 24rpx 30rpx 28rpx;
  background: rgba(255, 255, 255, 0.96);
  box-sizing: border-box;
}

.review-footer__button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 94rpx;
  border-radius: 999rpx;
  background: var(--review-orange);
  box-shadow: 0 12rpx 24rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
  color: #ffffff;
  font-size: 34rpx;
  line-height: 44rpx;
  font-weight: 800;
}

.review-footer__button::after {
  border: 0;
}

.review-footer__button[disabled] {
  background: #c3c7cc;
  box-shadow: none;
  color: #ffffff;
}

.review-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 180rpx 48rpx 0;
  box-sizing: border-box;
}

.review-state--loading {
  padding-top: 220rpx;
}

.review-state__icon {
  font-size: 72rpx;
  color: #c3c7cc;
}

.review-state__title {
  margin-top: 28rpx;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
  color: #000000;
}

.review-state__text {
  margin-top: 16rpx;
  font-size: 26rpx;
  line-height: 38rpx;
  color: #5b5f64;
  text-align: center;
}

.review-state__button {
  margin-top: 34rpx;
  padding: 18rpx 42rpx;
  border-radius: 999rpx;
  background: var(--review-orange);
  color: #ffffff;
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 700;
}

.review-state__button--hover {
  opacity: 0.82;
}

/* #ifdef H5 */
.review-content {
  padding-bottom: calc(210rpx + var(--window-bottom, 0px));
}

.review-footer {
  bottom: var(--window-bottom, 0px);
  padding-bottom: calc(28rpx + var(--window-bottom, 0px));
}
/* #endif */

/* #ifdef MP-WEIXIN */
.review-content {
  padding-bottom: calc(210rpx + env(safe-area-inset-bottom));
}

.review-footer {
  padding-bottom: calc(28rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
