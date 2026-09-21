/**
 * DIY 页面渲染 composable（由 mixins/diyPage.js 迁移而来）。
 *
 * 使用方式：
 * 1. 页面中调用 `const { loadDiyPage, ... } = useDiyPage()`；
 * 2. 在 `onLoad` 或刷新动作中调用 `loadDiyPage()`；
 * 3. 通过 `diyComponents`、`diyGlobalConfig` 驱动 `DiyPage.vue` 渲染。
 */
import { nextTick, ref } from 'vue'
import { getDiyHomePage, getDiyPage } from '@/api/home'
import { normalizeDiyPagePayload } from '@/utils/diy_adapter'

const MIN_LOADING_VISIBLE_MS = 120

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

export function useDiyPage() {
  // 默认先进入加载态，避免首页首次渲染时闪出空态文案。
  const diyLoading = ref(true)
  const diyPageConfig = ref(null)
  const diyComponents = ref([])
  const diyGlobalConfig = ref({})
  const diyReachBottomToken = ref(0)

  async function loadDiyPage(pageType = 'home', params = {}) {
    const loadingStartedAt = Date.now()
    diyLoading.value = true
    await nextTick()

    const request = pageType === 'home'
      ? getDiyHomePage(params)
      : getDiyPage(params)
    const res = await request.catch((error) => {
      console.error('加载DIY页面失败:', error)
      return null
    })

    const loadingElapsed = Date.now() - loadingStartedAt
    if (loadingElapsed < MIN_LOADING_VISIBLE_MS) {
      await wait(MIN_LOADING_VISIBLE_MS - loadingElapsed)
    }

    if (!res || res.code !== 200 || !res.data) {
      diyLoading.value = false
      return false
    }

    const normalized = normalizeDiyPagePayload(res.data)
    diyPageConfig.value = normalized.page
    diyComponents.value = normalized.components
    diyGlobalConfig.value = normalized.globalConfig

    if (normalized.page?.page_title) {
      uni.setNavigationBarTitle({
        title: normalized.page.page_title
      })
    }

    if (normalized.globalConfig?.title_bar) {
      uni.setNavigationBarColor({
        frontColor: normalized.globalConfig.title_bar.text_color === '#ffffff' ? '#ffffff' : '#000000',
        backgroundColor: normalized.globalConfig.title_bar.bg_color || '#f6f6f6'
      })
    }

    diyLoading.value = false
    return true
  }

  function getDiyBackgroundStyle() {
    const background = diyGlobalConfig.value.background || {}
    const gradient = background.gradient || {}
    let style = 'min-height: 100vh;'

    if (background.type === 'image' && background.image) {
      style += `background-image: url(${background.image});`
      style += `background-size: ${background.image_mode === 'repeat' ? 'auto' : 'cover'};`
      style += 'background-position: center top;'
      style += `background-repeat: ${background.image_mode === 'repeat' ? 'repeat' : 'no-repeat'};`
      style += `background-color: ${background.color || '#f6f6f6'};`
    } else if (background.type === 'gradient' && Array.isArray(gradient.colors) && gradient.colors.length > 0) {
      style += `background: linear-gradient(${gradient.angle || 180}deg, ${gradient.colors.join(', ')});`
    } else {
      style += `background-color: ${background.color || '#f6f6f6'};`
    }

    style += `padding-bottom: ${Math.max(Number(diyGlobalConfig.value.page_padding || 24), 24) + 120}rpx;`
    return style
  }

  return {
    diyLoading,
    diyPageConfig,
    diyComponents,
    diyGlobalConfig,
    diyReachBottomToken,
    loadDiyPage,
    getDiyBackgroundStyle
  }
}
