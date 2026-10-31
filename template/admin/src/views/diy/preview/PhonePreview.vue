<template>
  <div class="phone-preview" :class="{ 'phone-preview--editable': selectable }" :style="previewThemeStyle">
    <div class="phone-frame">
      <!-- 手机状态栏 -->
      <div class="phone-status-bar">
        <span class="status-time">{{ currentTime }}</span>
        <div class="status-icons">
          <LegacyIcon name="el-icon-signal" />
          <LegacyIcon name="el-icon-wifi" />
          <LegacyIcon name="el-icon-battery-full" />
        </div>
      </div>

      <div v-if="false" class="phone-header" :style="titleBarStyle">
        <LegacyIcon name="el-icon-arrow-left" />
        <span class="title">{{ pageTitle }}</span>
        <span class="placeholder" />
      </div>

      <div class="phone-body" :style="bodyScrollStyle" @click.self="handleBodyClick">
        <div ref="phoneContentRef" class="phone-content" :style="bodyStyle" @click.self="handleBodyClick">
          <div
            v-for="layer in headerBlurLayers"
            :key="layer.key"
            class="preview-header-blur"
            :class="layer.className"
            :style="layer.style"
            aria-hidden="true"
          >
            <img
              v-for="item in layer.images"
              :key="item.key"
              :src="item.url"
              :class="item.activeClass"
              alt=""
              class="preview-header-blur__image"
            >
            <div class="preview-header-blur__overlay" :style="layer.overlayStyle" />
          </div>

          <div v-if="modelValue.length === 0" class="empty-tip">
            {{ emptyText }}
          </div>
          <draggable
            v-else
            v-model="componentListModel"
            :item-key="getComponentItemKey"
            :animation="200"
            ghost-class="ghost"
            handle=".preview-component"
            :disabled="!sortable"
            class="phone-component-list"
            @end="handleDragEnd"
          >
            <template #item="{ element, index }">
              <div
                v-for="component in [getPreviewComponentAt(index, element)]"
                :key="component.id || component.stateKey"
                :ref="el => setComponentRef(component.previewRef, el)"
                class="preview-component"
                :class="[
                  `preview-component--${component.component_type}`,
                  component.headerBlurClass,
                  {
                    'is-selected': selectable && selectedId === component.id,
                    'is-hidden': isComponentHidden(component)
                  }
                ]"
                @click.stop="handleComponentClick(component.sourceComponent || component)"
              >
                <div
                  v-if="selectable"
                  class="component-label"
                  :class="{ 'component-label--hidden': isComponentHidden(component) }"
                  @mousedown.stop
                >
                  <span class="component-label__text">{{ getComponentName(component.component_type) }}</span>
                </div>
                <div
                  v-if="selectable && selectedId === component.id"
                  class="component-side-actions"
                  @mousedown.stop
                >
                  <el-tooltip
                    :content="isComponentHidden(component) ? '显示组件' : '隐藏组件'"
                    placement="right"
                    :open-delay="200"
                  >
                    <button
                      type="button"
                      class="component-action"
                      :class="{ 'is-active': isComponentHidden(component) }"
                      :aria-label="isComponentHidden(component) ? '显示组件' : '隐藏组件'"
                      @click.stop="handleToggleVisible(component.sourceComponent || component)"
                    >
                      <LegacyIcon name="el-icon-view" />
                    </button>
                  </el-tooltip>
                  <el-tooltip content="上移组件" placement="right" :open-delay="200">
                    <button
                      type="button"
                      class="component-action"
                      :class="{ 'is-disabled': isFirstComponent(component) }"
                      :disabled="isFirstComponent(component)"
                      aria-label="上移组件"
                      @click.stop="handleMoveUp(component.sourceComponent || component)"
                    >
                      <LegacyIcon name="el-icon-top" />
                    </button>
                  </el-tooltip>
                  <el-tooltip content="下移组件" placement="right" :open-delay="200">
                    <button
                      type="button"
                      class="component-action"
                      :class="{ 'is-disabled': isLastComponent(component) }"
                      :disabled="isLastComponent(component)"
                      aria-label="下移组件"
                      @click.stop="handleMoveDown(component.sourceComponent || component)"
                    >
                      <LegacyIcon name="el-icon-bottom" />
                    </button>
                  </el-tooltip>
                  <el-tooltip content="复制组件" placement="right" :open-delay="200">
                    <button
                      type="button"
                      class="component-action"
                      aria-label="复制组件"
                      @click.stop="handleCopy(component.sourceComponent || component)"
                    >
                      <LegacyIcon name="el-icon-copy-document" />
                    </button>
                  </el-tooltip>
                  <el-tooltip content="删除组件" placement="right" :open-delay="200">
                    <button
                      type="button"
                      class="component-action component-action--danger"
                      aria-label="删除组件"
                      @click.stop="handleRemove(component.sourceComponent || component)"
                    >
                      <LegacyIcon name="el-icon-delete" />
                    </button>
                  </el-tooltip>
                </div>
                <div
                  class="component-preview"
                  :style="getOuterStyle(component.renderData)"
                >
                  <div :style="getInnerStyle(component.renderData)">
                    <!-- 真实渲染 Admin mobilePage 组件（使用 props.config） -->
                    <component
                      :is="mobilePageComponents[component.component_type]"
                      v-if="mobilePageComponents[component.component_type]"
                      :key="component.component_type + '_' + component.id"
                      :config="component.renderData"
                      @change="handlePreviewComponentChange(component, $event)"
                      @resize="scheduleHeaderBlurMeasure"
                    />
                    <!-- 不支持的组件类型显示占位 -->
                    <div v-else class="preview-placeholder">
                      {{ getComponentName(component.component_type) }}
                    </div>
                    <div v-if="isComponentHidden(component)" class="component-hidden-mask">
                      已隐藏
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </draggable>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import draggable from 'vuedraggable'

