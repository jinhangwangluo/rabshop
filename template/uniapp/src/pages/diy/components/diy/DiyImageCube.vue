<template>
  <view class="diy-image-cube" :style="cubeStyle">
    <view v-if="hasGridPosition" class="cube-grid" :style="gridStyle">
      <view
        v-for="(item, index) in cubeBlocks"
        :key="item.id || index"
        class="cube-grid__item"
        :style="item.blockStyle"
        @click="handleClick(item)"
      >
        <image class="cube-image" :src="item.image" mode="aspectFill" />
      </view>
    </view>

    <view v-else class="cube-flex" :style="gapStyle">
      <view
        v-for="(item, index) in cubeBlocks"
        :key="item.id || index"
        class="cube-flex__item"
        :style="item.blockStyle"
        @click="handleClick(item)"
      >
        <image class="cube-image" :src="item.image" mode="aspectFill" />
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { toRpxUnit } from '@/utils/diy_adapter'

defineOptions({
  name: 'DiyImageCube'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  }
})

const sourceBlocks = computed(() => {
  const source = props.data.length > 0 ? props.data : (props.config.blocks || [])
  return Array.isArray(source) ? source.filter((item) => item && item.image) : []
})

const cubeBlocks = computed(() => {
  const useGrid = hasGridPosition.value
  return sourceBlocks.value.map((item) => ({
    ...item,
    blockStyle: useGrid ? buildGridBlockStyleText(item) : flexBlockStyleText.value
  }))
})

const styleConfig = computed(() => {
  return (props.config && props.config.style_config && typeof props.config.style_config === 'object')
    ? props.config.style_config
    : {}
})

const imageRadiusStyleText = computed(() => {
  const styleConfigValue = styleConfig.value || {}
  const mode = styleConfigValue.image_radius_mode === 'each' ? 'each' : 'all'

  if (mode === 'each') {
    const lt = normalizePercent(styleConfigValue.image_radius_lt)
    const rt = normalizePercent(styleConfigValue.image_radius_rt)
    const rb = normalizePercent(styleConfigValue.image_radius_rb)
    const lb = normalizePercent(styleConfigValue.image_radius_lb)

    if (lt || rt || rb || lb) {
      return `border-radius: ${lt * 2}rpx ${rt * 2}rpx ${rb * 2}rpx ${lb * 2}rpx`
    }

    return ''
  }

  const radius = normalizePercent(styleConfigValue.image_radius)
  return radius > 0 ? `border-radius: ${radius * 2}rpx` : ''
})

const cubeStyle = computed(() => {
  const style = {}
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  return style
})

const gapStyle = computed(() => {
  return {
    gap: toRpxUnit(props.config.gap, 12)
  }
})

const hasGridPosition = computed(() => {
  return sourceBlocks.value.some((item) => {
    return hasValidPosition(item)
  })
})

const gridStyle = computed(() => {
  return {
    gap: toRpxUnit(props.config.gap, 12)
  }
})

const flexBlockStyleText = computed(() => {
  const columns = Number(props.config.columns || 2)
  const gap = toRpxUnit(props.config.gap, 12)
  const styleList = []

  if (imageRadiusStyleText.value) {
    styleList.push(imageRadiusStyleText.value)
  }

  styleList.push(`width: calc(${100 / columns}% - ${gap})`)
  return styleList.join('; ')
})

function hasValidPosition(item = {}) {
  return [item.rowStart, item.rowEnd, item.colStart, item.colEnd].every((value) => Number.isFinite(Number(value)))
}

function normalizePercent(value) {
  const percent = Number(value)
  if (!Number.isFinite(percent)) {
    return 0
  }

  return Math.min(Math.max(percent, 0), 100)
}

function buildGridBlockStyleText(item = {}) {
  const styleList = []

  if (imageRadiusStyleText.value) {
    styleList.push(imageRadiusStyleText.value)
  }

  styleList.push(`grid-column: ${item.colStart} / ${Number(item.colEnd) + 1}`)
  styleList.push(`grid-row: ${item.rowStart} / ${Number(item.rowEnd) + 1}`)
  return styleList.join('; ')
}

function handleClick(item) {
  if (item.link_type === 'url' && item.link_url) {
    uni.navigateTo({
      url: `/pages/webview/webview?url=${encodeURIComponent(item.link_url)}`
    })
    return
  }

  if (item.link_url) {
    uni.navigateTo({ url: item.link_url })
  }
}
</script>

<style lang="scss" scoped>
.diy-image-cube {
  width: auto;
}

.cube-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(4, 1fr);
  aspect-ratio: 1;
}

.cube-flex {
  display: flex;
  flex-wrap: wrap;
}

.cube-grid__item,
.cube-flex__item {
  position: relative;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.72);
}

.cube-flex__item {
  min-height: 220rpx;
}

.cube-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
</style>
