/**
 * DIY 页面渲染混入。
 *
 * 使用方式：
 * 1. 页面引入该 mixin；
 * 2. 在 `onLoad` 或刷新动作中调用 `loadDiyPage()`；
 * 3. 通过 `diyComponents`、`diyGlobalConfig` 驱动 `DiyPage.vue` 渲染。
 */
import { getDiyHomePage, getDiyPage } from '@/api/home'
import { normalizeDiyPagePayload } from '@/utils/diy_adapter'

export default {
  data() {
    return {
      diyLoading: false,
      diyPageConfig: null,
      diyComponents: [],
      diyGlobalConfig: {},
      diyReachBottomToken: 0
    }
  },
  methods: {
    async loadDiyPage(pageType = 'home', params = {}) {
      this.diyLoading = true

      const request = pageType === 'home'
        ? getDiyHomePage(params)
        : getDiyPage(params)
      const res = await request.catch((error) => {
        console.error('加载DIY页面失败:', error)
        return null
      })

      if (!res || res.code !== 200 || !res.data) {
        this.diyLoading = false
        return false
      }

      const normalized = normalizeDiyPagePayload(res.data)
      this.diyPageConfig = normalized.page
      this.diyComponents = normalized.components
      this.diyGlobalConfig = normalized.globalConfig

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

      this.diyLoading = false
      return true
    },
    getDiyBackgroundStyle() {
      const background = this.diyGlobalConfig.background || {}
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

      style += `padding-bottom: ${Math.max(Number(this.diyGlobalConfig.page_padding || 24), 24) + 120}rpx;`
      return style
    }
  }
}
