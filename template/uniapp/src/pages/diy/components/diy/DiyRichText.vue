<template>
  <view class="diy-rich-text">
    <rich-text :nodes="convertedContent" />
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { convertHtmlPxToRpx } from '@/utils/diy_adapter'

defineOptions({
  name: 'DiyRichText'
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

const convertedContent = computed(() => {
  return convertHtmlPxToRpx(props.config.content || '')
})
</script>

<style lang="scss" scoped>
.diy-rich-text {
  width: auto;
  
  ::v-deep img {
    max-width: 100%;
    height: auto;
  }
  
  ::v-deep video {
    max-width: 100%;
  }
}
</style>
