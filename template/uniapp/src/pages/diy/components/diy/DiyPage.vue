<template>
  <view class="diy-page" :style="pageStyleVars">
    <view
      v-for="group in componentGroups"
      :key="group.key"
      class="diy-page__group"
      :class="group.groupClass"
    >
      <view v-if="group.showBlurBackground" class="diy-page__header-blur" aria-hidden="true">
        <image
          v-for="item in group.blurImages"
          :key="item.key"
          class="diy-page__header-blur-image"
          :class="item.activeClass"
          :src="item.url"
          mode="aspectFill"
        />
        <view class="diy-page__header-blur-overlay" :style="group.blurOverlayStyle"></view>
      </view>

      <view
        v-for="component in group.components"
        :key="component.bannerStateKey"
        class="diy-page__section"
        :class="component.sectionClass"
        :style="component.sectionStyle"
      >
        <DiyWrapper :style-config="component.styleConfig">
          <DiyRenderer
            :component-id="component.id"
            :component-type="component.type"
            :view-name="component.viewName"
            :config="component.componentConfig"
            :data="component.componentData"
            :is-banner-adjacent="component.isBannerAdjacent && group.showBlurBackground"
            :reach-bottom-token="component.isReachBottomTarget ? reachBottomToken : 0"
            @banner-change="handleBannerChange(component.bannerStateKey, $event)"
            @visibility-change="handleComponentVisibilityChange(component.bannerStateKey, $event)"
          />
        </DiyWrapper>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { normalizeDiyComponentType, toRpxUnit } from '@/utils/diy_adapter'
import DiyRenderer from './DiyRenderer.vue'
import DiyWrapper from './DiyWrapper.vue'

const DIY_COMPONENT_NAME_MAP = {
  search: 'DiySearch',
  banner: 'DiyBanner',
  navigation: 'DiyNavigation',
  notice: 'DiyNotice',
  image: 'DiyImageCube',
  image_cube: 'DiyImageCube',
  live: 'DiyLive',
  content: 'DiyContent',
  special: 'DiySpecial',
  product: 'DiyProduct',
  seckill: 'DiySeckill',
  coupon: 'DiyCoupon',
  hot_zone: 'DiyHotZone',
  hotzone: 'DiyHotZone',
  title: 'DiyTitle',
  rich_text: 'DiyRichText',
  richtext: 'DiyRichText',
  blank: 'DiyBlank'
}

const LOAD_MORE_COMPONENT_TYPES = ['content', 'special', 'product']

function resolveComponentViewName(type = '') {
  const normalizedType = normalizeDiyComponentType(type)
  return DIY_COMPONENT_NAME_MAP[normalizedType] || 'DiyBlank'
}

defineOptions({
  name: 'DiyPage'
})

const props = defineProps({
  components: {
    type: Array,
    default: () => []
  },
  globalConfig: {
    type: Object,
    default: () => ({})
  },
  reachBottomToken: {
    type: Number,
    default: 0
  }
})

const bannerActiveIndexes = ref({})
const hiddenComponentKeys = ref({})

watch(() => props.components, () => {
  hiddenComponentKeys.value = {}
}, {
  deep: true
})