const LOAD_MORE_COMPONENT_TYPES = ['content', 'special', 'product']
const DEFAULT_THEME_CONFIG = {
  primary_color: '#ea580c',
  secondary_color: '#fb923c',
  gradient_color: 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'
}

defineOptions({
  name: 'PhonePreview'
})

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  globalConfig: {
    type: Object,
    default: () => ({})
  },
  selectable: {
    type: Boolean,
    default: false
  },
  sortable: {
    type: Boolean,
    default: true
  },
  selectedId: {
    type: [Number, String],
    default: null
  },
  emptyText: {
    type: String,
    default: '暂无组件，请从左侧添加'
  },
  bodyHeight: {
    type: [Number, String],
    default: 780
  },
  mobilePageComponents: {
    type: Object,
    default: () => ({})
  },
  themeConfig: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['select', 'deselect', 'remove', 'toggle-visible', 'move-up', 'move-down', 'copy', 'update:modelValue'])
const phoneContentRef = ref(null)
const timer = ref(null)
const currentTime = ref('9:41')
const bannerPreviewIndexes = reactive({})
const headerBlurRects = reactive({})
const headerBlurMeasurePending = ref(false)
const headerBlurMeasureFrame = ref(null)
const componentRefs = reactive({})

function cloneComponentList(list) {
  return Array.isArray(list) ? list.map((component, index) => createPreviewComponent(component, index)) : []
}

const componentListModel = computed({
  get() {
    return props.modelValue
  },
  set(val) {
    emit('update:modelValue', val)
  }
})

const previewComponents = computed(() => {
  const list = cloneComponentList(props.modelValue)
  let index = 0

  while (index < list.length - 1) {
    const current = list[index]
    const next = list[index + 1]
    const searchBeforeBanner = current.component_type === 'search' && next.component_type === 'banner'
    const bannerBeforeSearch = current.component_type === 'banner' && next.component_type === 'search'

    if (searchBeforeBanner || bannerBeforeSearch) {
      const banner = searchBeforeBanner ? next : current
      const search = searchBeforeBanner ? current : next
      const images = getBannerImages(banner)

      if (images.length > 0) {
        current.headerBlurClass = 'preview-component--in-header-blur'
        next.headerBlurClass = 'preview-component--in-header-blur'
        current.renderData = getHeaderComponentData(current.renderData, current === search)
        next.renderData = getHeaderComponentData(next.renderData, next === search)
      }

      index += 2
      continue
    }

    index += 1
  }

  return list
})

