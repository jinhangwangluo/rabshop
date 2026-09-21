<template>
  <common-wrapper :config="config">
    <div class="banner-wrap">
      <!-- 有图片时：使用 el-carousel 轮播 -->
      <el-carousel
        v-if="imgSrc.length > 0"
        :interval="3000"
        :autoplay="true"
        :indicator-position="showIndicator ? 'none' : 'none'"
        :arrow="imgSrc.length > 1 ? 'hover' : 'never'"
        height="170px"
        class="banner-carousel"
        :style="carouselBorderStyle"
        @change="handleCarouselChange"
      >
        <el-carousel-item
          v-for="(item, index) in imgSrc"
          :key="index"
        >
          <div class="carousel-item-wrap" :style="imgRadiusStyle">
            <img
              v-if="item.img"
              :src="item.img"
              alt=""
              class="carousel-img"
              :style="imgRadiusStyle"
              @load="onImgLoad"
            >
            <div v-else class="empty-box" :style="imgRadiusStyle">
              <div class="thumb-empty"><LegacyIcon name="el-icon-picture-outline" /></div>
            </div>
            <!-- 渐变遮罩层 -->
            <div v-if="item.tag || item.title || item.btn_text" class="carousel-overlay">
              <div v-if="item.tag" class="overlay-tag">{{ item.tag }}</div>
              <div v-if="item.title" class="overlay-title">{{ item.title }}</div>
              <div v-if="item.btn_text" class="overlay-btn" :style="{ background: item.btn_color || '#ac2c00' }">
                {{ item.btn_text }}
              </div>
            </div>
          </div>
        </el-carousel-item>
      </el-carousel>

      <!-- 指示器（自绘，覆盖在轮播上） -->
      <div
        v-if="showIndicator && imgSrc.length > 0"
        class="custom-indicator"
        :class="indicatorPositionClass"
      >
        <!-- 数字样式 -->
        <span v-if="indicatorStyle === 'number'" class="indicator-number">
          {{ currentIndex + 1 }} / {{ imgSrc.length }}
        </span>
        <!-- 线条样式 -->
        <template v-else-if="indicatorStyle === 'line'">
          <span
            v-for="(item, i) in imgSrc"
            :key="i"
            class="indicator-line"
            :class="{ active: i === currentIndex }"
            :style="i === currentIndex ? { background: activeDotColor } : { background: dotBgColor }"
          />
        </template>
        <!-- 圆点样式（默认） -->
        <template v-else>
          <span
            v-for="(item, i) in imgSrc"
            :key="i"
            class="indicator-dot"
            :class="{ active: i === currentIndex }"
            :style="i === currentIndex ? { background: activeDotColor } : { background: dotBgColor }"
          />
        </template>
      </div>

      <!-- 无图片时：占位 -->
      <div v-if="imgSrc.length === 0" class="empty-box" :style="imgRadiusStyle">
        <div class="thumb-empty"><LegacyIcon name="el-icon-picture-outline" /></div>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'
import { getImageRadiusStyle } from './imageRadius'

defineOptions({
  name: 'Banner',
  cname: '轮播图',
  ename: 'swiperBg',
  icon: '#iconzujian-lunbotu',
  defaultName: 'swiperBg',
  configName: 'c_banner',
  type: 0
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})
const emit = defineEmits(['resize', 'change'])

const imgSrc = ref([])
const filletImg = ref(0)
const filletValImg = ref(6)
const valListImg = ref([{ val: 6 }, { val: 6 }, { val: 6 }, { val: 6 }])
const showIndicator = ref(true)
const indicatorStyle = ref('dot')
const indicatorPosition = ref('left')
const activeDotColor = ref('#ffffff')
const dotBgColor = ref('rgba(255,255,255,0.5)')
const currentIndex = ref(0)
const carouselTimer = ref(null)

const imgRadiusStyle = computed(() => {
  const styleConfig = props.config && props.config.style_config
  if (styleConfig && typeof styleConfig === 'object') {
    return getImageRadiusStyle(styleConfig)
  }
  if (filletImg.value) {
    return {
      borderRadius:
        valListImg.value[0].val + 'px ' +
        valListImg.value[1].val + 'px ' +
        valListImg.value[3].val + 'px ' +
        valListImg.value[2].val + 'px',
      overflow: 'hidden'
    }
  }
  return filletValImg.value > 0 ? { borderRadius: filletValImg.value + 'px', overflow: 'hidden' } : {}
})
const carouselBorderStyle = computed(() => ({ ...imgRadiusStyle.value }))
const indicatorPositionClass = computed(() => ({
  'pos-left': indicatorPosition.value === 'left',
  'pos-center': indicatorPosition.value === 'center',
  'pos-right': indicatorPosition.value === 'right'
}))

watch(() => props.config, setConfig, { deep: true, immediate: true })
onBeforeUnmount(stopCarouselTrack)

