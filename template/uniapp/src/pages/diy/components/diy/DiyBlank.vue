<template>
  <view class="diy-blank" :style="blankStyle" />
</template>

<script setup>
import { computed } from 'vue'
import { toRpxUnit } from '@/utils/diy_adapter'

defineOptions({
  name: 'DiyBlank'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  }
})

const blankStyle = computed(() => {
  const style = {
    height: toRpxUnit(props.config.height, 30)
  }
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  return style
})
</script>

<style lang="scss" scoped>
.diy-blank {
  width: auto;
}
</style>
