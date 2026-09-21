<template>
  <view class="product-share-popup">
    <view v-if="visible" class="share-popup" @click="closeSharePopup">
      <view class="share-popup__panel" @click.stop>
        <image
          v-if="sharePosterImage"
          class="share-popup__poster"
          :src="sharePosterImage"
          mode="aspectFit"
        >
        </image>
        <view v-else class="share-popup__loading">
          <image
            class="share-popup__loading-bg"
            :src="sharePopupBg"
            mode="aspectFill"
          ></image>
          <view class="share-popup__loading-content">
            <text class="share-popup__loading-title">{{
              sharePosterFailed ? '海报生成失败' : '分享海报生成中'
            }}</text>
            <text class="share-popup__loading-text">{{ sharePosterTip }}</text>
            <view
              v-if="sharePosterFailed"
              class="share-popup__retry"
              hover-class="share-popup__button--hover"
              @click.stop="scheduleSharePoster"
              >重试</view
            >
          </view>
        </view>
      </view>
      <view class="share-popup__actions" @click.stop>
        <!-- #ifdef MP-WEIXIN -->
        <button
          class="share-popup__button share-popup__button--primary"
          open-type="share"
        >
          <text>分享好友</text>
        </button>
        <view
          class="share-popup__button share-popup__button--ghost"
          hover-class="share-popup__button--hover"
          @click="handleSaveSharePoster"
        >
          <text>保存海报</text>
        </view>
        <!-- #endif -->

        <!-- #ifdef H5 -->
        <view
          class="share-popup__button share-popup__button--primary"
          hover-class="share-popup__button--hover"
          @click="handleSharePageGuide"
        >
          <text>分享页面</text>
        </view>
        <!-- #endif -->
      </view>
      <view
        class="share-popup__close"
        hover-class="share-popup__close--hover"
        @click.stop="closeSharePopup"
      >
        <text class="iconfont icon-close share-popup__close-icon"></text>
      </view>
    </view>
    <canvas
      id="productSharePosterCanvas"
      canvas-id="productSharePosterCanvas"
      class="share-poster-canvas"
      :width="shareCanvasWidth"
      :height="shareCanvasHeight"
    ></canvas>
  </view>
</template>

<script setup>
import {
  computed,
  getCurrentInstance,
  nextTick,
  onBeforeUnmount,
  ref,
  watch
} from 'vue'
import { resolveAssetUrl } from '@/utils/runtime_config'

defineOptions({ name: 'ProductSharePopup' })
const props = defineProps({
  visible: { type: Boolean, default: false },
  name: { type: String, default: '' },
  image: { type: String, default: '' },
  priceText: { type: String, default: '0.00' },
  pagePath: { type: String, default: '' },
  priceLabel: { type: String, default: '' }
})
const emit = defineEmits(['close'])
const { proxy } = getCurrentInstance() || {}
const STATIC_BASE = '/static/store/product-detail'

const sharePopupBg = `${STATIC_BASE}/share-popup-bg.png`

const SHARE_STEP_ICON_SIZE = 34

const SHARE_ARROW_ICON_SIZE = 34

const SHARE_ARROW_ICON = `${STATIC_BASE}/poster-arrow.png`

const SHARE_STEP_ICONS = {
  save: `${STATIC_BASE}/poster-save.png`,
  wechat: `${STATIC_BASE}/poster-wechat.png`,
  share: `${STATIC_BASE}/poster-share.png`
}

const SHARE_CANVAS_ID = 'productSharePosterCanvas'

const SHARE_CANVAS_WIDTH = 626

const SHARE_CANVAS_HEIGHT = 940

const SHARE_PRODUCT_CARD = {
  x: 48,
  y: 168,
  width: 530,
  height: 680,
  radius: 28,
  imageHeight: 360,
  infoHeight: 132
}

const SHARE_QRCODE_SIZE = 134

const SHARE_QRCODE_SCRIPT = '/static/vendor/qrcode.min.js'

const shareCanvasWidth = SHARE_CANVAS_WIDTH

const shareCanvasHeight = SHARE_CANVAS_HEIGHT

let H5QRCodeReady = null

const shareSteps = [
  {
    id: 'save',
    title: '保存图片'
  },
  {
    id: 'wechat',
    title: '微信好友'
  },
  {
    id: 'share',
    title: '分享好友'
  }
]

const shareQrcodeImage = ref('')
const shareQrcodeTip = ref('右上角分享')
const sharePosterImage = ref('')
const sharePosterTip = ref('正在合成商品图片与二维码')

