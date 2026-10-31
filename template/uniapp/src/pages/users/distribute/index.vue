<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="distribute-page">
    <image class="distribute-page__hero-bg" src="../static/distribute-hero-bg.jpg" mode="aspectFill"></image>
    <view class="distribute-page__hero-mask"></view>
    <view class="distribute-page__safe-top">
      <app-safe-top
        title="分销推广"
        background="transparent"
        :show-back="true"
        :emit-back="true"
        @back="handleBack"
      >
      </app-safe-top>
    </view>

    <view class="distribute-page__content">
      <view class="hero-card">
        <view class="hero-card__content">
          <text class="hero-card__title">分享好物 赚取佣金</text>
          <text class="hero-card__desc">多重奖励 · 上不封顶</text>
          <view class="hero-card__button" hover-class="hero-card__button--hover" @click="handleShareNow">
            <text class="hero-card__button-text">立即分享赚钱</text>
            <uni-icons type="right" size="14" color="#aa3c00"></uni-icons>
          </view>
        </view>
      </view>

      <view class="process-card">
        <view class="process-row">
          <view class="process-item">
            <view class="process-item__icon-wrap process-item__icon-wrap--orange">
              <image class="process-item__icon" src="../static/distribute-step-icon-1.png" mode="aspectFit"></image>
            </view>
            <text class="process-item__title">推广商品</text>
            <text class="process-item__desc">选择商品</text>
          </view>

          <view class="process-arrow">
            <uni-icons type="right" size="12" color="#f4b9ad"></uni-icons>
          </view>

          <view class="process-item">
            <view class="process-item__icon-wrap process-item__icon-wrap--coral">
              <image class="process-item__icon" src="../static/distribute-step-icon-2.png" mode="aspectFit"></image>
            </view>
            <text class="process-item__title">分享海报</text>
            <text class="process-item__desc">分享好友</text>
          </view>

          <view class="process-arrow">
            <uni-icons type="right" size="12" color="#f4b9ad"></uni-icons>
          </view>

          <view class="process-item">
            <view class="process-item__icon-wrap process-item__icon-wrap--purple">
              <image class="process-item__icon" src="../static/distribute-step-icon-3.png" mode="aspectFit"></image>
            </view>
            <text class="process-item__title">好友下单</text>
            <text class="process-item__desc">完成购买</text>
          </view>

          <view class="process-arrow">
            <uni-icons type="right" size="12" color="#f4b9ad"></uni-icons>
          </view>

          <view class="process-item">
            <view class="process-item__icon-wrap process-item__icon-wrap--gold">
              <image class="process-item__icon" src="../static/distribute-step-icon-4.png" mode="aspectFit"></image>
            </view>
            <text class="process-item__title">获得佣金</text>
            <text class="process-item__desc">收入到账</text>
          </view>
        </view>
      </view>

      <view class="poster-card">
        <swiper
          class="poster-card__swiper"
          :autoplay="false"
          :indicator-dots="posterSlides.length > 1"
          indicator-color="rgba(255, 255, 255, 0.48)"
          indicator-active-color="#ffffff"
          :current="currentPosterIndex"
          @change="handlePosterChange"
        >
          <swiper-item v-for="item in posterSlides" :key="item.id">
            <view class="poster-card__slide">
              <image class="poster-card__image" :src="item.displayImage" mode="aspectFill"></image>
              <view v-if="loading || composeLoading" class="poster-card__loading">
                <text class="poster-card__loading-text">海报生成中...</text>
              </view>
            </view>
          </swiper-item>
        </swiper>
      </view>

      <view class="save-poster-row">
        <view class="save-poster-button" hover-class="save-poster-button--hover" @click="handleSavePoster">
          <text class="save-poster-button__text">保存海报</text>
        </view>
      </view>

      <view class="page-bottom-space"></view>
    </view>

    <canvas
      id="promotionPosterCanvas"
      canvas-id="promotionPosterCanvas"
      class="poster-canvas"
      :width="canvasWidth"
      :height="canvasHeight"
    ></canvas>

    <app-floating-button />
  </view>
</template>

