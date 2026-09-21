<template>
  <div ref="stickyElement" :style="{height:height+'px',zIndex:props.zIndex}">
    <div
      :class="props.className"
      :style="{top:(isSticky ? props.stickyTop +'px' : ''),zIndex:props.zIndex,position:position,width:width,height:height+'px'}"
    >
      <slot>
        <div>sticky</div>
      </slot>
    </div>
  </div>
</template>

<script setup>
import { onActivated, onBeforeUnmount, onMounted, ref } from 'vue'

defineOptions({
  name: 'Sticky'
})

const props = defineProps({
  stickyTop: {
    type: Number,
    default: 0
  },
  zIndex: {
    type: Number,
    default: 1
  },
  className: {
    type: String,
    default: ''
  }
})

const stickyElement = ref(null)
const active = ref(false)
const position = ref('')
const width = ref()
const height = ref()
const isSticky = ref(false)

function sticky() {
  if (active.value) {
    return
  }
  position.value = 'fixed'
  active.value = true
  isSticky.value = true
}

function handleReset() {
  if (!active.value) {
    return
  }
  reset()
}

function reset() {
  position.value = ''
  width.value = 'auto'
  active.value = false
  isSticky.value = false
}

function handleScroll() {
  const element = stickyElement.value
  if (!element) return

  const rect = element.getBoundingClientRect()
  width.value = rect.width ? `${rect.width}px` : 'auto'
  if (rect.top < props.stickyTop) {
    sticky()
    return
  }
  handleReset()
}

function handleResize() {
  if (isSticky.value && stickyElement.value) {
    width.value = `${stickyElement.value.getBoundingClientRect().width}px`
  }
}

onMounted(() => {
  if (stickyElement.value) {
    height.value = stickyElement.value.getBoundingClientRect().height
  }
  window.addEventListener('scroll', handleScroll)
  window.addEventListener('resize', handleResize)
})

onActivated(() => {
  handleScroll()
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll)
  window.removeEventListener('resize', handleResize)
})
</script>