const sharePosterFailed = ref(false)
const shareProductName = computed(() => props.name || '商品详情')
const shareProductImage = computed(() => props.image)
const sharePagePath = computed(() => props.pagePath)
const priceInteger = computed(() => String(props.priceText).split('.')[0])
const priceDecimal = computed(() =>
  (String(props.priceText).split('.')[1] || '00').padEnd(2, '0').slice(0, 2)
)
let posterSequence = 0
let posterTask = Promise.resolve()
let destroyed = false

/** 串行绘制共用 canvas，避免关闭重开或刷新时旧任务覆盖新海报。
 * @returns {void}
 */
function scheduleSharePoster() {
  const current = ++posterSequence
  if (!props.visible || destroyed) return
  sharePosterImage.value = ''
  sharePosterFailed.value = false
  sharePosterTip.value = '正在合成商品图片与二维码'
  posterTask = posterTask.then(async () => {
    if (current !== posterSequence || !props.visible || destroyed) return
    shareQrcodeImage.value = ''
    shareQrcodeTip.value = '右上角分享'
    await nextTick()
    await composeSharePoster(current)
  })
}
/** 关闭分享弹窗。
 * @returns {void}
 */
function closeSharePopup() {
  posterSequence += 1
  emit('close')
}

watch(
  () => [
    props.visible,
    props.name,
    props.image,
    props.priceText,
    props.pagePath
  ],
  scheduleSharePoster,
  { immediate: true, flush: 'post' }
)
onBeforeUnmount(() => {
  destroyed = true
  posterSequence += 1
})

/**
 * 保存商品分享海报。
 *
 * @returns {void}
 */
function handleSaveSharePoster() {
  if (!sharePosterImage.value) {
    showToast('海报生成中')
    return
  }

  // #ifdef MP-WEIXIN
  uni.saveImageToPhotosAlbum({
    filePath: sharePosterImage.value,
    success: () => showToast('海报已保存'),
    fail: () => {
      uni.previewImage({
        urls: [sharePosterImage.value]
      })
    }
  })
  // #endif

  // #ifdef H5
  uni.previewImage({
    urls: [sharePosterImage.value],
    success: () => showToast('长按图片保存海报')
  })
  // #endif
}

/**
 * 公众号/H5 分享引导。
 *
 * @returns {void}
 */
function handleSharePageGuide() {
  showToast('请使用右上角三个点进行分享')
}

/** 合成海报，关闭或重开后不回填旧结果。
 * @param {number} current 本次海报任务序号。
 * @returns {Promise<void>}
 */
async function composeSharePoster(current) {
  try {
    await prepareShareQrcode()
    if (current !== posterSequence || !props.visible || destroyed) return
    const image = await drawSharePoster()
    if (current === posterSequence && props.visible && !destroyed)
      sharePosterImage.value = image
  } catch (error) {
    if (current !== posterSequence || !props.visible || destroyed) return
    sharePosterFailed.value = true
    sharePosterTip.value = resolveErrorMessage(error, '海报生成失败，请重试')
  }
}

/** H5 生成真实分享二维码；小程序使用页面原生好友分享。
 * @returns {Promise<string>} H5 二维码图片，小程序为空。
 */
async function prepareShareQrcode() {
  // #ifdef H5
  const QRCodeCtor = await loadH5QrcodeScript()
  const image = await generateShareQrcode(resolveShareUrl(), QRCodeCtor)
  shareQrcodeImage.value = image
  return image
  // #endif
  // #ifndef H5
  shareQrcodeTip.value = '右上角分享'
  return ''
  // #endif
}

/** 按需加载既有二维码资源，失败后允许重新加载。
 * @returns {Promise<Function>} 二维码构造器。
 */
function loadH5QrcodeScript() {
  // #ifdef H5
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return Promise.reject(new Error('浏览器环境不可用'))
  }
  if (window.QRCode) return Promise.resolve(window.QRCode)
  if (H5QRCodeReady) return H5QRCodeReady
  H5QRCodeReady = new Promise((resolve, reject) => {
    let script = document.querySelector(`script[src="${SHARE_QRCODE_SCRIPT}"]`)
    const created = !script
    if (created) {
      script = document.createElement('script')
      script.src = SHARE_QRCODE_SCRIPT
      script.async = true
    }
    let settled = false
    const timer = setTimeout(
      () => finish(new Error('二维码脚本加载超时')),
      10000
    )
    function finish(error) {
      if (settled) return
      settled = true
      clearTimeout(timer)
      script.removeEventListener('load', onLoad)
      script.removeEventListener('error', onError)
      if (error) {
        if (script.parentNode) script.parentNode.removeChild(script)
        reject(error)
      } else resolve(window.QRCode)
    }
    function onLoad() {
      finish(window.QRCode ? null : new Error('二维码脚本加载失败'))
    }
    function onError() {
      finish(new Error('二维码脚本加载失败'))
    }
    script.addEventListener('load', onLoad)
    script.addEventListener('error', onError)
    if (created) (document.head || document.body).appendChild(script)
  }).catch((error) => {
    H5QRCodeReady = null
    throw error
  })
  return H5QRCodeReady
  // #endif
  // #ifndef H5
  return Promise.reject(new Error('当前端不支持前端二维码生成'))
  // #endif
}

