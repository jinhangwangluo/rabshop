<template>
  <common-wrapper :config="config">
    <div class="diy-search-preview" :class="previewClass" :style="previewStyle">
      <div
        class="diy-search-preview__box"
        :class="{ 'diy-search-preview__box--square': isSquare }"
        :style="boxStyle"
      >
        <span v-if="showLeftIcon" class="diy-search-preview__icon-wrap">
          <img
            v-if="leftIconUrl"
            :src="leftIconUrl"
            alt=""
            class="diy-search-preview__icon-img"
          >
          <LegacyIcon
            v-else
            name="el-icon-search"
            class="diy-search-preview__icon"
            :style="textStyle"
          />
        </span>
        <span class="diy-search-preview__placeholder" :style="textStyle">
          {{ placeholderText }}
        </span>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed } from 'vue'
import CommonWrapper from './CommonWrapper'

defineOptions({
  name: 'SearchBox',
  cname: '搜索框',
  icon: '#iconzujian-sousuokuang',
  configName: 'c_search_box',
  type: 0,
  defaultName: 'headerSerch'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const safeConfig = computed(() => (props.config && typeof props.config === 'object' ? props.config : {}))
const placeholderText = computed(() => safeConfig.value.placeholder || '搜索课程、专题、直播')
const showLeftIcon = computed(() => safeConfig.value.show_left_icon !== false)
const leftIconUrl = computed(() => {
  const logoConfig = safeConfig.value.logoConfig && typeof safeConfig.value.logoConfig === 'object' ? safeConfig.value.logoConfig : {}
  return logoConfig.url || safeConfig.value.left_icon_url || safeConfig.value.icon_url || safeConfig.value.left_icon || ''
})
const isSquare = computed(() => safeConfig.value.shape === 'square')
const isBannerAdjacent = computed(() => safeConfig.value.__preview_banner_adjacent === true)
const previewClass = computed(() => (isBannerAdjacent.value ? 'diy-search-preview--banner-adjacent' : ''))
const previewStyle = computed(() => ({ backgroundColor: 'transparent' }))
const boxStyle = computed(() => ({
  backgroundColor: isBannerAdjacent.value
    ? 'rgba(255, 255, 255, 0.68)'
    : (safeConfig.value.box_bg_color || '#ffffff')
}))
const textStyle = computed(() => ({
  color: safeConfig.value.text_color || '#8a8f98'
}))
</script>

<style scoped lang="scss">
.diy-search-preview {
  width: auto;
  padding: 10px 0;
  box-sizing: border-box;
}

.diy-search-preview__box {
  display: flex;
  align-items: center;
  height: 40px;
  padding: 0 14px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 20px;
  background: #ffffff;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
  box-sizing: border-box;
  cursor: pointer;
}

.diy-search-preview--banner-adjacent .diy-search-preview__box {
  border-color: rgba(255, 255, 255, 0.5);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.1);
}

.diy-search-preview__box--square {
  border-radius: 6px;
}

.diy-search-preview__icon-wrap {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  margin-right: 8px;
}

.diy-search-preview__icon {
  font-size: 16px;
  line-height: 1;
}

.diy-search-preview__icon-img {
  display: block;
  width: 18px;
  height: 18px;
  object-fit: contain;
}

.diy-search-preview__placeholder {
  flex: 1;
  min-width: 0;
  font-size: 13px;
  line-height: 18px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