function getPreviewComponentAt(index, fallbackComponent) {
  return previewComponents.value[index] || createPreviewComponent(fallbackComponent, index)
}

const headerBlurLayers = computed(() => getHeaderBlurPairs().map((pair) => {
  const rect = headerBlurRects[pair.key] || getEstimatedHeaderBlurRect(pair.startIndex, [pair.current, pair.next])
  const activeIndex = getActiveBannerIndex(pair.banner, pair.images.length)
  return createHeaderBlur(pair.images, activeIndex, rect.top, rect.height, pair.position, pair.key)
}))

const headerBlurSignature = computed(() => getHeaderBlurPairs().map((pair) => {
  return [
    pair.key,
    pair.position,
    pair.images.join(','),
    getHeaderMeasureSignature(pair.current),
    getHeaderMeasureSignature(pair.next)
  ].join('|')
}).join('||'))

const bodyStyle = computed(() => {
  const config = props.globalConfig || {}
  const bg = config.background || {}
  let background = '#f5f5f5'

  if (bg.type === 'color' && bg.color) {
    background = bg.color
  } else if (bg.type === 'gradient' && bg.gradient && bg.gradient.colors) {
    const colors = bg.gradient.colors
    background = `linear-gradient(${bg.gradient.angle || 180}deg, ${colors.join(', ')})`
  } else if (bg.type === 'image' && bg.image) {
    background = `url(${bg.image}) center/cover`
  }

  const height = typeof props.bodyHeight === 'number' ? `${props.bodyHeight}px` : props.bodyHeight

  return {
    background,
    height
  }
})

const bodyScrollStyle = computed(() => {
  const height = typeof props.bodyHeight === 'number' ? `${props.bodyHeight}px` : props.bodyHeight
  return { height }
})

const titleBarStyle = computed(() => {
  const tb = props.globalConfig && props.globalConfig.title_bar ? props.globalConfig.title_bar : {}
  if (!tb.show) return {}
  return {
    background: tb.bg_color || '#ffffff',
    color: tb.text_color || '#333'
  }
})

const pageTitle = computed(() => (props.globalConfig && props.globalConfig.title_bar && props.globalConfig.title_bar.title) || '首页')
const previewThemeStyle = computed(() => buildThemeVariables(props.themeConfig))

function updateTime() {
  const now = new Date()
  currentTime.value = `${now.getHours()}:${String(now.getMinutes()).padStart(2, '0')}`
}

function getComponentName(type) {
  const names = {
    search: '搜索框',
    banner: '轮播图',
    navigation: '导航',
    notice: '公告',
    image_cube: '图片魔方',
    live: '直播',
    content: '内容',
    special: '专题',
    product: '商品',
    seckill: '秒杀',
    coupon: '优惠券',
    hot_zone: '热区',
    title: '标题',
    rich_text: '富文本',
    blank: '空白'
  }
  return names[type] || type
}

function createPreviewComponent(component, index) {
  const stateKey = getComponentStateKey(component, index)
  return {
    ...component,
    sourceComponent: component,
    renderData: normalizePreviewRenderData(component, index),
    stateKey,
    previewRef: getComponentRefName(stateKey),
    headerBlurClass: ''
  }
}

function normalizePreviewRenderData(component = {}, index = 0) {
  const type = component.component_type || ''
  const data = component.component_data && typeof component.component_data === 'object'
    ? component.component_data
    : {}

  if (LOAD_MORE_COMPONENT_TYPES.indexOf(type) === -1) {
    return data
  }

  return {
    ...data,
    load_more: shouldEnablePreviewLoadMore(type, data, index)
  }
}

function shouldEnablePreviewLoadMore(type = '', data = {}, index = 0) {
  if (index !== props.modelValue.length - 1) {
    return false
  }
  if (type === 'product') {
    return true
  }
  return data.display_style !== 'category_tabs' && data.style !== 'category_tabs' && data.data_source !== 'category_tabs'
}

function getComponentStateKey(component, index = 0) {
  return component && component.id ? `id:${component.id}` : `index:${index}`
}

function getComponentItemKey(component) {
  return component && (component.id || component._id || component.key || component.stateKey || component.component_type)
}