/** 使用当前 H5 路由模式解析分享地址。
 * @returns {string} 分享链接或小程序路径。
 */
function resolveShareUrl() {
  // #ifdef H5
  if (typeof window !== 'undefined' && window.location) {
    const location = window.location
    const prefix =
      location.hash && location.hash.startsWith('#/')
        ? (location.pathname || '/') + '#'
        : ''
    return (location.origin || '') + prefix + sharePagePath.value
  }
  // #endif
  return sharePagePath.value
}

/** 使用既有二维码库生成海报二维码，并清理临时节点。
 * @param {string} text 分享地址。
 * @param {Function} QRCodeCtor 二维码构造器。
 * @returns {Promise<string>} 二维码图片。
 */
function generateShareQrcode(text, QRCodeCtor) {
  // #ifdef H5
  return new Promise((resolve, reject) => {
    if (!text || typeof document === 'undefined' || !QRCodeCtor) {
      reject(new Error('二维码内容为空'))
      return
    }
    const container = document.createElement('div')
    container.style.position = 'absolute'
    container.style.left = '-9999px'
    container.style.top = '-9999px'
    document.body.appendChild(container)
    const cleanup = () => {
      if (container.parentNode) container.parentNode.removeChild(container)
    }
    try {
      new QRCodeCtor(container, {
        text,
        width: 260,
        height: 260,
        correctLevel: QRCodeCtor.CorrectLevel ? QRCodeCtor.CorrectLevel.H : 2
      })
      setTimeout(() => {
        try {
          const canvas = container.querySelector('canvas')
          const image = container.querySelector('img')
          const dataUrl = canvas
            ? canvas.toDataURL('image/png')
            : image && image.src
          dataUrl ? resolve(dataUrl) : reject(new Error('二维码生成失败'))
        } catch (error) {
          reject(error)
        } finally {
          cleanup()
        }
      }, 80)
    } catch (error) {
      cleanup()
      reject(error)
    }
  })
  // #endif
  // #ifndef H5
  return Promise.reject(new Error('当前端不支持前端二维码生成'))
  // #endif
}

/**
 * 绘制完整商品分享海报并导出图片。
 *
 * @returns {Promise<string>} canvas 导出的图片。
 */
async function drawSharePoster() {
  const ctx = uni.createCanvasContext(SHARE_CANVAS_ID, proxy)
  const bgPath = await resolveCanvasImage(sharePopupBg)

  ctx.clearRect(0, 0, SHARE_CANVAS_WIDTH, SHARE_CANVAS_HEIGHT)
  ctx.drawImage(bgPath, 0, 0, SHARE_CANVAS_WIDTH, SHARE_CANVAS_HEIGHT)
  drawSharePosterHeader(ctx)
  await drawShareProductBlock(ctx)
  await drawShareCanvas(ctx)
  return exportShareCanvasImage()
}

/**
 * 绘制分享海报标题。
 *
 * @param {Object} ctx canvas 上下文。
 * @returns {void}
 */
function drawSharePosterHeader(ctx) {
  drawCanvasText(ctx, '分享好物', 313, 50, {
    color: '#321706',
    size: 44,
    bold: true,
    align: 'center'
  })

  drawRibbon(ctx, 192, 114, 242, 42)
  drawCanvasText(ctx, '一起发现品质生活', 313, 122, {
    color: '#9b4d19',
    size: 26,
    bold: true,
    align: 'center'
  })
}

/**
 * 绘制商品、二维码和分享步骤组合块。
 *
 * @param {Object} ctx canvas 上下文。
 * @returns {Promise<void>} 绘制完成。
 */
async function drawShareProductBlock(ctx) {
  const card = SHARE_PRODUCT_CARD
  fillRoundRect(
    ctx,
    card.x,
    card.y,
    card.width,
    card.height,
    card.radius,
    '#fffaf2'
  )

  await drawShareGoodsImage(ctx)
  drawShareGoodsInfo(ctx)
  drawDashedLine(
    ctx,
    card.x + 26,
    card.y + card.imageHeight + card.infoHeight,
    card.x + card.width - 26
  )
  await drawShareGuide(ctx)
}

/**
 * 绘制商品图片。
 *
 * @param {Object} ctx canvas 上下文。
 * @returns {Promise<void>} 绘制完成。
 */
