<template>
  <div>
    <FullScreen class="screenfull-icon" @click="click" />
  </div>
</template>

<script setup>
import { ElMessage } from 'element-plus'
import { FullScreen } from '@element-plus/icons-vue'
import screenfull from 'screenfull'

defineOptions({
  name: 'Screenfull'
})

function click() {
  if (!screenfull.enabled) {
    ElMessage.warning('you browser can not work')
    return false
  }
  screenfull.toggle()
}
</script>

<style scoped>
.screenfull-icon {
  display: inline-block;
  cursor: pointer;
  color: currentColor;
  width: 20px;
  height: 20px;
  vertical-align: middle;
}
</style>