function getComponentRefName(stateKey) {
  return `preview_component_${String(stateKey).replace(/[^a-zA-Z0-9_]/g, '_')}`
}

function getHeaderPairKey(current, next) {
  return `${current.stateKey}_${next.stateKey}`
}

function getHeaderBlurPairs() {
  const list = Array.isArray(props.modelValue)
    ? props.modelValue.map((component, index) => createPreviewComponent(component, index))
    : []
  const pairs = []
  let index = 0

  while (index < list.length - 1) {
    const current = list[index]
    const next = list[index + 1]
    const searchBeforeBanner = current.component_type === 'search' && next.component_type === 'banner'
    const bannerBeforeSearch = current.component_type === 'banner' && next.component_type === 'search'

    if (searchBeforeBanner || bannerBeforeSearch) {
      const banner = searchBeforeBanner ? next : current
      const images = getBannerImages(banner)

      if (images.length > 0) {
        pairs.push({
          key: getHeaderPairKey(current, next),
          current,
          next,
          banner,
          images,
          position: searchBeforeBanner ? 'after' : 'before',
          startIndex: index
        })
      }

      index += 2
      continue
    }

    index += 1
  }

  return pairs
}

function getHeaderMeasureSignature(component) {
  const data = component && component.renderData
    ? component.renderData
    : ((component && component.component_data) || {})
  const sc = data.style_config || {}

  return [
    component.stateKey,
    component.component_type,
    sc.page_margin_top || 0,
    sc.margin_top || 0,
    sc.margin_bottom || 0,
    sc.padding_lr || 0,
    sc.padding_x || 0,
    getBoxConfigSignature(data.marginConfig),
    getBoxConfigSignature(data.paddingConfig)
  ].join(',')
}

function getBoxConfigSignature(config) {
  const safeConfig = config || {}
  const valList = Array.isArray(safeConfig.valList) ? safeConfig.valList : []
  return [
    safeConfig.isAll === true ? 1 : 0,
    safeConfig.val || 0,
    valList.map((item) => (item && item.val) || 0).join(':')
  ].join(':')
}

function getBannerImages(component) {
  const data = component && component.renderData
    ? component.renderData
    : ((component && component.component_data) || {})
  const list = data.swiperConfig && Array.isArray(data.swiperConfig.list)
    ? data.swiperConfig.list
    : (Array.isArray(data.items) ? data.items : [])

  return list.map((item) => String((item && (item.img || item.image)) || '').trim()).filter(Boolean)
}

function getActiveBannerIndex(component, imageCount) {
  const activeIndex = Number(bannerPreviewIndexes[component.stateKey] || 0)
  return activeIndex >= 0 && activeIndex < imageCount ? activeIndex : 0
}

function getHeaderBlurHeight(components) {
  return components.reduce((height, component) => height + getEstimatedComponentHeight(component), 0)
}

function getEstimatedHeaderBlurRect(startIndex, components) {
  const list = Array.isArray(props.modelValue)
    ? props.modelValue.map((component, index) => createPreviewComponent(component, index))
    : []
  const top = list
    .slice(0, startIndex)
    .reduce((height, component) => height + getEstimatedComponentHeight(component), 0)

  return {
    top,
    height: getHeaderBlurHeight(components)
  }
}

function getEstimatedComponentHeight(component) {
  const data = component && component.renderData ? component.renderData : {}
  const sc = data.style_config || {}
  const outerTop = Number(sc.page_margin_top || 0) + Number(sc.margin_top || 0)
  const outerBottom = Number(sc.margin_bottom || 0)
  const paddingY = getPaddingY(data)

  if (component.component_type === 'search') {
    return outerTop + outerBottom + paddingY + 60
  }

  if (component.component_type === 'banner') {
    return outerTop + outerBottom + paddingY + 170
  }

  return outerTop + outerBottom + paddingY + 60
}

function getPaddingY(componentData) {
  const sc = componentData && componentData.style_config ? componentData.style_config : {}
  const stylePadding = Number(sc.padding_x || 0) > 0 ? 0 : 0
  const paddingConfig = componentData && componentData.paddingConfig ? componentData.paddingConfig : {}
  const valList = Array.isArray(paddingConfig.valList) ? paddingConfig.valList : []

  if (paddingConfig.isAll === true && valList.length >= 3) {
    return Number(valList[0].val || 0) + Number(valList[2].val || 0) + stylePadding
  }

  if (paddingConfig.val !== undefined) {
    return Number(paddingConfig.val || 0) * 2 + stylePadding
  }

  return stylePadding
}

