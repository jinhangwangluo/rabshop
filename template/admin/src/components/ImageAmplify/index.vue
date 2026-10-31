
<template>
  <div v-if="dialogVisible" tabindex="-1" class="el-image-viewer__wrapper" style="z-index: 9964;" @wheel.prevent="onWheel">
    <div class="el-image-viewer__mask" @click="closeImagePreview" />
    <span class="el-image-viewer__btn el-image-viewer__close">
      <LegacyIcon name="el-icon-close" @click="closeImagePreview" />
    </span>
    <span v-if="imagesList.length > 1" class="el-image-viewer__btn el-image-viewer__prev" @click="changeImage('prev')">
      <LegacyIcon name="el-icon-arrow-left" />
    </span>
    <span v-if="imagesList.length > 1" class="el-image-viewer__btn el-image-viewer__next" @click="changeImage('next')">
      <LegacyIcon name="el-icon-arrow-right" />
    </span>
    <div class="el-image-viewer__btn el-image-viewer__actions">
      <div class="el-image-viewer__actions__inner">
        <LegacyIcon name="el-icon-zoom-out" @click="changeImage('out')" />
        <LegacyIcon name="el-icon-zoom-in" @click="changeImage('in')" />
        <i class="el-image-viewer__actions__divider" />
        <LegacyIcon
          :name="styleHW ? 'el-icon-c-scale-to-original' : 'el-icon-full-screen'"
          @click="changeImage('screen')"
        />
        <i class="el-image-viewer__actions__divider" />
        <LegacyIcon name="el-icon-refresh-left" @click="changeImage('left')" />
        <LegacyIcon name="el-icon-refresh-right" @click="changeImage('right')" />
      </div>
    </div>
    <div class="el-image-viewer__canvas">
      <img :src="imageUrl" class="el-image-viewer__img" :style="'transform: '+styleTransform+'; margin-left: 0px; margin-top: 0px; '+styleHW">
    </div>
  </div>
</template>
<script setup>
import { ref, watch } from 'vue'

defineOptions({
  name: 'ImageAmplify'
})

const props = defineProps({
  imagesList: {
    type: Array,
    default: () => []
  },
  open: {
    type: Boolean,
    default: false
  }
})

const dialogVisible = ref(props.open)
const styleHW = ref('max-height: 100%; max-width: 100%;')
const styleTransform = ref('scale(1) rotate(0deg)')
const scale = ref(1)
const rotation = ref(0)
const imageUrl = ref('')
const imageIndex = ref(0)

watch(() => props.open, (value) => {
  dialogVisible.value = value
}, { immediate: true })

watch(() => props.imagesList, (list) => {
  imageUrl.value = Array.isArray(list) && list.length ? list[0] : ''
  imageIndex.value = 0
}, {
  immediate: true,
  deep: true
})

function changeImage(action) {
  const length = props.imagesList.length
  switch (action) {
    case 'prev':
      if (imageIndex.value === 0) {
        imageUrl.value = props.imagesList[length - 1]
        imageIndex.value = length
      } else {
        imageIndex.value--
        imageUrl.value = props.imagesList[imageIndex.value]
      }
      break
    case 'next':
      if (imageIndex.value === length) {
        imageIndex.value = 0
        imageUrl.value = props.imagesList[0]
      } else {
        imageIndex.value++
        imageUrl.value = props.imagesList[imageIndex.value]
      }
      break
    case 'out':
      scale.value -= 0.1
      styleTransform.value = getTransformStr(scale.value, rotation.value)
      break
    case 'in':
      scale.value += 0.1
      styleTransform.value = getTransformStr(scale.value, rotation.value)
      break
    case 'screen':
      styleHW.value = styleHW.value ? '' : 'max-height: 100%; max-width: 100%;'
      break
    case 'left':
      rotation.value += 90
      if ((rotation.value / 90) === 4) {
        rotation.value = 0
      }
      styleTransform.value = getTransformStr(scale.value, rotation.value)
      break
    case 'right':
      rotation.value -= 90
      if ((rotation.value / 90) === -4) {
        rotation.value = 0
      }
      styleTransform.value = getTransformStr(scale.value, rotation.value)
      break
  }
}

function getTransformStr(scaleValue, rotationValue) {
  return `scale(${scaleValue}) rotate(${rotationValue}deg)`
}

function closeImagePreview() {
  dialogVisible.value = false
  imageUrl.value = ''
  imageIndex.value = 0
  scale.value = 1
  rotation.value = 0
  styleTransform.value = 'scale(1) rotate(0deg)'
  styleHW.value = 'max-height: 100%; max-width: 100%;'
}

function onWheel(e) {
  const step = 0.1
  if (e.deltaY < 0) {
    scale.value = Math.min(scale.value + step, 5)
  } else {
    scale.value = Math.max(scale.value - step, 0.1)
  }
  styleTransform.value = getTransformStr(scale.value, rotation.value)
}

defineExpose({
  closeImagePreview,
  changeImage
})
</script>

<style scoped>
.el-image-viewer__mask {
  cursor: pointer;
}
</style>
