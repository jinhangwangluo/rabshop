<template>
  <view v-if="visible" class="search-tag-pill" :class="pillClassName" :style="pillStyle">
    <image v-if="iconUrl" class="search-tag-pill__icon" :src="iconUrl" mode="aspectFit"></image>
    <text v-if="displayText" class="search-tag-pill__text">{{ displayText }}</text>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { resolveAssetUrl } from '@/utils/runtime_config'

defineOptions({
  name: 'SearchTagPill'
})

const props = defineProps({
  tag: {
    type: Object,
    default: () => ({})
  },
  text: {
    type: String,
    default: ''
  },
  className: {
    type: String,
    default: ''
  },
  effectType: {
    type: [Number, String],
    default: ''
  },
  textColor: {
    type: String,
    default: ''
  },
  bgColor: {
    type: String,
    default: ''
  },
  borderColor: {
    type: String,
    default: ''
  },
  bgImage: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: ''
  }
})

const displayText = computed(() => {
  return String(props.text || props.tag.text || props.tag.name || props.tag.label || '').trim()
})

const iconUrl = computed(() => {
  return String(props.icon || props.tag.icon || '').trim()
})

const resolvedEffectType = computed(() => {
  const value = Number(props.effectType || props.tag.effectType || props.tag.effect_type || 1)
  return value === 2 ? 2 : 1
})

const pillStyle = computed(() => {
  if (resolvedEffectType.value !== 1) {
    return ''
  }

  const textColor = String(props.textColor || props.tag.textColor || props.tag.text_color || '').trim()
  const bgColor = String(props.bgColor || props.tag.bgColor || props.tag.bg_color || '').trim()
  const borderColor = String(props.borderColor || props.tag.borderColor || props.tag.border_color || '').trim()
  const bgImage = resolveAssetUrl(props.bgImage || props.tag.bgImage || props.tag.bg_image || props.tag.backgroundImage || props.tag.background_image || '')
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

const pillClassName = computed(() => {
  const classes = []
  const className = String(props.className || props.tag.className || props.tag.class_name || '').trim()
  if (className) {
    classes.push(className)
  }
  if (resolvedEffectType.value === 2) {
    classes.push('is-image')
  }
  if (pillStyle.value) {
    classes.push('is-custom')
  }
  if (!displayText.value) {
    classes.push('is-icon-only')
  }
  return classes.join(' ')
})

const visible = computed(() => {
  return Boolean(displayText.value || iconUrl.value)
})
</script>

<style lang="scss" scoped>
.search-tag-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  max-width: 100%;
  min-height: 36rpx;
  margin-right: 10rpx;
  margin-bottom: 10rpx;
  padding: 6rpx 12rpx;
  box-sizing: border-box;
  border-width: 1rpx;
  border-style: none;
  border-color: transparent;
  border-radius: 10rpx;
}

.search-tag-pill.is-icon-only {
  width: 36rpx;
  padding: 6rpx;
}

.search-tag-pill__icon {
  flex-shrink: 0;
  width: 22rpx;
  height: 22rpx;
  margin-right: 6rpx;
}

.search-tag-pill.is-icon-only .search-tag-pill__icon {
  margin-right: 0;
}

.search-tag-pill__text {
  overflow: hidden;
  max-width: 140rpx;
  font-size: 18rpx;
  line-height: 24rpx;
  color: inherit;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