async function drawShareGoodsImage(ctx) {
  const card = SHARE_PRODUCT_CARD
  const imageInfo = await resolveCanvasImageInfo(shareProductImage.value)
  drawRoundedImage(
    ctx,
    imageInfo,
    card.x,
    card.y,
    card.width,
    card.imageHeight,
    card.radius,
    true,
    false
  )
}

/**
 * 绘制商品标题、标签和价格。
 *
 * @param {Object} ctx canvas 上下文。
 * @returns {void}
 */
function drawShareGoodsInfo(ctx) {
  const card = SHARE_PRODUCT_CARD
  const top = card.y + card.imageHeight
  const nameLines = wrapCanvasText(ctx, shareProductName.value, 26, 246, 2)
  nameLines.forEach((line, index) => {
    drawCanvasText(ctx, line, card.x + 28, top + 24 + index * 36, {
      color: '#2d1708',
      size: 26,
      bold: true
    })
  })

  drawTag(ctx, card.x + 28, top + 98, props.priceLabel || '精选好物', 88)
  drawTag(ctx, card.x + 130, top + 98, '品质优选', 88)
  drawSolidLine(ctx, card.x + 322, top + 24, card.x + 322, top + 116, '#f2d2b4')

  const symbolX = card.x + 344
  const integerX = card.x + 372
  const priceMaxWidth = card.x + card.width - integerX - 22
  let integerSize = 54
  let decimalSize = 28
  let integerWidth = measureCanvasText(ctx, priceInteger.value, integerSize)
  let decimalWidth = measureCanvasText(
    ctx,
    `.${priceDecimal.value}`,
    decimalSize
  )
  while (integerWidth + decimalWidth + 8 > priceMaxWidth && integerSize > 38) {
    integerSize -= 2
    decimalSize = Math.max(22, decimalSize - 1)
    integerWidth = measureCanvasText(ctx, priceInteger.value, integerSize)
    decimalWidth = measureCanvasText(ctx, `.${priceDecimal.value}`, decimalSize)
  }

  drawCanvasText(ctx, '¥', symbolX, top + 50, {
    color: '#f0582a',
    size: 26,
    bold: true
  })
  drawCanvasText(ctx, priceInteger.value, integerX, top + 28, {
    color: '#f0582a',
    size: integerSize,
    bold: true
  })
  drawCanvasText(
    ctx,
    `.${priceDecimal.value}`,
    integerX + integerWidth + 4,
    top + 56,
    {
      color: '#f0582a',
      size: decimalSize,
      bold: true
    }
  )
  drawPill(ctx, card.x + 350, top + 94, 138, 38, '立即抢购')
}

/**
 * 绘制二维码、提示语和分享步骤。
 *
 * @param {Object} ctx canvas 上下文。
 * @returns {Promise<void>} 绘制完成。
 */
async function drawShareGuide(ctx) {
  const card = SHARE_PRODUCT_CARD
  const top = card.y + card.imageHeight + card.infoHeight
  const qrcodeX = card.x + 28
  const qrcodeY = top + 26

  fillRoundRect(
    ctx,
    qrcodeX,
    qrcodeY,
    SHARE_QRCODE_SIZE,
    SHARE_QRCODE_SIZE,
    12,
    '#ffffff'
  )
  if (shareQrcodeImage.value) {
    try {
      const qrcodePath = await resolveCanvasImage(shareQrcodeImage.value)
      ctx.drawImage(
        qrcodePath,
        qrcodeX + 9,
        qrcodeY + 9,
        SHARE_QRCODE_SIZE - 18,
        SHARE_QRCODE_SIZE - 18
      )
    } catch (error) {
      drawQrcodePlaceholder(
        ctx,
        qrcodeX + 9,
        qrcodeY + 9,
        SHARE_QRCODE_SIZE - 18
      )
    }
  } else {
    drawQrcodePlaceholder(ctx, qrcodeX + 9, qrcodeY + 9, SHARE_QRCODE_SIZE - 18)
  }

  drawCanvasText(ctx, '“', card.x + 194, top + 34, {
    color: '#ef5b2e',
    size: 58,
    bold: true
  })
  drawCanvasText(ctx, '长按识别二维码', card.x + 226, top + 36, {
    color: '#3b220f',
    size: 24,
    bold: true
  })
  drawCanvasText(ctx, '查看商品详情', card.x + 226, top + 72, {
    color: '#3b220f',
    size: 24,
    bold: true
  })

  await drawShareStep(
    ctx,
    card.x + 220,
    top + 118,
    SHARE_STEP_ICONS.save,
    shareSteps[0].title
  )
  await drawShareArrow(ctx, card.x + 275, top + 118)
  await drawShareStep(
    ctx,
    card.x + 330,
    top + 118,
    SHARE_STEP_ICONS.wechat,
    shareSteps[1].title
  )
  await drawShareArrow(ctx, card.x + 385, top + 118)
  await drawShareStep(
    ctx,
    card.x + 440,
    top + 118,
    SHARE_STEP_ICONS.share,
    shareSteps[2].title
  )
}

