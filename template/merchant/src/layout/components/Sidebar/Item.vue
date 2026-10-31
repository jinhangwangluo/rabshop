<template>
  <div>
    <LegacyIcon
      v-if="icon"
      :name="icon"
      class="sub-el-icon"
      :class="{ 'sub-el-icon-ml-19': !isCollapse }"
    />
    <span v-if="title">{{ title }}</span>
  </div>
</template>
<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/store/modules/app'

defineOptions({
  name: 'MenuItem'
})

defineProps({
  icon: {
    type: String,
    default: ''
  },
  title: {
    type: String,
    default: ''
  }
})

const appStore = useAppStore()
const { sidebar } = storeToRefs(appStore)
const isCollapse = computed(() => !!sidebar.value.opened)
</script>

<style scoped>
.sub-el-icon {
  color: currentColor;
  width: 1em;
  height: 1em;
}

.sub-el-icon-ml-19 {
  margin-left: 19px !important;
}
</style>
