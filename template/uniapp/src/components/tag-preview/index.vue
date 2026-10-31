<template>
  <view v-if="visible" class="tag-preview" :class="tagClassName" :style="tagStyle">
    <image v-if="isImageTag" class="tag-preview__icon" :src="iconUrl" mode="aspectFit"></image>
    <text v-else class="tag-preview__text">{{ displayText }}</text>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { resolveAssetUrl } from '@/utils/runtime_config'

defineOptions({
  name: 'TagPreview'
})

const props = defineProps({
  tag: {
    type: Object,
    default: () => ({})
  },
  size: {
    type: String,
    default: 'medium'
  }
})

const displayText = computed(() => {
  return String(props.tag.name || props.tag.label || props.tag.title || props.tag.text || '').trim()
})

const iconUrl = computed(() => {
  return String(props.tag.icon || '').trim()
})

const effectType = computed(() => {
  return Number(props.tag.effect_type || props.tag.effectType || 1) === 2 && !!iconUrl.value ? 2 : 1
})

const isImageTag = computed(() => {
  return effectType.value === 2 && !!iconUrl.value
})

const tagClassName = computed(() => {
  const classes = []
  if (props.size === 'small') {
    classes.push('tag-preview--small')
  }
  if (isImageTag.value) {
    classes.push('tag-preview--image')
  }
  return classes.join(' ')
})

const tagStyle = computed(() => {
  if (isImageTag.value) {
    return ''
  }

  const textColor = String(props.tag.text_color || props.tag.textColor || '').trim()
  const bgColor = String(props.tag.bg_color || props.tag.bgColor || '').trim()
  const borderColor = String(props.tag.border_color || props.tag.borderColor || '').trim()
  const bgImage = resolveAssetUrl(props.tag.bg_image || props.tag.bgImage || props.tag.background_image || props.tag.backgroundImage || '')
  const style = []
  if (textColor) {
    style.push(`color: ${textColor};`)
  }
  if (bgImage) {
    style.push(`background-image: url(${bgImage});`)
    style.push('background-size: 100% 100%;')
    style.push('background-repeat: no-repeat;')
    style.push('background-position: center;')
  }
  if (bgColor) {
    style.push(`background-color: ${bgColor};`)
  }
  if (borderColor) {
    style.push('border-style: solid;')
    style.push(`border-color: ${borderColor};`)
  }
  return style.join(' ')
})

const visible = computed(() => {
  return Boolean(displayText.value || iconUrl.value)
})
</script>

<style lang="scss" scoped>
.tag-preview {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  max-width: 180rpx;
  height: 44rpx;
  margin-right: 12rpx;
  padding: 0 16rpx;
  border-width: 1rpx;
  border-style: none;
  border-color: transparent;
  border-radius: 8rpx;
  box-sizing: border-box;
  overflow: hidden;
  line-height: 1;
  vertical-align: top;

  &--small {
    max-width: 132rpx;
    height: 36rpx;
    margin-right: 8rpx;
    padding: 0 12rpx;
  }

  &--image {
    width: auto;
    max-width: 160rpx;
    padding: 0;
    border-style: none;
    border-color: transparent;
    background: transparent;
  }
}

.tag-preview__text {
  display: block;
  max-width: 100%;
  font-size: 24rpx;
  line-height: 1;
  color: inherit;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tag-preview--small .tag-preview__text {
  font-size: 20rpx;
  line-height: 1;
}

.tag-preview__icon {
  display: block;
  max-width: 144rpx;
  height: 44rpx;
}

.tag-preview--small .tag-preview__icon {
  max-width: 132rpx;
  height: 36rpx;
}
</style>