<script setup>
import { getCommissionPromotionPoster } from '@/api/commission'
import { normalizePublicErrorMessage } from '@/utils/error'
import { getTerminalType, TERMINAL_TYPES } from '@/utils/platform'
import { resolveAssetUrl } from '@/utils/runtime_config'
import { computed, getCurrentInstance, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
// #ifdef H5
import QrCodeJs2 from 'qrcodejs2'
// #endif

let H5QRCode = null
// #ifdef H5
H5QRCode = QrCodeJs2
// #endif

const CANVAS_ID = 'promotionPosterCanvas'
const POSTER_CANVAS_WIDTH = 750
const POSTER_CANVAS_HEIGHT = 1050
const DEFAULT_POSTER_IMAGE = '../static/distribute-hero-figure.png'

// 海报底图绘制区域：后期调整海报在画布中的位置和尺寸时，修改这里的 x/y/width/height。
const POSTER_IMAGE_LAYOUT = {
  x: 0,
  y: 0,
  width: POSTER_CANVAS_WIDTH,
  height: POSTER_CANVAS_HEIGHT
}

// 二维码右下角位置：后期需要调整二维码大小或边距时，修改 size/right/bottom。
const POSTER_QRCODE_LAYOUT = {
  size: 128,
  right: 68,
  bottom: 48
}

defineOptions({ name: 'UserDistributePage' })

const { proxy } = getCurrentInstance() || {}

const loading = ref(false)
const composeLoading = ref(false)
const shareCopy = ref('分享好物赚取佣金，长按海报保存后转发给好友，轻松开启你的推广收益。')
const recordRoute = '/pages/users/commission/index'
const posterItems = ref([])
const currentPosterIndex = ref(0)
const qrcodeImage = ref('')
const qrcodeUrl = ref('')
const promotionUrl = ref('')
const promotionPath = ref('')
const qrcodeRecordId = ref(0)
const canvasId = CANVAS_ID
const canvasWidth = POSTER_CANVAS_WIDTH
const canvasHeight = POSTER_CANVAS_HEIGHT
const defaultPosterImage = DEFAULT_POSTER_IMAGE

/**
 * 轮播展示的海报列表。
 *
 * @returns {Array<Object>} 优先展示接口海报，接口为空时展示默认占位海报。
 */
const posterSlides = computed(() => {
  if (posterItems.value.length > 0) {
    return posterItems.value
  }

  return [buildDefaultPosterItem()]
})

onLoad(() => {
  loadPromotionPoster()
})

/**
 * 返回上一页，若无历史则回到佣金页。
 */
function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.redirectTo({
        url: recordRoute
      })
    }
  })
}

/**
 * 打开推广记录页。
 */
function handleRecord() {
  uni.navigateTo({
    url: recordRoute
  })
}

/**
 * 复制分享文案，方便直接转发。
 */
function handleShareNow() {
  const text = String(promotionUrl.value || shareCopy.value || '')

  uni.setClipboardData({
    data: text,
    success: () => {
      uni.showToast({
        title: '分享文案已复制',
        icon: 'none'
      })
    }
  })
}

/**
 * 记录当前轮播海报下标。
 *
 * @param {Object} event swiper change 事件。
 */
function handlePosterChange(event = {}) {
  const detail = event.detail || {}
  const current = Number(detail.current || 0)
  currentPosterIndex.value = current >= 0 ? current : 0
}

/**
 * 预览海报大图，微信小程序中可长按保存。
 */
async function handleSavePoster() {
  if (loading.value || composeLoading.value) {
    return
  }

  const activeIndex = getActivePosterIndex()
  if (activeIndex < 0) {
    showToast('海报还没有生成成功')
    return
  }

  const poster = posterItems.value[activeIndex] || {}
  let image = poster.composedImage || ''
  if (!image) {
    composeLoading.value = true
    try {
      image = await composePosterByIndex(activeIndex)
    } catch (error) {
      showToast(normalizePublicErrorMessage(error && (error.message || error.errMsg), '海报合成失败'))
      return
    } finally {
      composeLoading.value = false
    }
  }

  if (!image) {
    showToast('海报还没有生成成功')
    return
  }

  savePosterImage(image)
}

/**
 * 拉取推广海报接口数据，并触发二维码与海报合成。
 *
 * @returns {Promise<void>} 加载完成后刷新页面海报。
 */
async function loadPromotionPoster() {
  loading.value = true
  try {
    const res = await getCommissionPromotionPoster({}, { showError: false, loginRedirect: true })
    if (!res || res.code !== 200 || !res.data) {
      showToast((res && res.msg) || '推广海报加载失败')
      return
    }

    applyPromotionData(res.data)
    await prepareQrcodeImage()
    await composePromotionPosters()
  } catch (error) {
    showToast(normalizePublicErrorMessage(error && (error.message || error.errMsg), '推广海报加载失败'))
  } finally {
    loading.value = false
  }
}

