<template>
  <common-wrapper :config="config">
    <div class="image-cube">
      <div v-if="blocks.length === 0" class="cube-empty">
        <LegacyIcon name="el-icon-picture-outline" />
        <span>图片魔方</span>
      </div>

      <div v-else-if="hasGridPosition" class="cube-canvas" :style="canvasStyle">
        <div
          v-for="block in blocks"
          :key="block.id"
          class="cube-block"
          :style="cubeBlockStyle(block)"
        >
          <img v-if="block.image" :src="block.image" alt="" class="cube-img">
          <div v-else class="cube-placeholder">
            <LegacyIcon name="el-icon-picture-outline" />
          </div>
        </div>
      </div>

      <div v-else class="cube-flex" :style="flexStyle">
        <div
          v-for="block in blocks"
          :key="block.id"
          class="cube-flex-block"
          :style="imageRadiusStyle"
        >
          <img v-if="block.image" :src="block.image" alt="" class="cube-img">
          <div v-else class="cube-placeholder">
            <LegacyIcon name="el-icon-picture-outline" />
          </div>
        </div>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'

defineOptions({
  name: 'ImageCube',
  cname: '图片魔方',
  ename: 'imageCube',
  icon: '#iconzujian-tupianmofang',
  defaultName: 'imageCube',
  configName: 'c_image_cube',
  type: 0
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const blocks = ref([])
const gap = ref(4)

const styleConfig = computed(() => {
  return props.config && props.config.style_config && typeof props.config.style_config === 'object'
    ? props.config.style_config
    : {}
})

const imageRadiusStyle = computed(() => {
  const currentStyleConfig = styleConfig.value || {}
  const mode = currentStyleConfig.image_radius_mode === 'each' ? 'each' : 'all'

  if (mode === 'each') {
    const lt = normalizePercent(currentStyleConfig.image_radius_lt)
    const rt = normalizePercent(currentStyleConfig.image_radius_rt)
    const rb = normalizePercent(currentStyleConfig.image_radius_rb)
    const lb = normalizePercent(currentStyleConfig.image_radius_lb)

    if (lt || rt || rb || lb) {
      return {
        borderRadius: `${lt}px ${rt}px ${rb}px ${lb}px`
      }
    }

    return {}
  }

  const radius = normalizePercent(currentStyleConfig.image_radius)
  return radius > 0 ? { borderRadius: `${radius}px` } : {}
})

const hasGridPosition = computed(() => blocks.value.some(block => hasValidPosition(block)))
const canvasStyle = computed(() => ({
  gap: `${gap.value}px`
}))
const flexStyle = computed(() => ({
  gap: `${gap.value}px`,
  '--cube-gap': `${gap.value}px`,
  '--cube-columns': 2
}))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  blocks.value = Array.isArray(data.blocks) ? data.blocks : []
  gap.value = data.gap !== undefined ? data.gap : 4
}

function hasValidPosition(block = {}) {
  return [block.colStart, block.colEnd, block.rowStart, block.rowEnd].every(value => Number.isFinite(Number(value)))
}

function normalizePercent(value) {
  const percent = Number(value)
  if (!Number.isFinite(percent)) {
    return 0
  }

  return Math.min(Math.max(percent, 0), 100)
}

function cubeBlockStyle(block) {
  if (!hasValidPosition(block)) {
    return { ...imageRadiusStyle.value }
  }

  return {
    ...imageRadiusStyle.value,
    gridColumn: `${block.colStart} / ${Number(block.colEnd) + 1}`,
    gridRow: `${block.rowStart} / ${Number(block.rowEnd) + 1}`
  }
}
</script>

<style scoped lang="scss">
.image-cube {
  width: auto;
}

.cube-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 0;
  color: #c0c4cc;
  font-size: 12px;

  i {
    font-size: 32px;
    margin-bottom: 8px;
  }
}

.cube-canvas {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(4, 1fr);
  width: 100%;
  aspect-ratio: 1;
}

.cube-block,
.cube-flex-block {
  overflow: hidden;
  position: relative;
  min-height: 0;
}

.cube-flex {
  display: flex;
  flex-wrap: wrap;
}

.cube-flex-block {
  width: calc((100% - (var(--cube-columns) - 1) * var(--cube-gap)) / var(--cube-columns));
  aspect-ratio: 1;
}

.cube-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.cube-placeholder {
  width: 100%;
  height: 100%;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;

  i {
    font-size: 20px;
  }
}
</style>