function createHeaderBlur(images, activeIndex, top, height, position, key) {
  const pageColor = getPageBackgroundColor()
  const overlayStyle = position === 'before'
    ? { backgroundImage: `linear-gradient(180deg, ${pageColor} 0%, transparent 52%, transparent 100%)` }
    : { backgroundImage: `linear-gradient(180deg, transparent 0%, transparent 48%, ${pageColor} 100%)` }

  return {
    key,
    className: position === 'before' ? 'is-banner-before' : 'is-banner-after',
    style: {
      top: `${Math.max(Number(top || 0), 0)}px`,
      height: `${Math.max(Number(height || 0), 0)}px`
    },
    overlayStyle,
    images: images.map((url, index) => ({
      key: `${index}:${url}`,
      url,
      activeClass: index === activeIndex ? 'is-active' : ''
    }))
  }
}

function getHeaderComponentData(componentData, isSearch = false) {
  const styleConfig = {
    ...((componentData && componentData.style_config) || {}),
    bg_color_start: '',
    bg_color_end: '',
    bottom_bg_color: ''
  }

  return {
    ...(componentData || {}),
    __preview_banner_adjacent: isSearch,
    bottomBgColor: { color: [{ item: '' }] },
    componentBgConfig: null,
    style_config: styleConfig
  }
}

function getPageBackgroundColor() {
  const config = props.globalConfig || {}
  const bg = config.background || {}
  if (bg.type === 'color' && bg.color) return bg.color
  return bg.color || '#f5f5f5'
}

function buildThemeVariables(config = {}) {
  const normalized = normalizeThemeConfig(config)
  const primary = normalized.primary_color
  const secondary = normalized.secondary_color
  const gradient = normalized.gradient_color

  return {
    '--theme-primary': primary,
    '--theme-secondary': secondary,
    '--theme-gradient': gradient,
    '--brand-primary': primary,
    '--brand-primary-dark': primary,
    '--brand-primary-light': secondary,
    '--brand-primary-pale': toSoftBackground(secondary),
    '--brand-shadow': toRgba(primary, 0.18),
    '--brand-shadow-hover': toRgba(primary, 0.26),
    '--brand-focus-shadow': toRgba(primary, 0.16),
    '--text-link': primary,
    '--text-link-hover': primary,
    '--surface-user-hero': toSoftBackground(secondary),
    '--surface-user-hero-start': toSoftBackground(secondary),
    '--surface-user-hero-end': secondary
  }
}

function normalizeThemeConfig(config = {}) {
  const source = config && typeof config === 'object' && !Array.isArray(config) ? config : {}
  return {
    primary_color: normalizeHexColor(source.primary_color, DEFAULT_THEME_CONFIG.primary_color),
    secondary_color: normalizeHexColor(source.secondary_color, DEFAULT_THEME_CONFIG.secondary_color),
    gradient_color: normalizeGradient(source.gradient_color, DEFAULT_THEME_CONFIG.gradient_color)
  }
}

function normalizeHexColor(value, fallback) {
  const color = String(value || '').trim()
  if (/^#[0-9a-fA-F]{6}$/.test(color)) {
    return color.toLowerCase()
  }
  if (/^[0-9a-fA-F]{6}$/.test(color)) {
    return `#${color.toLowerCase()}`
  }
  return fallback
}

