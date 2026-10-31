<template>
  <view class="diy-page" :style="pageStyleVars">
    <view
      v-for="(component, index) in normalizedComponents"
      :key="component.id || index"
      class="diy-page__section"
      :class="component.sectionClass"
      :style="component.sectionStyle"
    >
      <DiyWrapper :style-config="component.styleConfig">
        <DiyRenderer
          :view-name="component.viewName"
          :config="component.componentConfig"
          :data="component.componentData"
        />
      </DiyWrapper>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { toRpxUnit } from '@/utils/diy_adapter'
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

function resolveComponentViewName(type = '') {
  const normalizedType = String(type || '').trim()
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
  }
})

const normalizedComponents = computed(() => {
  if (!Array.isArray(props.components)) {
    return []
  }

  const preparedComponents = props.components.map((component) => {
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
      componentData
    }
  }).filter((item) => item && shouldRenderComponent(item))

  return preparedComponents.map((item, index) => {
    const { component, type, componentConfig, componentData } = item
    const sectionClassList = [`type-${type}`]

    if (index === 0) {
      sectionClassList.push('is-first')
    }

    if (index === preparedComponents.length - 1) {
      sectionClassList.push('is-last')
    }

    if (type === 'search' || type === 'banner') {
      sectionClassList.push('is-hero')
    }

    if (type === 'title') {
      sectionClassList.push('is-title')
    }

    return {
      ...component,
      viewName: resolveComponentViewName(type),
      sectionClass: sectionClassList.join(' '),
      sectionStyle: getSectionStyle(type, index === preparedComponents.length - 1),
      componentConfig,
      componentData,
      styleConfig: componentConfig.style_config || null
    }
  })
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
  return String(component.component_type || component.type || '').trim()
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
  if (item.type === 'special') {
    if (Array.isArray(item.componentData)) {
      return item.componentData.length > 0
    }
    if (item.componentData && typeof item.componentData === 'object') {
      const categories = item.componentData.categories
      const list = item.componentData.list
      return item.componentData.mode === 'category_tabs' &&
        ((Array.isArray(categories) && categories.length > 0) || (Array.isArray(list) && list.length > 0))
    }
    return false
  }

  return true
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
  width: 100%;
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
