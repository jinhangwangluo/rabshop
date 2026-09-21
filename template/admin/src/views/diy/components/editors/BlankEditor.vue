<template>
  <div class="blank-editor">
    <el-form :model="localConfig" label-width="100px">
      <el-form-item label="组件名称">
        <el-input v-model="localConfig.title" placeholder="空白间隔" />
      </el-form-item>
      <el-form-item label="高度">
        <el-slider v-model="localConfig.height" :min="10" :max="100" :step="5" show-input />
        <span class="tips">单位：rpx</span>
      </el-form-item>
      <el-form-item label="背景颜色">
        <el-color-picker v-model="localConfig.bg_color" show-alpha />
        <el-input v-model="localConfig.bg_color" placeholder="transparent" style="width: 150px; margin-left: 10px" />
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'

defineOptions({
  name: 'BlankEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const localConfig = reactive({
  title: '空白间隔',
  height: 30,
  bg_color: 'transparent'
})
const syncingFromProp = ref(false)

watch(() => props.config, val => {
  if (val && Object.keys(val).length > 0) {
    syncingFromProp.value = true
    Object.assign(localConfig, {
      title: '空白间隔',
      height: 30,
      bg_color: 'transparent',
      ...val
    })
    syncingFromProp.value = false
  }
}, {
  immediate: true,
  deep: true
})

watch(localConfig, val => {
  if (syncingFromProp.value) return
  emit('change', val)
}, {
  deep: true
})

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.blank-editor {
  .tips {
    font-size: 12px;
    color: #909399;
    margin-left: 10px;
  }
}
</style>
