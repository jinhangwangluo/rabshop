<template>
  <div ref="rootRef" :style="{height:height+'px',zIndex:zIndex}">
    <div
      :class="className"
      :style="{top:(isSticky ? stickyTop +'px' : ''),zIndex:zIndex,position:position,width:width,height:height+'px'}"
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

const rootRef = ref(null)
const active = ref(false)
const position = ref('')
const width = ref(undefined)
const height = ref(undefined)
const isSticky = ref(false)

function sticky() {
  if (active.value) {
    return
  }
  position.value = 'fixed'
  active.value = true
  width.value = width.value + 'px'
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
  if (!rootRef.value) {
    return
  }
  const rect = rootRef.value.getBoundingClientRect()
  width.value = rect.width || 'auto'
  if (rect.top < props.stickyTop) {
    sticky()
    return
  }
  handleReset()
}

function handleResize() {
  if (isSticky.value && rootRef.value) {
    width.value = rootRef.value.getBoundingClientRect().width + 'px'
  }
}

onMounted(() => {
  if (rootRef.value) {
    height.value = rootRef.value.getBoundingClientRect().height
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
