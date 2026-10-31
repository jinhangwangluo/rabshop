<template>
  <view class="diy-hot-zone" :style="componentStyle">
    <image
      v-if="imageUrl"
      class="hot-image"
      :src="imageUrl"
      mode="widthFix"
      @load="onImageLoad"
    />
    <view v-if="hotAreas.length > 0" class="hot-areas">
      <view
        v-for="(area, index) in hotAreas"
        :key="area.id || index"
        class="hot-area"
        :style="area.areaStyle"
        hover-class="hot-area--hover"
        @click="handleClick(area)"
      />
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { getImageRadiusStyle } from '@/utils/diy_adapter'

defineOptions({
  name: 'DiyHotZone'
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

const imageUrl = computed(() => {
  return props.config.image || ''
})

const hotAreas = computed(() => {
  const source = props.data.length > 0 ? props.data : (props.config.hot_areas || [])
  return Array.isArray(source)
    ? source
      .filter((item) => item && Number(item.width) > 0 && Number(item.height) > 0)
      .map((item) => ({
        ...item,
        areaStyle: `left: ${item.x}%; top: ${item.y}%; width: ${item.width}%; height: ${item.height}%;`
      }))
    : []
})

const componentStyle = computed(() => {
  const style = {
    ...getImageRadiusStyle(props.config.style_config)
  }
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  return style
})

function onImageLoad() {
  // 图片加载完成
}

function resolveLinkType(area = {}) {
  const linkUrl = String(area.link_url || '').trim()
  if (/^(https?:)?\/\//i.test(linkUrl)) {
    return 'url'
  }
  return area.link_type || (linkUrl ? 'page' : '')
}

function handleClick(area) {
  const linkType = resolveLinkType(area)
  const linkUrl = String(area.link_url || '').trim()

  if (!linkUrl) {
    return
  }

  if (linkType === 'url') {
    uni.navigateTo({
      url: `/pages/webview/webview?url=${encodeURIComponent(linkUrl)}`
    })
    return
  }

  uni.navigateTo({ url: linkUrl })
}
</script>

<style lang="scss" scoped>
.diy-hot-zone {
  position: relative;
  width: auto;

  .hot-image {
    width: 100%;
    display: block;
  }

  .hot-areas {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;

    .hot-area {
      position: absolute;

      &--hover,
      &:active {
        background: rgba(0, 0, 0, 0.08);
      }
    }
  }
}
</style>