const normalizedComponents = computed(() => {
  if (!Array.isArray(props.components)) {
    return []
  }

  const preparedComponents = props.components.map((component, index) => {
    const type = getComponentType(component)
    if (!type) {
      return null
    }

    const componentConfig = normalizeComponentConfig(component)
    const componentData = normalizeComponentData(component)
    return {
      component,
      type,
      componentConfig,
      componentData,
      stateKey: getComponentStateKey(component, index)
    }
  }).filter((item) => item && shouldRenderComponent(item))

  const renderComponents = preparedComponents.map((item, index) => ({
    ...item,
    componentConfig: normalizeLoadMoreConfig(
      item.type,
      item.componentConfig,
      index === preparedComponents.length - 1
    )
  }))

  const reachBottomTargetKey = getReachBottomTargetKey(renderComponents)

  return renderComponents.map((item, index) => {
    const { component, type, componentConfig, componentData } = item
    const previousItem = index > 0 ? preparedComponents[index - 1] : null
    const nextItem = index < preparedComponents.length - 1 ? preparedComponents[index + 1] : null
    const previousType = previousItem ? previousItem.type : ''
    const nextType = nextItem ? nextItem.type : ''
    const isBannerAdjacent = type === 'search' && (previousType === 'banner' || nextType === 'banner')
    const adjacentBannerItem = type === 'search'
      ? (nextType === 'banner' ? nextItem : (previousType === 'banner' ? previousItem : null))
      : null
    const adjacentBannerPosition = adjacentBannerItem === nextItem ? 'after' : (adjacentBannerItem ? 'before' : '')
    const adjacentBannerStateKey = adjacentBannerItem ? adjacentBannerItem.stateKey : ''
    const adjacentBannerImages = getBannerImages(adjacentBannerItem)
    const rawAdjacentBannerIndex = Number(bannerActiveIndexes.value[adjacentBannerStateKey] || 0)
    const adjacentBannerIndex = rawAdjacentBannerIndex >= 0 && rawAdjacentBannerIndex < adjacentBannerImages.length
      ? rawAdjacentBannerIndex
      : 0
    const sectionClassList = [`type-${type}`]
    const bannerStateKey = item.stateKey

    if (index === 0) {
      sectionClassList.push('is-first')
    }

    if (index === preparedComponents.length - 1) {
      sectionClassList.push('is-last')
    }

    if (type === 'search' || type === 'banner') {
      sectionClassList.push('is-hero')
    }

    if (isBannerAdjacent) {
      sectionClassList.push('is-banner-adjacent')
    }

    if (type === 'title') {
      sectionClassList.push('is-title')
    }

    return {
      ...component,
      type,
      component_type: type,
      viewName: resolveComponentViewName(type),
      sectionClass: sectionClassList.join(' '),
      sectionStyle: getSectionStyle(type, index === preparedComponents.length - 1),
      isBannerAdjacent,
      adjacentBannerImages,
      adjacentBannerIndex,
      bannerAdjacentPosition: adjacentBannerPosition,
      bannerStateKey,
      isReachBottomTarget: bannerStateKey === reachBottomTargetKey,
      componentConfig,
      componentData,
      styleConfig: componentConfig.style_config || null
    }
  })
})

const componentGroups = computed(() => {
  const groups = []
  let index = 0

  while (index < normalizedComponents.value.length) {
    const current = normalizedComponents.value[index]
    const next = index < normalizedComponents.value.length - 1
      ? normalizedComponents.value[index + 1]
      : null
    const searchBeforeBanner = current.type === 'search' && next && next.type === 'banner' && current.bannerAdjacentPosition === 'after'
    const bannerBeforeSearch = current.type === 'banner' && next && next.type === 'search' && next.bannerAdjacentPosition === 'before'

    if (searchBeforeBanner || bannerBeforeSearch) {
      groups.push(createHeaderGroup([current, next], searchBeforeBanner ? current : next))
      index += 2
      continue
    }

    groups.push({
      key: `single:${current.bannerStateKey}`,
      groupClass: '',
      showBlurBackground: false,
      blurImages: [],
      blurOverlayStyle: '',
      components: [current]
    })
    index += 1
  }

  return groups
})

const pageStyleVars = computed(() => {
  return {}
})

/**
 * 解析组件类型。
 *
 * @param {Object} component DIY 组件数据。
 * @returns {string} 组件类型标识。
 */
function getComponentType(component = {}) {
  return normalizeDiyComponentType(component.component_type || component.type || '')
}

/**
 * 规范化组件配置对象。
 *
 * @param {Object} component DIY 组件数据。
 * @returns {Object} 可安全传递给子组件的配置对象。
 */
function normalizeComponentConfig(component = {}) {
  const config = component.config
  return config && typeof config === 'object' ? config : {}
}

/**
 * 规范化组件数据，兼容旧数组结构和分类标签对象结构。
 *
 * @param {Object} component DIY 组件数据。
 * @returns {Array|Object} 可安全传递给子组件的数据。
 */
function normalizeComponentData(component = {}) {
  if (Array.isArray(component.data)) {
    return component.data
  }

  if (component.data && typeof component.data === 'object') {
    return component.data
  }

  if (getComponentType(component) === 'special') {
    const config = normalizeComponentConfig(component)
    const fallbackData = config.special_data || config.items || config.list
    return Array.isArray(fallbackData) ? fallbackData : []
  }

  return []
}

function shouldRenderComponent(item = {}) {
  if (hiddenComponentKeys.value[item.stateKey]) {
    return false
  }

  if (!isDataDrivenComponent(item.type)) {
    return true
  }

  if (Number((item.component && item.component.id) || 0) > 0) {
    return true
  }

  return hasRenderableComponentData(item.componentData)
}