/**
 * 应用后端推广海报返回数据。
 *
 * @param {Object} data 接口返回的 poster/promotion/qrcode 数据。
 * @returns {void}
 */
function applyPromotionData(data = {}) {
  const promotion = data.promotion || {}
  const qrcode = data.qrcode || {}
  const rawPosterItems = normalizePosterList(data.poster)

  posterItems.value = rawPosterItems.length > 0 ? rawPosterItems : [buildDefaultPosterItem()]
  currentPosterIndex.value = 0
  promotionUrl.value = String(promotion.url || '')
  promotionPath.value = String(promotion.path || '')
  qrcodeRecordId.value = Number(qrcode.record_id || 0)
  qrcodeImage.value = resolvePosterImage(qrcode.image || '')
  qrcodeUrl.value = String(qrcode.url || promotion.url || promotion.path || '')

  if (promotionUrl.value) {
    shareCopy.value = `分享好物赚取佣金，点击链接立即前往：${promotionUrl.value}`
  }
}

/**
 * 将接口返回的 poster 统一整理为轮播数据。
 *
 * @param {Array|Object|string} poster 后端返回的海报配置。
 * @returns {Array<Object>} 可直接用于轮播展示和 canvas 合成的数据。
 */
function normalizePosterList(poster) {
  const rawList = Array.isArray(poster) ? poster : (poster ? [poster] : [])

  return rawList
    .map((item, index) => {
      const sourceImage = resolvePosterImage(item)
      return sourceImage ? buildPosterItem(sourceImage, index, item) : null
    })
    .filter(Boolean)
}

/**
 * 兼容旧配置字段与新上传组件字段，提取海报图片地址。
 *
 * @param {Object|string} poster 海报配置项或图片地址。
 * @returns {string} 规范化后的图片地址。
 */
function resolvePosterImage(poster) {
  let source = ''
  if (typeof poster === 'string') {
    source = poster
  } else if (poster && typeof poster === 'object') {
    source = poster.url || poster.image || poster.src || poster.path || ''
  }

  const sourceText = String(source || '').trim()
  if (!sourceText) {
    return ''
  }

  const isLocalPageAsset = sourceText.startsWith('../') || sourceText.startsWith('./')
  return isLocalPageAsset ? sourceText : resolveAssetUrl(sourceText)
}

/**
 * 构建单张海报轮播项。
 *
 * @param {string} sourceImage 海报底图地址。
 * @param {number} index 海报下标。
 * @param {Object|string} raw 原始接口数据。
 * @returns {Object} 轮播项数据。
 */
function buildPosterItem(sourceImage = '', index = 0, raw = {}) {
  const fileName = raw && typeof raw === 'object' ? String(raw.fileName || raw.name || '') : ''
  const id = `${fileName || 'poster'}-${index}`

  return {
    id,
    fileName,
    sourceImage,
    displayImage: sourceImage,
    composedImage: ''
  }
}

/**
 * 构建接口为空时的默认海报轮播项。
 *
 * @returns {Object} 默认海报项。
 */
function buildDefaultPosterItem() {
  return {
    id: 'default-poster',
    fileName: '',
    sourceImage: defaultPosterImage,
    displayImage: defaultPosterImage,
    composedImage: ''
  }
}

/**
 * 获取当前轮播海报下标。
 *
 * @returns {number} 有效下标，不存在海报时返回 -1。
 */
function getActivePosterIndex() {
  const length = posterItems.value.length
  if (length <= 0) {
    return -1
  }

  const current = Number(currentPosterIndex.value || 0)
  const index = Math.min(Math.max(current, 0), length - 1)
  if (index !== current) {
    currentPosterIndex.value = index
  }

  return index
}

/**
 * 准备二维码图片。
 *
 * H5/公众号：前端根据推广链接生成二维码。
 * 微信小程序：直接使用后端返回的小程序码图片。
 *
 * @returns {Promise<void>} 二维码图片准备完成。
 */
async function prepareQrcodeImage() {
  if (qrcodeImage.value) {
    return
  }

  if (!qrcodeUrl.value) {
    return
  }

  if (getTerminalType() === TERMINAL_TYPES.MINIAPP) {
    showToast('小程序码生成失败，请稍后重试')
    return
  }

  qrcodeImage.value = await generateFrontendQrcode(qrcodeUrl.value)
}

