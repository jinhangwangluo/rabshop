<template>
  <view class="diy-wrapper" :style="outerStyle">
    <view class="diy-wrapper__inner" :style="innerStyle">
      <slot />
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { toRpxUnit } from '@/utils/diy_adapter'

const DEFAULT_STYLE_CONFIG = {
  image_radius_mode: 'all',
  image_radius: 0,
  image_radius_lt: 0,
  image_radius_rt: 0,
  image_radius_lb: 0,
  image_radius_rb: 0,
  bg_color_start: '',
  bg_color_end: '',
  bottom_bg_color: '',
  margin_top: 0,
  margin_bottom: 0,
  padding_lr: 0,
  padding_x: 10,
  page_margin_top: 0,
  radius_mode: 'all',
  border_radius: 0,
  border_radius_lt: 0,
  border_radius_rt: 0,
  border_radius_lb: 0,
  border_radius_rb: 0
}

defineOptions({
  name: 'DiyWrapper'
})

const props = defineProps({
  styleConfig: {
    type: Object,
    default: null
  }
})

const cfg = computed(() => {
  if (!props.styleConfig || typeof props.styleConfig !== 'object') {
    return { ...DEFAULT_STYLE_CONFIG }
  }
  return { ...DEFAULT_STYLE_CONFIG, ...props.styleConfig }
})

const outerStyle = computed(() => {
  const styleConfig = cfg.value
  const style = {
    boxSizing: 'border-box'
  }

  if (styleConfig.bottom_bg_color) {
    style.backgroundColor = styleConfig.bottom_bg_color
  }

  if (Number(styleConfig.padding_lr) > 0) {
    style.paddingLeft = toRpxUnit(styleConfig.padding_lr)
    style.paddingRight = toRpxUnit(styleConfig.padding_lr)
  }

  if (Number(styleConfig.page_margin_top) > 0) {
    style.marginTop = toRpxUnit(styleConfig.page_margin_top)
  }

  if (Number(styleConfig.margin_top) > 0) {
    style.paddingTop = toRpxUnit(styleConfig.margin_top)
  }

  if (Number(styleConfig.margin_bottom) > 0) {
    style.paddingBottom = toRpxUnit(styleConfig.margin_bottom)
  }

  return style
})

const innerStyle = computed(() => {
  const styleConfig = cfg.value
  const style = {
    boxSizing: 'border-box'
  }

  if (Number(styleConfig.padding_x) > 0) {
    style.paddingLeft = toRpxUnit(styleConfig.padding_x)
    style.paddingRight = toRpxUnit(styleConfig.padding_x)
  }

  const startColor = styleConfig.bg_color_start
  const endColor = styleConfig.bg_color_end
  if (startColor && endColor && startColor !== endColor) {
    style.background = `linear-gradient(180deg, ${startColor} 0%, ${endColor} 100%)`
  } else if (startColor) {
    style.backgroundColor = startColor
  }

  if (styleConfig.radius_mode === 'each') {
    const lt = Number(styleConfig.border_radius_lt || 0)
    const rt = Number(styleConfig.border_radius_rt || 0)
    const rb = Number(styleConfig.border_radius_rb || 0)
    const lb = Number(styleConfig.border_radius_lb || 0)
    if (lt || rt || rb || lb) {
      style.borderRadius = `${toRpxUnit(lt)} ${toRpxUnit(rt)} ${toRpxUnit(rb)} ${toRpxUnit(lb)}`
      style.overflow = 'hidden'
    }
  } else {
    const r = Number(styleConfig.border_radius || 0)
    if (r > 0) {
      style.borderRadius = toRpxUnit(r)
      style.overflow = 'hidden'
    }
  }

  return style
})
</script>

<style lang="scss" scoped>
.diy-wrapper {
  width: auto;
}

.diy-wrapper__inner {
  width: auto;
  box-sizing: border-box;
}
</style>