function normalizeGradient(value, fallback) {
  const gradient = String(value || '').trim()
  return /^linear-gradient\([#a-zA-Z0-9\s,%.()-]+\)$/.test(gradient) ? gradient : fallback
}

function toRgba(hex, alpha) {
  const color = normalizeHexColor(hex, DEFAULT_THEME_CONFIG.primary_color).slice(1)
  const red = parseInt(color.slice(0, 2), 16)
  const green = parseInt(color.slice(2, 4), 16)
  const blue = parseInt(color.slice(4, 6), 16)
  return `rgba(${red}, ${green}, ${blue}, ${alpha})`
}

function toSoftBackground(hex) {
  const color = normalizeHexColor(hex, DEFAULT_THEME_CONFIG.secondary_color).slice(1)
  const red = parseInt(color.slice(0, 2), 16)
  const green = parseInt(color.slice(2, 4), 16)
  const blue = parseInt(color.slice(4, 6), 16)
  const softRed = Math.round(red + (255 - red) * 0.86)
  const softGreen = Math.round(green + (255 - green) * 0.86)
  const softBlue = Math.round(blue + (255 - blue) * 0.86)
  return `rgb(${softRed}, ${softGreen}, ${softBlue})`
}

function handlePreviewComponentChange(component, activeIndex = 0) {
  if (!component || component.component_type !== 'banner') return
  bannerPreviewIndexes[component.stateKey] = Math.max(Number(activeIndex || 0), 0)
  scheduleHeaderBlurMeasure()
}

function scheduleHeaderBlurMeasure() {
  if (headerBlurMeasurePending.value) return
  headerBlurMeasurePending.value = true

  nextTick(() => {
    const run = () => {
      headerBlurMeasurePending.value = false
      headerBlurMeasureFrame.value = null
      measureHeaderBlurLayers()
    }

    if (typeof window !== 'undefined' && window.requestAnimationFrame) {
      headerBlurMeasureFrame.value = window.requestAnimationFrame(run)
      return
    }

    run()
  })
}

function measureHeaderBlurLayers() {
  const content = phoneContentRef.value
  if (!content || !content.getBoundingClientRect) return

  const contentRect = content.getBoundingClientRect()
  const nextRects = {}

  getHeaderBlurPairs().forEach((pair) => {
    const firstEl = getRefElement(pair.current.previewRef)
    const secondEl = getRefElement(pair.next.previewRef)
    if (!firstEl || !secondEl || !firstEl.getBoundingClientRect || !secondEl.getBoundingClientRect) return

    const firstRect = firstEl.getBoundingClientRect()
    const secondRect = secondEl.getBoundingClientRect()
    const top = Math.max(Math.round(firstRect.top - contentRect.top), 0)
    const height = Math.ceil(Math.max(firstRect.bottom, secondRect.bottom) - firstRect.top)

    if (height > 0) {
      nextRects[pair.key] = { top, height }
    }
  })

  if (!isSameHeaderBlurRects(nextRects)) {
    Object.keys(headerBlurRects).forEach((key) => delete headerBlurRects[key])
    Object.assign(headerBlurRects, nextRects)
  }
}

function setComponentRef(refName, el) {
  if (!refName) return
  if (el) {
    componentRefs[refName] = el
    return
  }
  delete componentRefs[refName]
}

function getRefElement(refName) {
  return componentRefs[refName] || null
}

function isSameHeaderBlurRects(nextRects) {
  const prevKeys = Object.keys(headerBlurRects)
  const nextKeys = Object.keys(nextRects)

  if (prevKeys.length !== nextKeys.length) return false

  return nextKeys.every((key) => {
    const prev = headerBlurRects[key]
    const next = nextRects[key]
    return prev && Math.abs(prev.top - next.top) < 1 && Math.abs(prev.height - next.height) < 1
  })
}

function handleComponentClick(component) {
  if (!props.selectable) return
  emit('select', component)
}

function handleBodyClick() {
  if (!props.selectable) return
  emit('deselect')
}

function handleRemove(component) {
  emit('remove', component)
}

function getSourceComponent(component) {
  return (component && component.sourceComponent) || component
}

function getComponentIndex(component) {
  const source = getSourceComponent(component)
  if (!source) return -1
  return props.modelValue.findIndex(item => item && item.id === source.id)
}

function isFirstComponent(component) {
  return getComponentIndex(component) <= 0
}

function isLastComponent(component) {
  const index = getComponentIndex(component)
  return index === -1 || index >= props.modelValue.length - 1
}

function isComponentHidden(component) {
  const source = getSourceComponent(component)
  return Number(source && source.status) === 0
}

function handleToggleVisible(component) {
  emit('toggle-visible', getSourceComponent(component))
}

function handleMoveUp(component) {
  emit('move-up', getSourceComponent(component))
}

function handleMoveDown(component) {
  emit('move-down', getSourceComponent(component))
}

function handleCopy(component) {
  emit('copy', getSourceComponent(component))
}

function handleDragEnd() {
  emit('update:modelValue', componentListModel.value)
}

function getOuterStyle(componentData) {
  const sc = (componentData && componentData.style_config) || {}
  const marginLr = sc.padding_lr !== undefined && sc.padding_lr !== null ? Number(sc.padding_lr) || 0 : 0
  const style = {}

  if (sc.page_margin_top > 0) {
    style.marginTop = `${sc.page_margin_top}px`
  }
  if (sc.margin_top > 0) {
    style.paddingTop = `${sc.margin_top}px`
  }
  if (sc.margin_bottom > 0) {
    style.paddingBottom = `${sc.margin_bottom}px`
  }
  style.marginLeft = `${marginLr}px`
  style.marginRight = `${marginLr}px`
  return style
}

function getInnerStyle(componentData) {
  const sc = (componentData && componentData.style_config) || {}
  const paddingX = sc.padding_x !== undefined && sc.padding_x !== null ? Number(sc.padding_x) || 0 : 0
  const style = {
    width: 'auto',
    maxWidth: '100%',
    boxSizing: 'border-box',
    paddingLeft: `${paddingX}px`,
    paddingRight: `${paddingX}px`
  }

  const start = sc.bg_color_start
  const end = sc.bg_color_end
  if (start && end && start !== end) {
    style.background = `linear-gradient(180deg, ${start} 0%, ${end} 100%)`
  } else if (start) {
    style.backgroundColor = start
  }

  if (sc.radius_mode === 'each') {
    const lt = sc.border_radius_lt || 0
    const rt = sc.border_radius_rt || 0
    const rb = sc.border_radius_rb || 0
    const lb = sc.border_radius_lb || 0
    if (lt || rt || rb || lb) {
      style.borderRadius = `${lt}px ${rt}px ${rb}px ${lb}px`
      style.overflow = 'hidden'
    }
  } else {
    const r = sc.border_radius || 0
    if (r > 0) {
      style.borderRadius = `${r}px`
      style.overflow = 'hidden'
    }
  }

  return style
}

watch(() => headerBlurSignature.value, () => {
  scheduleHeaderBlurMeasure()
})

watch(() => props.bodyHeight, () => {
  scheduleHeaderBlurMeasure()
})

onBeforeUnmount(() => {
  clearInterval(timer.value)
  if (headerBlurMeasureFrame.value && typeof window !== 'undefined' && window.cancelAnimationFrame) {
    window.cancelAnimationFrame(headerBlurMeasureFrame.value)
  }
  headerBlurMeasureFrame.value = null
  headerBlurMeasurePending.value = false
})

updateTime()
timer.value = setInterval(updateTime, 60000)
scheduleHeaderBlurMeasure()
</script>

<style lang="scss" scoped>
.phone-preview {
  --preview-screen-width: 390px;
  --preview-label-gutter: 92px;
  --preview-action-gutter: 48px;

  display: flex;
  justify-content: center;
}

.phone-frame {
  width: var(--preview-screen-width);
  background: #fff;
  border: 0;
  border-radius: 0;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
  overflow: visible;
}

.phone-preview--editable {
  .phone-frame {
    width: calc(var(--preview-screen-width) + var(--preview-label-gutter) + var(--preview-action-gutter));
    background: transparent;
    box-shadow: none;
  }

  .phone-status-bar {
    width: var(--preview-screen-width);
    margin-left: var(--preview-label-gutter);
  }

  .phone-body {
    width: calc(var(--preview-screen-width) + var(--preview-label-gutter) + var(--preview-action-gutter));
    overflow-x: hidden;
  }

  .phone-content {
    width: var(--preview-screen-width);
    margin-left: var(--preview-label-gutter);
  }
}

.phone-status-bar {
  height: 28px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  color: #1f2937;
  font-size: 12px;
  font-weight: 600;
  border-bottom: 1px solid #eef1f6;
  box-sizing: border-box;

  .status-icons {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
  }
}

.phone-header {
  height: 44px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 12px;
  border-bottom: 1px solid #eee;

  .title {
    font-size: 16px;
    font-weight: 500;
    color: inherit;
    flex: 1;
    text-align: center;
  }

  .placeholder {
    width: 20px;
  }
}

.phone-body {
  position: relative;
  height: 780px;
  overflow-y: auto;
  -ms-overflow-style: none;
  scrollbar-width: none;

  &::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
  }

  &::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 2px;
  }
}

