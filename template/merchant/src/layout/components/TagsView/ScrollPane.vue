<template>
  <el-scrollbar ref="scrollContainer" :vertical="false" class="scroll-container" @wheel.prevent="handleScroll">
    <slot />
  </el-scrollbar>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const tagAndTagSpacing = 4 // tagAndTagSpacing

defineOptions({
  name: 'ScrollPane'
})

const emit = defineEmits(['scroll'])
const scrollContainer = ref(null)

function getScrollWrapper() {
  const scrollbar = scrollContainer.value
  return scrollbar && (scrollbar.wrapRef || (scrollbar.$refs && scrollbar.$refs.wrap))
}

function handleScroll(e) {
  const eventDelta = e.wheelDelta || -e.deltaY * 40
  const scrollWrapper = getScrollWrapper()
  if (scrollWrapper) {
    scrollWrapper.scrollLeft = scrollWrapper.scrollLeft + eventDelta / 4
  }
}

function emitScroll() {
  emit('scroll')
}

function moveToTarget(currentTag, tagList = []) {
  const container = scrollContainer.value && scrollContainer.value.$el
  const scrollWrapper = getScrollWrapper()
  if (!container || !scrollWrapper || !currentTag || !tagList.length) {
    return
  }

  const containerWidth = container.offsetWidth
  const firstTag = tagList[0]
  const lastTag = tagList[tagList.length - 1]

  if (firstTag === currentTag) {
    scrollWrapper.scrollLeft = 0
    return
  }

  if (lastTag === currentTag) {
    scrollWrapper.scrollLeft = scrollWrapper.scrollWidth - containerWidth
    return
  }

  const currentIndex = tagList.findIndex(item => item === currentTag)
  const prevTag = tagList[currentIndex - 1]
  const nextTag = tagList[currentIndex + 1]
  if (!prevTag || !nextTag) {
    return
  }

  const afterNextTagOffsetLeft = nextTag.offsetLeft + nextTag.offsetWidth + tagAndTagSpacing
  const beforePrevTagOffsetLeft = prevTag.offsetLeft - tagAndTagSpacing

  if (afterNextTagOffsetLeft > scrollWrapper.scrollLeft + containerWidth) {
    scrollWrapper.scrollLeft = afterNextTagOffsetLeft - containerWidth
  } else if (beforePrevTagOffsetLeft < scrollWrapper.scrollLeft) {
    scrollWrapper.scrollLeft = beforePrevTagOffsetLeft
  }
}

onMounted(() => {
  const scrollWrapper = getScrollWrapper()
  if (scrollWrapper) {
    scrollWrapper.addEventListener('scroll', emitScroll, true)
  }
})

onBeforeUnmount(() => {
  const scrollWrapper = getScrollWrapper()
  if (scrollWrapper) {
    scrollWrapper.removeEventListener('scroll', emitScroll, true)
  }
})

defineExpose({
  moveToTarget
})
</script>

<style lang="scss" scoped>
.scroll-container {
  white-space: nowrap;
  position: relative;
  overflow: hidden;
  width: 100%;
  :deep(.el-scrollbar__bar) {
    bottom: 0px;
  }
  :deep(.el-scrollbar__wrap) {
    height: 49px;
  }
}
</style>