/**
 * H5/公众号前端生成推广链接二维码。
 *
 * @param {string} text 二维码内容。
 * @returns {Promise<string>} 二维码 dataURL。
 */
function generateFrontendQrcode(text = '') {
  return new Promise((resolve, reject) => {
    // #ifdef H5
    if (!text || typeof document === 'undefined') {
      reject(new Error('二维码内容为空'))
      return
    }

    const QRCodeCtor = H5QRCode && (H5QRCode.default || H5QRCode)
    if (!QRCodeCtor) {
      reject(new Error('二维码组件加载失败'))
      return
    }

    const container = document.createElement('div')
    container.style.position = 'absolute'
    container.style.left = '-9999px'
    container.style.top = '-9999px'
    document.body.appendChild(container)

    new QRCodeCtor(container, {
      text,
      width: 256,
      height: 256,
      correctLevel: QRCodeCtor.CorrectLevel ? QRCodeCtor.CorrectLevel.H : 2
    })

    setTimeout(() => {
      const canvas = container.querySelector('canvas')
      const image = container.querySelector('img')
      const dataUrl = canvas ? canvas.toDataURL('image/png') : (image ? image.src : '')
      document.body.removeChild(container)
      dataUrl ? resolve(dataUrl) : reject(new Error('二维码生成失败'))
    }, 80)
    // #endif

    // #ifndef H5
    reject(new Error('当前端不支持前端二维码生成'))
    // #endif
  })
}

/**
 * 使用 canvas 逐张合成海报底图和二维码。
 *
 * @returns {Promise<void>} 合成完成后刷新 posterItems。
 */
async function composePromotionPosters() {
  if (!posterItems.value.length || !qrcodeImage.value) {
    return
  }

  composeLoading.value = true
  let failedCount = 0
  try {
    for (let index = 0; index < posterItems.value.length; index += 1) {
      try {
        await composePosterByIndex(index)
      } catch (error) {
        failedCount += 1
      }
    }

    if (failedCount >= posterItems.value.length) {
      throw new Error('海报合成失败')
    }

    if (failedCount > 0) {
      showToast('部分海报合成失败')
    }
  } catch (error) {
    showToast(normalizePublicErrorMessage(error && (error.message || error.errMsg), '海报合成失败'))
  } finally {
    composeLoading.value = false
  }
}

/**
 * 合成指定下标的海报。
 *
 * @param {number} index 海报下标。
 * @returns {Promise<string>} 合成后的图片地址。
 */
async function composePosterByIndex(index = 0) {
  const poster = posterItems.value[index]
  if (!poster) {
    return ''
  }

  const sourceImage = poster.sourceImage || defaultPosterImage
  if (!sourceImage || !qrcodeImage.value) {
    return ''
  }

  const composedImage = await composePosterImage(sourceImage)
  posterItems.value[index] = {
    ...poster,
    displayImage: composedImage || sourceImage,
    composedImage
  }

  return composedImage
}

/**
 * 使用 canvas 合成单张海报底图和二维码。
 *
 * @param {string} posterImage 海报底图地址。
 * @returns {Promise<string>} 合成后的图片地址。
 */
async function composePosterImage(posterImage = '') {
  const posterPath = await resolveCanvasImage(posterImage)
  const qrcodePath = await resolveCanvasImage(qrcodeImage.value)
  const ctx = uni.createCanvasContext(canvasId, proxy)

  ctx.clearRect(0, 0, canvasWidth, canvasHeight)

  // 海报底图位置：这里绘制整张背景图，后期可通过 POSTER_IMAGE_LAYOUT 调整 x/y/width/height。
  ctx.drawImage(
    posterPath,
    POSTER_IMAGE_LAYOUT.x,
    POSTER_IMAGE_LAYOUT.y,
    POSTER_IMAGE_LAYOUT.width,
    POSTER_IMAGE_LAYOUT.height
  )

  const qrcodeX = canvasWidth - POSTER_QRCODE_LAYOUT.right - POSTER_QRCODE_LAYOUT.size
  const qrcodeY = canvasHeight - POSTER_QRCODE_LAYOUT.bottom - POSTER_QRCODE_LAYOUT.size

  // 二维码位置：当前放在右下角，后期可通过 POSTER_QRCODE_LAYOUT 调整 size/right/bottom。
  ctx.drawImage(
    qrcodePath,
    qrcodeX,
    qrcodeY,
    POSTER_QRCODE_LAYOUT.size,
    POSTER_QRCODE_LAYOUT.size
  )

  await drawCanvas(ctx)
  return exportCanvasImage()
}

