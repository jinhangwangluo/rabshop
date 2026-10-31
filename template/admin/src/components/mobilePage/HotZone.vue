<template>
  <common-wrapper :config="config">
    <div class="hot-zone-area">
      <div v-if="!bgImage && areas.length === 0" class="zone-empty">
        <LegacyIcon name="el-icon-map-location" />
        <span>热区图片</span>
      </div>
      <div v-else class="zone-wrapper" :style="wrapperStyle">
        <img v-if="bgImage" :src="bgImage" alt="热区底图" class="zone-bg">
        <div v-else class="zone-bg-placeholder">
          <LegacyIcon name="el-icon-picture-outline" />
        </div>
        <div
          v-for="(area, index) in areas"
          :key="index"
          class="zone-area"
          :style="getAreaStyle(area)"
          @click.prevent="handleClick(area)"
        >
          <span v-if="debugMode" class="area-label">{{ area.link || '热区' + (index + 1) }}</span>
        </div>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'
import { getImageRadiusStyle } from './imageRadius'

defineOptions({
  name: 'HotZone'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const bgImage = ref('')
const areas = ref([])
const debugMode = ref(false)

const imageRadiusStyle = computed(() => getImageRadiusStyle(props.config && props.config.style_config))

const wrapperStyle = computed(() => ({
  width: '100%',
  ...imageRadiusStyle.value
}))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  if (data.image !== undefined || data.hot_areas !== undefined) {
    bgImage.value = data.image || ''
    areas.value = (data.hot_areas || []).map(item => ({
      image: item.image || '',
      x: item.x || 0,
      y: item.y || 0,
      width: item.width || 50,
      height: item.height || 30,
      link: item.link_url || ''
    }))
    debugMode.value = data.debug !== undefined ? data.debug : false
  }
}

function getAreaStyle(area) {
  return {
    left: `${area.x}%`,
    top: `${area.y}%`,
    width: `${area.width}%`,
    height: `${area.height}%`
  }
}

function handleClick() {
  // 在预览中点击热区不做跳转，只做视觉反馈
}
</script>

<style scoped lang="scss">
.hot-zone-area {
  width: auto;
}

.zone-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 0;
  color: #c0c4cc;
  font-size: 12px;

  i {
    font-size: 28px;
    margin-bottom: 8px;
  }
}

.zone-wrapper {
  position: relative;
  width: 100%;
}

.zone-bg {
  width: 100%;
  height: auto;
  display: block;
}

.zone-bg-placeholder {
  width: 100%;
  height: 200px;
  background: #f5f5f5;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;

  i {
    font-size: 32px;
  }
}

.zone-area {
  position: absolute;
  border: none;
  background: transparent;
  cursor: pointer;
}

.area-label {
  font-size: 10px;
  color: #409eff;
  text-align: center;
  padding: 2px 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}
</style>