function safeValList(list) {
  const def = [{ val: 0 }, { val: 0 }, { val: 0 }, { val: 0 }]
  return Array.isArray(list) && list.length >= 4 ? list : def
}

function normalizeTabVal(tabVal, defaultValue = 0) {
  const nextVal = Number(tabVal)
  return Number.isNaN(nextVal) ? defaultValue : nextVal
}

function setConfig(data) {
  if (!data) return
  filletImg.value = data.filletImg ? data.filletImg.type : 0
  filletValImg.value = data.filletImg ? (data.filletImg.val || 6) : 6
  valListImg.value = safeValList(data.filletImg ? data.filletImg.valList : null)
  imgSrc.value = data.swiperConfig && data.swiperConfig.list ? data.swiperConfig.list : []

  const docTabVal = normalizeTabVal(data.docConfig ? data.docConfig.tabVal : 0)
  showIndicator.value = docTabVal !== 4
  indicatorStyle.value = docTabVal === 2 ? 'line' : docTabVal === 3 ? 'number' : 'dot'

  const posTabVal = normalizeTabVal(data.docPosition ? data.docPosition.tabVal : 0)
  indicatorPosition.value = posTabVal === 1 ? 'center' : posTabVal === 2 ? 'right' : 'left'
  activeDotColor.value = data.dotColor && data.dotColor.color ? data.dotColor.color[0].item : '#ffffff'
  dotBgColor.value = data.dotBgColor && data.dotBgColor.color ? data.dotBgColor.color[0].item : 'rgba(255,255,255,0.5)'

  setCurrentIndex(0)
  stopCarouselTrack()
  nextTick(startCarouselTrack)
}

function onImgLoad() {
  nextTick(() => emit('resize'))
}

function startCarouselTrack() {
  if (imgSrc.value.length <= 1) return
  carouselTimer.value = setInterval(() => {
    setCurrentIndex((currentIndex.value + 1) % imgSrc.value.length)
  }, 3000)
}

function handleCarouselChange(index) {
  setCurrentIndex(index)
}

function setCurrentIndex(index) {
  const nextIndex = Math.max(Number(index || 0), 0)
  currentIndex.value = imgSrc.value.length > 0 ? nextIndex % imgSrc.value.length : 0
  emit('change', currentIndex.value)
}

function stopCarouselTrack() {
  if (carouselTimer.value) {
    clearInterval(carouselTimer.value)
    carouselTimer.value = null
  }
}
</script>

<style scoped lang="scss">
.banner-wrap {
  width: auto;
  position: relative;
  overflow: hidden;
}

.banner-carousel {
  width: 100%;

  :deep(.el-carousel__container) {
    height: 170px !important;
  }

  /* 隐藏 el-carousel 自带指示器，改用自绘 */
  :deep(.el-carousel__indicators) {
    display: none !important;
  }
}

.carousel-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.carousel-item-wrap {
  position: relative;
  width: 100%;
  height: 100%;
}

.carousel-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.65) 0%, rgba(0, 0, 0, 0.2) 50%, transparent 100%);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 16px;
  box-sizing: border-box;
}

.overlay-tag {
  display: inline-block;
  align-self: flex-start;
  padding: 4px 10px;
  background: rgba(255, 255, 255, 0.9);
  color: #333;
  font-size: 11px;
  font-weight: 600;
  border-radius: 12px;
  margin-bottom: 8px;
}

.overlay-title {
  font-size: 18px;
  font-weight: 600;
  color: #fff;
  line-height: 1.4;
  margin-bottom: 12px;
  max-width: 80%;
}

.overlay-btn {
  display: inline-block;
  align-self: flex-start;
  padding: 8px 20px;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  border-radius: 16px;
  background: #ac2c00;
  cursor: pointer;
  transition: transform 0.2s;

  &:active {
    transform: scale(0.96);
  }
}

/* 自绘指示器 */
.custom-indicator {
  position: absolute;
  bottom: 8px;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 0 10px;
  pointer-events: none;
  z-index: 10;

  &.pos-left   { justify-content: flex-start; }
  &.pos-center { justify-content: center; }
  &.pos-right  { justify-content: flex-end; }
}

.indicator-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
  transition: all 0.3s;

  &.active {
    width: 16px;
    border-radius: 3px;
  }
}

.indicator-line {
  width: 16px;
  height: 3px;
  border-radius: 2px;
  background: rgba(255, 255, 255, 0.5);
  transition: background 0.3s;
}

.indicator-number {
  font-size: 11px;
  color: #fff;
  background: rgba(0, 0, 0, 0.35);
  border-radius: 10px;
  padding: 1px 8px;
  line-height: 18px;
}

.empty-box {
  height: 170px;
  width: 100%;
  background-color: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;

  .thumb-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c0c4cc;

    .legacy-icon {
      font-size: 36px;
    }
  }
}
</style>