/**
 * 判断组件是否依赖业务列表数据渲染。
 *
 * @param {string} type 组件类型。
 * @returns {boolean} 数据型组件返回 true。
 */
function isDataDrivenComponent(type = '') {
  return ['content', 'special', 'product', 'live', 'coupon'].indexOf(type) > -1
}

/**
 * 判断组件数据中是否存在可展示条目。
 *
 * @param {Array|Object} data 组件数据。
 * @returns {boolean} 有可展示条目返回 true。
 */
function hasRenderableComponentData(data = []) {
  if (Array.isArray(data)) {
    return data.length > 0
  }

  if (data && typeof data === 'object' && Array.isArray(data.list)) {
    return data.list.length > 0
  }

  return false
}

/**
 * 获取当前页面最后一个开启触底加载的组件状态键。
 *
 * @param {Object[]} components 已规范化的可渲染组件列表。
 * @returns {string} 触底加载目标组件的状态键。
 */
function getReachBottomTargetKey(components = []) {
  for (let index = components.length - 1; index >= 0; index -= 1) {
    if (isReachBottomLoadMoreComponent(components[index])) {
      return components[index].stateKey
    }
  }

  return ''
}

/**
 * 按组件位置修正触底加载配置，避免历史配置导致中间组件展示加载提示。
 *
 * @param {string} type 组件类型。
 * @param {Object} config 组件配置。
 * @param {boolean} isLast 当前组件是否为页面最后一个可渲染组件。
 * @returns {Object} 修正后的组件配置。
 */
function normalizeLoadMoreConfig(type = '', config = {}, isLast = false) {
  if (LOAD_MORE_COMPONENT_TYPES.indexOf(type) === -1) {
    return config
  }

  const loadMore = isLast && !isCategoryTabsLoadMoreDisabled(type, config)
  if (config.load_more === loadMore) {
    return config
  }

  return {
    ...config,
    load_more: loadMore
  }
}

function isCategoryTabsLoadMoreDisabled(type = '', config = {}) {
  if (type === 'product') {
    return false
  }
  return config.display_style === 'category_tabs' || config.style === 'category_tabs' || config.data_source === 'category_tabs'
}

/**
 * 判断组件是否可响应页面触底加载。
 *
 * @param {Object} item 已规范化的组件项。
 * @returns {boolean} 可响应返回 true。
 */
function isReachBottomLoadMoreComponent(item = {}) {
  const config = item.componentConfig && typeof item.componentConfig === 'object' ? item.componentConfig : {}
  return LOAD_MORE_COMPONENT_TYPES.indexOf(item.type) > -1 &&
    (config.load_more === true || Number(config.load_more || 0) === 1)
}

/**
 * 判断当前组件是否命中目标类型。
 *
 * @param {Object} component DIY 组件数据。
 * @param {string} type 目标类型。
 * @returns {boolean} 命中返回 true。
 */
function isComponentType(component = {}, type = '') {
  return getComponentType(component) === type
}

/**
 * 获取组件在当前 DIY 页面中的状态键。
 *
 * @param {Object} component DIY 组件数据。
 * @param {number} index 组件渲染位置。
 * @returns {string} 可用于记录轮播状态的稳定键。
 */
function getComponentStateKey(component = {}, index = 0) {
  return component.id ? `id:${component.id}` : `index:${index}`
}

/**
 * 提取 Banner 组件中可用于相邻背景的图片地址。
 *
 * @param {Object|null} item 已规范化的组件项。
 * @returns {string[]} 有效图片地址列表。
 */
function getBannerImages(item = null) {
  if (!item || item.type !== 'banner') return []

  const dataItems = Array.isArray(item.componentData) ? item.componentData : []
  const configItems = item.componentConfig && Array.isArray(item.componentConfig.items)
    ? item.componentConfig.items
    : []
  const source = dataItems.length > 0 ? dataItems : configItems

  return source.map((banner) => String((banner && (banner.image || banner.img)) || '').trim()).filter(Boolean)
}

/**
 * 同步 Banner 当前页，用于驱动相邻搜索组件的模糊背景。
 *
 * @param {string} stateKey Banner 状态键。
 * @param {number} index 当前轮播索引。
 * @returns {void}
 */
function handleBannerChange(stateKey = '', index = 0) {
  if (!stateKey) return
  bannerActiveIndexes.value[stateKey] = Math.max(Number(index || 0), 0)
}