/**
 * 绘制二维码缺省占位。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} size 尺寸。
 * @returns {void}
 */
function drawQrcodePlaceholder(ctx, x, y, size) {
  drawDashedRect(ctx, x, y, size, size, '#f06a3d')
  drawCanvasText(ctx, '▦', x + size / 2, y + 34, {
    color: '#ef5b2e',
    size: 46,
    align: 'center'
  })
  drawCanvasText(ctx, shareQrcodeTip.value, x + size / 2, y + 82, {
    color: '#9f6a4a',
    size: 18,
    align: 'center'
  })
}

/**
 * 绘制分享步骤。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {string} icon 图片地址。
 * @param {string} title 标题。
 * @returns {Promise<void>} 绘制完成。
 */
async function drawShareStep(ctx, x, y, icon, title) {
  if (!(await drawStaticCanvasIcon(ctx, icon, x, y, SHARE_STEP_ICON_SIZE))) {
    drawCanvasText(ctx, '•', x, y, {
      color: '#4a260f',
      size: 30,
      align: 'center'
    })
  }
  drawCanvasText(ctx, title, x, y + 46, {
    color: '#6f5c4e',
    size: 20,
    align: 'center'
  })
}

/**
 * 绘制分享步骤之间的箭头。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @returns {Promise<void>} 绘制完成。
 */
async function drawShareArrow(ctx, x, y) {
  if (
    !(await drawStaticCanvasIcon(
      ctx,
      SHARE_ARROW_ICON,
      x,
      y,
      SHARE_ARROW_ICON_SIZE
    ))
  ) {
    drawCanvasText(ctx, '→', x, y + 6, {
      color: '#d8a477',
      size: 30,
      bold: true,
      align: 'center'
    })
  }
}

/**
 * 绘制本地静态图标。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {string} source 静态图标路径。
 * @param {number} x 中心横坐标。
 * @param {number} y 顶部纵坐标。
 * @param {number} size 图标尺寸。
 * @returns {Promise<boolean>} 是否已写入绘制指令。
 */
async function drawStaticCanvasIcon(ctx, source, x, y, size) {
  const icon = String(source || '').trim()
  if (!icon) {
    return false
  }

  try {
    const iconPath = await resolveCanvasImage(icon)
    ctx.drawImage(iconPath, x - size / 2, y, size, size)
    return true
  } catch (error) {
    return false
  }
}

/**
 * 解析 canvas 可绘制图片地址。
 *
 * @param {string} source 原图片地址。
 * @returns {Promise<string>} 可绘制图片地址。
 */