.phone-content {
  position: relative;
  width: 100%;
  min-height: 100%;
}

.phone-component-list {
  position: relative;
  z-index: 1;
}

.empty-tip {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 200px;
  color: #999;
  font-size: 14px;
}

.preview-component {
  position: relative;
  width: auto;
  max-width: 100%;
  margin-bottom: 0;
  box-sizing: border-box;
  border: 2px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  overflow: visible;
  transition: border-color 0.2s, box-shadow 0.2s;

  &.is-selected {
    border-color: var(--prev-color-primary);
    border-style: solid;
    box-shadow: 0 0 0 1px rgba(243, 65, 0, 0.12);
  }

  &:hover:not(.is-selected) {
    border-color: var(--dashboard-accent-light);
    border-style: dashed;
  }

  &.is-hidden {
    .component-preview > div > :not(.component-hidden-mask) {
      opacity: 0.42;
    }
  }
}

.preview-component--in-header-blur {
  z-index: 1;
}

.preview-header-blur {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
  background: rgba(246, 246, 246, 0.72);
}

.preview-header-blur__image {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  object-fit: cover;
  transform: scale(1.5);
  filter: blur(18px);
  transition: opacity 0.45s ease;
}

.preview-header-blur__image.is-active {
  opacity: 0.78;
}