/**
 * 根据子渲染器反馈隐藏或恢复整个 DIY 模块。
 *
 * @param {string} stateKey 组件状态键。
 * @param {boolean} visible 是否展示模块。
 * @returns {void}
 */
function handleComponentVisibilityChange(stateKey = '', visible = true) {
  if (!stateKey) return

  const nextHiddenKeys = { ...hiddenComponentKeys.value }
  if (visible) {
    delete nextHiddenKeys[stateKey]
  } else {
    nextHiddenKeys[stateKey] = true
  }
  hiddenComponentKeys.value = nextHiddenKeys
}

/**
 * 创建搜索与 Banner 共用的全宽头部组合。
 *
 * @param {Object[]} components 相邻的搜索和 Banner 组件。
 * @param {Object} searchComponent 已规范化的搜索组件。
 * @returns {Object} 可直接用于模板渲染的头部组合数据。
 */
function createHeaderGroup(components = [], searchComponent = {}) {
  const images = Array.isArray(searchComponent.adjacentBannerImages)
    ? searchComponent.adjacentBannerImages
    : []
  const activeIndex = Number(searchComponent.adjacentBannerIndex || 0)
  const isBannerBefore = searchComponent.bannerAdjacentPosition === 'before'
  const positionClass = isBannerBefore
    ? 'is-banner-before'
    : 'is-banner-after'
  const pageBackgroundColor = getPageBackgroundColor()
  const blurOverlayStyle = isBannerBefore
    ? `background-image: linear-gradient(180deg, ${pageBackgroundColor} 0%, transparent 52%, transparent 100%);`
    : `background-image: linear-gradient(180deg, transparent 0%, transparent 48%, ${pageBackgroundColor} 100%);`
  const headerComponents = components.map((component) => ({
    ...component,
    styleConfig: getTransparentHeaderStyleConfig(component.styleConfig)
  }))

  return {
    key: `header:${components.map((component) => component.bannerStateKey).join(':')}`,
    groupClass: `is-blurred-header ${positionClass}`,
    showBlurBackground: images.length > 0,
    blurOverlayStyle,
    blurImages: images.map((url, index) => ({
      key: `${index}:${url}`,
      url,
      activeClass: index === activeIndex ? 'is-active' : ''
    })),
    components: headerComponents
  }
}

/**
 * 保留组件布局配置，同时移除会遮挡头部模糊图的背景层。
 *
 * @param {Object|null} styleConfig 组件样式配置。
 * @returns {Object} 背景透明的头部组件样式配置。
 */
function getTransparentHeaderStyleConfig(styleConfig = null) {
  return {
    ...(styleConfig && typeof styleConfig === 'object' ? styleConfig : {}),
    bg_color_start: '',
    bg_color_end: '',
    bottom_bg_color: ''
  }
}

/**
 * 获取头部模糊背景渐隐时使用的页面底色。
 *
 * @returns {string} DIY 页面背景色。
 */
function getPageBackgroundColor() {
  const background = props.globalConfig && props.globalConfig.background
  return background && background.color ? background.color : '#f6f6f6'
}

function getSectionStyle(type = '', isLast = false) {
  const style = {
    marginBottom: isLast ? '0rpx' : toRpxUnit(props.globalConfig.section_gap, 0)
  }

  if (type === 'search') {
    style.zIndex = 3
  } else if (type === 'banner') {
    style.zIndex = 1
  }

  return style
}
</script>

<style lang="scss" scoped>
.diy-page {
  width: auto;
}

.diy-page__group {
  position: relative;
}

.diy-page__group.is-blurred-header {
  overflow: hidden;
}

.diy-page__header-blur {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 0;
  overflow: hidden;
  background: rgba(246, 246, 246, 0.72);
}

.diy-page__header-blur-image {
  position: absolute;
  top: -15%;
  left: -15%;
  width: 130%;
  height: 130%;
  opacity: 0;
  transform: scale(1.2);
  transition: opacity 0.45s ease;
}

/* #ifdef H5 */
.diy-page__header-blur-image {
  -webkit-filter: blur(30rpx);
  filter: blur(30rpx);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.diy-page__header-blur-image {
  filter: blur(30rpx);
}
/* #endif */

.diy-page__header-blur-image.is-active {
  opacity: 0.78;
}

.diy-page__header-blur-overlay {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: transparent;
}

.diy-page__section {
  position: relative;

  &.type-search {
    z-index: 3;
  }

  &.type-banner {
    z-index: 1;
  }
}
</style>