function resolveCanvasImage(source = '') {
  const sourceText = String(source || '').trim()
  if (!sourceText) {
    return Promise.reject(new Error('图片地址为空'))
  }

  let imageSource = sourceText

  const isLocalStaticAsset =
    sourceText.startsWith('/static/') ||
    sourceText.startsWith('./') ||
    sourceText.startsWith('../')
  imageSource =
    sourceText.startsWith('data:') || isLocalStaticAsset
      ? sourceText
      : resolveAssetUrl(sourceText)

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
 * 获取 canvas 图片路径和原始尺寸。
 *
 * @param {string} source 原图片地址。
 * @returns {Promise<Object>} 图片信息。
 */
function resolveCanvasImageInfo(source = '') {
  return resolveCanvasImage(source).then(
    (path) =>
      new Promise((resolve, reject) => {
        // #ifdef H5
        if (typeof Image !== 'undefined') {
          const image = new Image()
          image.crossOrigin = 'anonymous'
          image.onload = () => {
            resolve({
              path,
              width: Number(image.naturalWidth || image.width || 0),
              height: Number(image.naturalHeight || image.height || 0)
            })
          }
          image.onerror = () => {
            reject(new Error('图片加载失败'))
          }
          image.src = path
          return
        }
        // #endif

        uni.getImageInfo({
          src: path,
          success: (res) => {
            resolve({
              path: res.path || path,
              width: Number(res.width || 0),
              height: Number(res.height || 0)
            })
          },
          fail: () => {
            reject(new Error('图片加载失败'))
          }
        })
      })
  )
}

/**
 * 等待分享 canvas 绘制完成。
 *
 * @param {Object} ctx canvas 上下文。
 * @returns {Promise<void>} 绘制完成。
 */
function drawShareCanvas(ctx) {
  return new Promise((resolve) => {
    ctx.draw(false, () => {
      setTimeout(resolve, 80)
    })
  })
}

/**
 * 导出分享 canvas 图片。
 *
 * @returns {Promise<string>} 导出的临时图片路径。
 */
function exportShareCanvasImage() {
  return new Promise((resolve, reject) => {
    uni.canvasToTempFilePath(
      {
        canvasId: SHARE_CANVAS_ID,
        width: SHARE_CANVAS_WIDTH,
        height: SHARE_CANVAS_HEIGHT,
        destWidth: SHARE_CANVAS_WIDTH,
        destHeight: SHARE_CANVAS_HEIGHT,
        success: (res) => resolve(res.tempFilePath),
        fail: () => reject(new Error('海报导出失败'))
      },
      proxy
    )
  })
}

/**
 * 绘制圆角图片。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {Object} imageInfo 图片信息。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @param {number} radius 圆角。
 * @param {boolean} topLeft 左上是否圆角。
 * @param {boolean} bottomCorners 底部是否圆角。
 * @returns {void}
 */
function drawRoundedImage(
  ctx,
  imageInfo,
  x,
  y,
  width,
  height,
  radius,
  topLeft = true,
  bottomCorners = true
) {
  ctx.save()
  drawRoundRectPath(ctx, x, y, width, height, {
    topLeft: topLeft ? radius : 0,
    topRight: radius,
    bottomRight: bottomCorners ? radius : 0,
    bottomLeft: bottomCorners ? radius : 0
  })
  ctx.clip()
  drawAspectFillImage(ctx, imageInfo, x, y, width, height)
  ctx.restore()
}

/**
 * 按 aspectFill 规则绘制图片。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {Object} imageInfo 图片信息。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @returns {void}
 */
function drawAspectFillImage(ctx, imageInfo, x, y, width, height) {
  const image = imageInfo && imageInfo.path ? imageInfo.path : ''
  const sourceWidth = Number(imageInfo && imageInfo.width ? imageInfo.width : 0)
  const sourceHeight = Number(
    imageInfo && imageInfo.height ? imageInfo.height : 0
  )
  if (!image) {
    return
  }

  if (sourceWidth <= 0 || sourceHeight <= 0) {
    ctx.drawImage(image, x, y, width, height)
    return
  }

  const targetRatio = width / height
  const sourceRatio = sourceWidth / sourceHeight
  let sx = 0
  let sy = 0
  let sw = sourceWidth
  let sh = sourceHeight

  if (sourceRatio > targetRatio) {
    sw = sourceHeight * targetRatio
    sx = (sourceWidth - sw) / 2
  } else {
    sh = sourceWidth / targetRatio
    sy = (sourceHeight - sh) / 2
  }

  ctx.drawImage(image, sx, sy, sw, sh, x, y, width, height)
}

/**
 * 绘制圆角填充矩形。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @param {number} radius 圆角。
 * @param {string} color 填充色。
 * @returns {void}
 */
function fillRoundRect(ctx, x, y, width, height, radius, color) {
  ctx.setFillStyle(color)
  drawRoundRectPath(ctx, x, y, width, height, radius)
  ctx.fill()
}

/**
 * 绘制圆角矩形路径。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @param {number|Object} radius 圆角配置。
 * @returns {void}
 */
function drawRoundRectPath(ctx, x, y, width, height, radius) {
  const radiusConfig =
    typeof radius === 'number'
      ? {
          topLeft: radius,
          topRight: radius,
          bottomRight: radius,
          bottomLeft: radius
        }
      : radius

  const tl = radiusConfig.topLeft || 0
  const tr = radiusConfig.topRight || 0
  const br = radiusConfig.bottomRight || 0
  const bl = radiusConfig.bottomLeft || 0

  ctx.beginPath()
  ctx.moveTo(x + tl, y)
  ctx.lineTo(x + width - tr, y)
  ctx.quadraticCurveTo(x + width, y, x + width, y + tr)
  ctx.lineTo(x + width, y + height - br)
  ctx.quadraticCurveTo(x + width, y + height, x + width - br, y + height)
  ctx.lineTo(x + bl, y + height)
  ctx.quadraticCurveTo(x, y + height, x, y + height - bl)
  ctx.lineTo(x, y + tl)
  ctx.quadraticCurveTo(x, y, x + tl, y)
  ctx.closePath()
}

/**
 * 绘制缎带标题背景。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @returns {void}
 */
function drawRibbon(ctx, x, y, width, height) {
  ctx.setFillStyle('#ffe1a3')
  ctx.beginPath()
  ctx.moveTo(x + 18, y)
  ctx.lineTo(x + width - 18, y)
  ctx.lineTo(x + width, y + height / 2)
  ctx.lineTo(x + width - 18, y + height)
  ctx.lineTo(x + 18, y + height)
  ctx.lineTo(x, y + height / 2)
  ctx.closePath()
  ctx.fill()
}

/**
 * 绘制标签。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {string} text 文案。
 * @param {number} width 宽度。
 * @returns {void}
 */
function drawTag(ctx, x, y, text, width) {
  fillRoundRect(ctx, x, y, width, 34, 8, '#fff0df')
  drawCanvasText(ctx, text, x + width / 2, y + 5, {
    color: '#ee5a2d',
    size: 20,
    bold: true,
    align: 'center'
  })
}

/**
 * 绘制圆角按钮。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @param {string} text 文案。
 * @returns {void}
 */
function drawPill(ctx, x, y, width, height, text) {
  fillRoundRect(ctx, x, y, width, height, height / 2, '#fff0df')
  drawCanvasText(ctx, text, x + width / 2, y + 8, {
    color: '#ec5d2b',
    size: 20,
    bold: true,
    align: 'center'
  })
}

/**
 * 绘制实线。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x1 起点横坐标。
 * @param {number} y1 起点纵坐标。
 * @param {number} x2 终点横坐标。
 * @param {number} y2 终点纵坐标。
 * @param {string} color 颜色。
 * @returns {void}
 */
function drawSolidLine(ctx, x1, y1, x2, y2, color) {
  ctx.setStrokeStyle(color)
  ctx.setLineWidth(1)
  ctx.beginPath()
  ctx.moveTo(x1, y1)
  ctx.lineTo(x2, y2)
  ctx.stroke()
}

/**
 * 绘制横向虚线分隔。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x1 起点横坐标。
 * @param {number} y 纵坐标。
 * @param {number} x2 终点横坐标。
 * @returns {void}
 */
function drawDashedLine(ctx, x1, y, x2) {
  ctx.setStrokeStyle('#f0c7a3')
  ctx.setLineWidth(1)
  for (let x = x1; x < x2; x += 16) {
    ctx.beginPath()
    ctx.moveTo(x, y)
    ctx.lineTo(Math.min(x + 8, x2), y)
    ctx.stroke()
  }
}

/**
 * 绘制虚线矩形。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {number} width 宽度。
 * @param {number} height 高度。
 * @param {string} color 颜色。
 * @returns {void}
 */
function drawDashedRect(ctx, x, y, width, height, color) {
  ctx.setStrokeStyle(color)
  ctx.setLineWidth(1)
  for (let lineX = x; lineX < x + width; lineX += 14) {
    drawLineSegment(ctx, lineX, y, Math.min(lineX + 7, x + width), y)
    drawLineSegment(
      ctx,
      lineX,
      y + height,
      Math.min(lineX + 7, x + width),
      y + height
    )
  }
  for (let lineY = y; lineY < y + height; lineY += 14) {
    drawLineSegment(ctx, x, lineY, x, Math.min(lineY + 7, y + height))
    drawLineSegment(
      ctx,
      x + width,
      lineY,
      x + width,
      Math.min(lineY + 7, y + height)
    )
  }
}

/**
 * 绘制线段。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {number} x1 起点横坐标。
 * @param {number} y1 起点纵坐标。
 * @param {number} x2 终点横坐标。
 * @param {number} y2 终点纵坐标。
 * @returns {void}
 */
function drawLineSegment(ctx, x1, y1, x2, y2) {
  ctx.beginPath()
  ctx.moveTo(x1, y1)
  ctx.lineTo(x2, y2)
  ctx.stroke()
}

/**
 * 绘制 canvas 文字。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {string} text 文案。
 * @param {number} x 横坐标。
 * @param {number} y 纵坐标。
 * @param {Object} options 文本配置。
 * @returns {void}
 */
function drawCanvasText(ctx, text, x, y, options = {}) {
  ctx.setFillStyle(options.color || '#111111')
  ctx.setFontSize(options.size || 24)
  ctx.setTextAlign(options.align || 'left')
  ctx.setTextBaseline('top')
  if (typeof ctx.font !== 'undefined') {
    const weight = options.bold ? 'bold' : 'normal'
    ctx.font = `${weight} ${options.size || 24}px sans-serif`
  }
  ctx.fillText(String(text || ''), x, y)
  ctx.setTextAlign('left')
}

/**
 * 测量 canvas 文本宽度。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {string} text 文案。
 * @param {number} size 字号。
 * @returns {number} 文本宽度。
 */
function measureCanvasText(ctx, text, size) {
  ctx.setFontSize(size)
  if (typeof ctx.measureText === 'function') {
    return ctx.measureText(String(text || '')).width
  }

  return String(text || '').length * size
}

/**
 * 按宽度裁剪 canvas 文本为多行。
 *
 * @param {Object} ctx canvas 上下文。
 * @param {string} text 原文案。
 * @param {number} size 字号。
 * @param {number} maxWidth 最大宽度。
 * @param {number} maxLines 最大行数。
 * @returns {Array<string>} 文本行。
 */
function wrapCanvasText(ctx, text, size, maxWidth, maxLines) {
  const chars = String(text || '').split('')
  const lines = []
  let line = ''

  chars.forEach((char) => {
    const nextLine = `${line}${char}`
    if (measureCanvasText(ctx, nextLine, size) > maxWidth && line) {
      lines.push(line)
      line = char
      return
    }
    line = nextLine
  })

  if (line) {
    lines.push(line)
  }

  if (lines.length > maxLines) {
    const clipped = lines.slice(0, maxLines)
    let lastLine = clipped[maxLines - 1]
    while (
      lastLine &&
      measureCanvasText(ctx, `${lastLine}...`, size) > maxWidth
    ) {
      lastLine = lastLine.slice(0, -1)
    }
    clipped[maxLines - 1] = `${lastLine}...`
    return clipped
  }

  return lines
}

/**
 * 从请求异常对象中解析错误提示。
 *
 * @param {Object|string|Error} error 请求异常或错误响应。
 * @param {string} fallbackMessage 默认提示。
 * @returns {string} 错误提示。
 */
function resolveErrorMessage(error, fallbackMessage = '请求失败') {
  if (typeof error === 'string' && error.trim()) {
    return error.trim()
  }

  if (!error || typeof error !== 'object') {
    return fallbackMessage
  }

  if (error.msg) {
    return String(error.msg)
  }

  if (error.message) {
    return String(error.message)
  }

  if (error.errMsg) {
    return String(error.errMsg)
  }

  if (error.error && error.error.errMsg) {
    return String(error.error.errMsg)
  }

  return fallbackMessage
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

<style lang="scss" scoped>
.share-popup {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 72;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 44rpx 54rpx 52rpx;
  background: rgba(0, 0, 0, 0.72);
  box-sizing: border-box;
  animation: share-popup-mask-in 180ms ease-out both;
}

.share-popup__panel {
  position: relative;
  width: 626rpx;
  max-width: 100%;
  height: 940rpx;
  overflow: hidden;
  border-radius: 34rpx;
  box-shadow: 0 18rpx 48rpx rgba(0, 0, 0, 0.18);
  animation: share-popup-panel-in 220ms ease-out both;
}

.share-popup__poster {
  display: block;
  width: 100%;
  height: 100%;
}

.share-popup__actions {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 24rpx;
}

.share-popup__button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 244rpx;
  height: 76rpx;
  margin: 0 10rpx;
  padding: 0;
  border: 0;
  border-radius: 999rpx;
  box-sizing: border-box;
  font-size: 28rpx;
  line-height: 76rpx;
  font-weight: 700;
}

.share-popup__button::after {
  border: 0;
}

.share-popup__button--primary {
  background: var(--brand-primary, #f0582a);
  color: #ffffff;
  box-shadow: 0 12rpx 24rpx var(--brand-shadow, rgba(240, 88, 42, 0.24));
}

.share-popup__button--ghost {
  border: 2rpx solid rgba(255, 255, 255, 0.72);
  background: rgba(255, 255, 255, 0.16);
  color: #ffffff;
}

.share-popup__button--hover {
  opacity: 0.84;
}

.share-popup__loading {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.share-popup__loading-bg {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.share-popup__loading-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.share-popup__loading-title {
  color: #321706;
  font-size: 34rpx;
  line-height: 48rpx;
  font-weight: 800;
}

.share-popup__loading-text {
  margin-top: 14rpx;
  color: #9b4d19;
  font-size: 24rpx;
  line-height: 34rpx;
  max-width: 480rpx;
  text-align: center;
}

.share-popup__close {
  position: fixed;
  top: 72rpx;
  right: 30rpx;
  z-index: 73;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 76rpx;
  height: 76rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.24);
}

.share-popup__close--hover {
  opacity: 0.78;
}

.share-popup__close-icon {
  color: #ffffff;
  font-size: 42rpx;
  line-height: 48rpx;
}

.share-poster-canvas {
  position: absolute;
  left: -9999px;
  top: -9999px;
  width: 626px;
  height: 940px;
  opacity: 0;
  pointer-events: none;
}

/* #ifdef H5 */
.share-popup__close {
  top: 56rpx;
}

/* #endif */

@keyframes share-popup-mask-in {
  0% {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

@keyframes share-popup-panel-in {
  0% {
    opacity: 0;
    transform: scale(0.96);
  }

  100% {
    opacity: 1;
    transform: scale(1);
  }
}

.share-popup__retry {
  margin-top: 24rpx;
  padding: 12rpx 36rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #f0582a);
  color: var(--text-inverse, #ffffff);
  font-size: 28rpx;
  line-height: 40rpx;
}
</style>