/**
 * 获取 canvas 可绘制图片路径。
 *
 * @param {string} source 原始图片地址。
 * @returns {Promise<string>} 可绘制图片路径。
 */
function resolveCanvasImage(source = '') {
  const sourceText = String(source || '').trim()
  const isLocalPageAsset = sourceText.startsWith('../') || sourceText.startsWith('./')
  const imageSource = isLocalPageAsset ? sourceText : resolveAssetUrl(sourceText)
  if (!imageSource) {
    return Promise.reject(new Error('图片地址为空'))
  }

  if (imageSource.startsWith('data:')) {
    return Promise.resolve(imageSource)
  }

  // #ifdef MP-WEIXIN
  return new Promise((resolve, reject) => {
    uni.getImageInfo({
      src: imageSource,
      success: (res) => resolve(res.path || imageSource),
      fail: () => reject(new Error('图片下载失败'))
    })
  })
  // #endif

  // #ifdef H5
  return Promise.resolve(imageSource)
  // #endif

  return Promise.resolve(imageSource)
}

/**
 * 等待 canvas 绘制完成。
 *
 * @param {Object} ctx UniApp canvas context。
 * @returns {Promise<void>} 绘制完成。
 */
function drawCanvas(ctx) {
  return new Promise((resolve) => {
    ctx.draw(false, () => {
      setTimeout(resolve, 80)
    })
  })
}

/**
 * 导出 canvas 合成图片。
 *
 * @returns {Promise<string>} 临时图片路径或 dataURL。
 */
function exportCanvasImage() {
  return new Promise((resolve, reject) => {
    uni.canvasToTempFilePath({
      canvasId,
      width: canvasWidth,
      height: canvasHeight,
      destWidth: canvasWidth,
      destHeight: canvasHeight,
      success: (res) => resolve(res.tempFilePath),
      fail: () => reject(new Error('海报导出失败'))
    }, proxy)
  })
}

/**
 * 保存或预览合成海报。
 *
 * @param {string} image 图片路径。
 * @returns {void}
 */
function savePosterImage(image = '') {
  if (!image) {
    showToast('海报图片为空')
    return
  }

  // #ifdef MP-WEIXIN
  uni.saveImageToPhotosAlbum({
    filePath: image,
    success: () => showToast('海报已保存'),
    fail: () => {
      uni.previewImage({ urls: [image] })
    }
  })
  // #endif

  // #ifdef H5
  uni.previewImage({
    urls: [image],
    success: () => showToast('长按图片保存海报')
  })
  // #endif
}

/**
 * 统一展示轻提示。
 *
 * @param {string} title 提示文案。
 * @returns {void}
 */
function showToast(title = '') {
  uni.showToast({
    title: normalizePublicErrorMessage(title, '操作失败'),
    icon: 'none'
  })
}
</script>

<style lang="scss">
page {
  background: #f6f7fb;
}
</style>

<style lang="scss" scoped>
.distribute-page {
  position: relative;
  background-color: #f6f7fb;

  &__hero-bg {
    position: absolute;
    left: 0;
    top: 0;
    z-index: 0;
    display: block;
    width: 100%;
    height: 620rpx;
  }

  &__hero-mask {
    position: absolute;
    left: 0;
    top: 390rpx;
    z-index: 0;
    width: 100%;
    height: 300rpx;
    background: linear-gradient(
      180deg,
      rgba(246, 247, 251, 0) 0%,
      rgba(246, 247, 251, 0.28) 48%,
      rgba(246, 247, 251, 0.92) 78%,
      #f6f7fb 100%
    );
  }

  &__safe-top {
    position: relative;
    z-index: 1;
  }

  &__content {
    position: relative;
    z-index: 1;
    padding: 0 24rpx 32rpx;
  }

  &__nav-action {
    display: flex;
    align-items: center;
    height: 72rpx;
    padding-left: 20rpx;
  }

  &__nav-action-text {
    font-size: 28rpx;
    line-height: 1;
    color: #2a2f39;
    font-weight: 600;
  }

  &__nav-action--hover {
    opacity: 0.72;
  }
}

