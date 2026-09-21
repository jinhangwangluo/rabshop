<template>
  <div v-if="isExternal" v-bind="$attrs" :style="styleExternalIcon" class="svg-external-icon svg-icon" />
  <svg v-else v-bind="$attrs" :class="svgClass" aria-hidden="true">
    <use :xlink:href="iconName" />
  </svg>
</template>

<script setup>
import { computed } from 'vue'
// doc: https://panjiachen.github.io/vue-element-admin-site/feature/component/svg-icon.html#usage
import { isExternal as isExternalPath } from '@/utils/validate'

defineOptions({
  name: 'SvgIcon'
})

const { iconClass, className } = defineProps({
  iconClass: {
    type: String,
    required: true
  },
  className: {
    type: String,
    default: ''
  }
})

const isExternal = computed(() => isExternalPath(iconClass))

const iconName = computed(() => `#icon-${iconClass}`)

const svgClass = computed(() => (className ? `svg-icon ${className}` : 'svg-icon'))

const styleExternalIcon = computed(() => ({
  mask: `url(${iconClass}) no-repeat 50% 50%`,
  '-webkit-mask': `url(${iconClass}) no-repeat 50% 50%`
}))
</script>

<style scoped>
.svg-icon {
  width: 1em;
  height: 1em;
  vertical-align: -0.15em;
  fill: currentColor;
  overflow: hidden;
}

.svg-external-icon {
  background-color: currentColor;
  mask-size: cover!important;
  display: inline-block;
}
</style>
