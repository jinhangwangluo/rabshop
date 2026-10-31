<template>
  <transition :name="props.transitionName">
    <div v-show="visible" :style="props.customStyle" class="back-to-ceiling" @click="backToTop">
      <svg width="16" height="16" viewBox="0 0 17 17" xmlns="http://www.w3.org/2000/svg" class="Icon Icon--backToTopArrow" aria-hidden="true" style="height:16px;width:16px"><path d="M12.036 15.59a1 1 0 0 1-.997.995H5.032a.996.996 0 0 1-.997-.996V8.584H1.03c-1.1 0-1.36-.633-.578-1.416L7.33.29a1.003 1.003 0 0 1 1.412 0l6.878 6.88c.782.78.523 1.415-.58 1.415h-3.004v7.004z" /></svg>
    </div>
  </transition>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

defineOptions({
  name: 'BackToTop'
})

const props = defineProps({
  visibilityHeight: {
    type: Number,
    default: 400
  },
  backPosition: {
    type: Number,
    default: 0
  },
  customStyle: {
    type: Object,
    default() {
      return {
        right: '50px',
        bottom: '50px',
        width: '40px',
        height: '40px',
        'border-radius': '4px',
        'line-height': '45px',
        background: '#e7eaf1'
      }
    }
  },
  transitionName: {
    type: String,
    default: 'fade'
  }
})

const visible = ref(false)
const interval = ref(null)
const isMoving = ref(false)

function handleScroll() {
  visible.value = window.pageYOffset > props.visibilityHeight
}

function easeInOutQuad(t, b, c, d) {
  if ((t /= d / 2) < 1) return c / 2 * t * t + b
  return -c / 2 * (--t * (t - 2) - 1) + b
}

function backToTop() {
  if (isMoving.value) return
  const start = window.pageYOffset
  let i = 0
  isMoving.value = true
  interval.value = setInterval(() => {
    const next = Math.floor(easeInOutQuad(10 * i, start, -start, 500))
    if (next <= props.backPosition) {
      window.scrollTo(0, props.backPosition)
      clearInterval(interval.value)
      isMoving.value = false
    } else {
      window.scrollTo(0, next)
    }
    i++
  }, 16.7)
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll)
  if (interval.value) {
    clearInterval(interval.value)
  }
})
</script>

<style scoped>
.back-to-ceiling {
  position: fixed;
  display: inline-block;
  text-align: center;
  cursor: pointer;
}

.back-to-ceiling:hover {
  background: #d5dbe7;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity .5s;
}

.fade-enter,
.fade-leave-to {
  opacity: 0
}

.back-to-ceiling .Icon {
  fill: #9aaabf;
  background: none;
}
</style>