.preview-header-blur__overlay {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: transparent;
}

.component-label {
  position: absolute;
  left: -84px;
  top: 10px;
  z-index: 10;
  width: 76px;
  max-width: 76px;
  font-size: 12px;
  color: var(--dashboard-text-secondary);
  background: #fff;
  border: 1px solid var(--dashboard-border);
  border-radius: 3px;
  padding: 5px 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
  cursor: pointer;
  pointer-events: auto;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.component-label--hidden {
  color: var(--dashboard-text-muted);
  background: var(--dashboard-surface-muted);
}

.component-label__text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.component-side-actions {
  position: absolute;
  top: 50%;
  right: -38px;
  z-index: 20;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transform: translateY(-50%);
}

.component-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  color: var(--dashboard-text-muted);
  cursor: pointer;
  background: #fff;
  border: 1px solid var(--dashboard-border);
  border-radius: 4px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
  outline: none;
  transition: color 0.2s, border-color 0.2s, background 0.2s;

  i {
    font-size: 14px;
    line-height: 1;
  }

  &:hover,
  &:focus {
    color: var(--prev-color-primary);
    border-color: var(--dashboard-accent-light);
  }

  &.is-active {
    color: #e6a23c;
    background: #fdf6ec;
    border-color: #f5dab1;
  }

  &.is-disabled,
  &:disabled {
    color: #c0c4cc;
    cursor: not-allowed;
    background: #f5f7fa;
    border-color: #ebeef5;
    box-shadow: none;
  }
}

.component-action--danger {
  &:hover,
  &:focus {
    color: #f56c6c;
    border-color: #fbc4c4;
    background: #fef0f0;
  }
}

.component-preview {
  position: relative;
  z-index: 1;
  display: block;
  width: auto;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  border-radius: 4px;
  min-height: 60px;
  overflow: hidden;

  > div {
    position: relative;
    width: auto;
    max-width: 100%;
  }
}

.component-hidden-mask {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 58px;
  height: 24px;
  padding: 0 8px;
  color: #fff;
  font-size: 12px;
  background: rgba(31, 45, 61, 0.72);
  border-radius: 12px;
  opacity: 1;
  pointer-events: none;
  transform: translate(-50%, -50%);
}

.preview-component--title,
.preview-component--notice,
.preview-component--blank,
.preview-component--rich_text {
  .component-preview {
    min-height: 0;
  }
}

.preview-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 80px;
  color: #999;
  font-size: 14px;
  background: #fafafa;
}
</style>
