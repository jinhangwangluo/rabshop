<template>
  <div :style="wrapperStyle">
    <div :style="boxStyle">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'CommonWrapper'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

function safeValList(list) {
  const def = [{ val: 0 }, { val: 0 }, { val: 0 }, { val: 0 }]
  return Array.isArray(list) && list.length >= 4 ? list : def
}

const wrapperStyle = computed(() => {
  const config = props.config || {}
  const bottomBgColor = config.bottomBgColor && config.bottomBgColor.color ? config.bottomBgColor.color[0].item : ''
  return {
    background: bottomBgColor
  }
})

const boxStyle = computed(() => {
  const config = props.config || {}
  const marginConfig = config.marginConfig || {}
  const mList = safeValList(marginConfig.valList)
  const paddingConfig = config.paddingConfig || {}
  const pList = safeValList(paddingConfig.valList)
  const componentBgConfig = config.componentBgConfig
  const zIndexConfig = config.zIndexConfig
  const borderConfig = config.borderConfig
  const shadowConfig = config.shadowConfig
  const fillet = config.fillet
  const borderRadius = config.borderRadius ? config.borderRadius : '0'
  const style = {
    marginTop: (!marginConfig.isAll ? (marginConfig.val || 0) : mList[0].val) + 'px',
    marginBottom: (!marginConfig.isAll ? (marginConfig.val || 0) : mList[2].val) + 'px',
    marginLeft: (!marginConfig.isAll ? (marginConfig.val || 0) : mList[3].val) + 'px',
    marginRight: (!marginConfig.isAll ? (marginConfig.val || 0) : mList[1].val) + 'px',
    paddingTop: (!paddingConfig.isAll ? (paddingConfig.val || 0) : pList[0].val) + 'px',
    paddingBottom: (!paddingConfig.isAll ? (paddingConfig.val || 0) : pList[2].val) + 'px',
    paddingLeft: (!paddingConfig.isAll ? (paddingConfig.val || 0) : pList[3].val) + 'px',
    paddingRight: (!paddingConfig.isAll ? (paddingConfig.val || 0) : pList[1].val) + 'px'
  }

  if (componentBgConfig) {
    if (componentBgConfig.tabVal === 0) {
      const colorConfig = componentBgConfig.colorConfig
      const colorDirection = componentBgConfig.colorDirection

      if (colorConfig && colorConfig.color) {
        const colors = colorConfig.color.map(c => c.item)
        if (colors.length > 1) {
          let deg = '90deg'
          if (colorDirection) {
            switch (colorDirection.tabVal) {
              case 0:
                deg = '90deg'
                break
              case 1:
                deg = '180deg'
                break
              case 2:
                deg = '135deg'
                break
              case 3:
                deg = '200deg'
                break
            }
          }
          style.background = `linear-gradient(${deg}, ${colors[0]} 0%, ${colors[1]} 100%)`
        } else {
          style.background = colors[0]
        }
      }
    } else if (componentBgConfig.tabVal === 1) {
      const imageConfig = componentBgConfig.imageConfig
      if (imageConfig && imageConfig.url) {
        style.backgroundImage = `url(${imageConfig.url})`
        style.backgroundRepeat = 'no-repeat'
        style.backgroundSize = 'cover'
        style.backgroundPosition = 'center'
      }
    }
  }

  if (fillet) {
    if (fillet.type) {
      const valList = fillet.valList
      style.borderRadius = `${valList[0].val}px ${valList[1].val}px ${valList[3].val}px ${valList[2].val}px`
    } else {
      style.borderRadius = fillet.val + 'px'
    }
    style.overflow = 'hidden'
  } else if (borderRadius && borderRadius !== '0') {
    style.borderRadius = borderRadius
    style.overflow = 'hidden'
  }

  if (borderConfig && borderConfig.tabVal) {
    const color = borderConfig.colorConfig ? borderConfig.colorConfig.color[0].item : '#000'
    const width = borderConfig.widthConfig ? borderConfig.widthConfig.val : 1
    let borderStyle = 'solid'
    if (borderConfig.styleConfig) {
      const styleVal = borderConfig.styleConfig.tabVal
      if (styleVal === 1) {
        borderStyle = 'dashed'
      } else if (styleVal === 2) {
        borderStyle = 'dotted'
      }
    }
    style.border = `${width}px ${borderStyle} ${color}`
  }

  if (shadowConfig && shadowConfig.tabVal) {
    const x = shadowConfig.xConfig ? shadowConfig.xConfig.val : 0
    const y = shadowConfig.yConfig ? shadowConfig.yConfig.val : 0
    const blur = shadowConfig.blurConfig ? shadowConfig.blurConfig.val : 0
    const spread = shadowConfig.spreadConfig ? shadowConfig.spreadConfig.val : 0
    const color = shadowConfig.colorConfig ? shadowConfig.colorConfig.color[0].item : '#000'
    style.boxShadow = `${x}px ${y}px ${blur}px ${spread}px ${color}`
  }

  if (zIndexConfig) {
    style.zIndex = zIndexConfig.val
    style.position = 'relative'
  }

  if (config.textColor && config.textColor.color) {
    style.color = config.textColor.color[0].item
  }
  return style
})
</script>

<style scoped></style>