.hero-card {
  position: relative;
  min-height: 334rpx;

  &__content {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 480rpx;
    min-height: 334rpx;
    padding: 42rpx 28rpx 40rpx 36rpx;
    box-sizing: border-box;
  }

  &__title {
    font-size: 46rpx;
    line-height: 1.12;
    color: #fff8f1;
    font-weight: 800;
    letter-spacing: 0;
  }

  &__desc {
    margin-top: 18rpx;
    font-size: 28rpx;
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.88);
    font-weight: 500;
  }

  &__button {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin-top: 30rpx;
    padding: 0 28rpx;
    height: 72rpx;
    border-radius: 999rpx;
    background: linear-gradient(135deg, #ffe6a7 0%, #ffd06d 100%);
    box-shadow: 0 10rpx 24rpx rgba(155, 55, 0, 0.18);
  }

  &__button-text {
    margin-right: 10rpx;
    font-size: 26rpx;
    line-height: 1;
    color: #8e3d03;
    font-weight: 700;
  }

  &__button--hover {
    opacity: 0.92;
  }
}

/* #ifdef H5 */
.distribute-page {
  &__hero-bg {
    height: 660rpx;
  }

  &__hero-mask {
    top: 420rpx;
    height: 300rpx;
  }
}
/* #endif */

/* #ifdef MP-WEIXIN */
.distribute-page {
  &__hero-bg {
    height: 720rpx;
  }

  &__hero-mask {
    top: 460rpx;
    height: 320rpx;
  }
}
/* #endif */

.process-card {
  position: relative;
  z-index: 2;
  margin-top: -30rpx;
  padding: 32rpx 22rpx 28rpx;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 12rpx 36rpx rgba(15, 23, 42, 0.06);
}

.process-row {
  display: flex;
  align-items: flex-start;
}

.process-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;

  &__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 92rpx;
    height: 92rpx;
    border-radius: 50%;
    box-shadow: 0 10rpx 18rpx rgba(255, 146, 70, 0.12);

    &--orange {
      background: linear-gradient(135deg, #ffb13a 0%, #ff8e09 100%);
    }

    &--coral {
      background: linear-gradient(135deg, #ff7b6f 0%, #ff555f 100%);
    }

    &--purple {
      background: linear-gradient(135deg, #9f8bff 0%, #7c67ff 100%);
    }

    &--gold {
      background: linear-gradient(135deg, #ffc34c 0%, #ffab09 100%);
    }
  }

  &__icon {
    width: 52rpx;
    height: 52rpx;
  }

  &__title {
    margin-top: 18rpx;
    font-size: 28rpx;
    line-height: 1.3;
    color: #111827;
    font-weight: 700;
  }

  &__desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    line-height: 1.3;
    color: #7c8492;
    font-weight: 400;
  }
}

.process-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40rpx;
  padding-top: 30rpx;
  box-sizing: border-box;
}

.poster-card {
  margin-top: 20rpx;
  border-radius: 28rpx;
  background: #ffffff;
  overflow: hidden;
  box-shadow: 0 12rpx 36rpx rgba(15, 23, 42, 0.06);

  &__swiper {
    width: 100%;
    height: 984rpx;
  }

  &__slide {
    position: relative;
    width: 100%;
    height: 100%;
    background: #f4f5f7;
  }

  &__image {
    width: 100%;
    height: 100%;
    display: block;
  }

  &__loading {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 28rpx;
    display: flex;
    justify-content: center;
  }

  &__loading-text {
    padding: 12rpx 24rpx;
    border-radius: 999rpx;
    background: rgba(17, 24, 39, 0.66);
    font-size: 24rpx;
    line-height: 1;
    color: #ffffff;
  }
}

.save-poster-row {
  margin-top: 24rpx;
}

.save-poster-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 92rpx;
  border-radius: 999rpx;
  background: linear-gradient(135deg, #ff8c4c 0%, #ff4b3e 100%);
  box-shadow: 0 14rpx 26rpx rgba(255, 95, 58, 0.22);

  &__text {
    font-size: 30rpx;
    line-height: 1;
    color: #fff;
    font-weight: 700;
  }

  &--hover {
    opacity: 0.92;
  }
}

.page-bottom-space {
  height: 28rpx;
}

.poster-canvas {
  position: absolute;
  left: -9999px;
  top: -9999px;
  width: 750px;
  height: 1050px;
  opacity: 0;
  pointer-events: none;
}
</style>
