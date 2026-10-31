<template>
  <div>
    <!--    此处 特殊需求 title 不显示的 自己额外用icon 实现关闭事件-->
    <el-dialog
      v-model="visible"
      title="上传图片"
      width="950px"
      :modal="booleanVal"
      append-to-body
      :before-close="handleClose"
    >
      <el-button
        class="selfDialogClose"
        type="text"
        icon="el-icon-close"
        circle
        size="medium"
        @click="handleClose"
      />
      <upload-picture
        v-if="visible"
        v-hasPermi="['merchant:upload:image']"
        :is-more="isMore"
        :model-name="modelName"
        @getImage="getImage"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import UploadPicture from '@/components/UploadPicture/index'

defineOptions({
  name: 'UploadFroms'
})

const visible = ref(false)
const callback = ref(() => {})
const isMore = ref('')
const modelName = ref('')
const ISmodal = ref(false)
const booleanVal = ref(false)

function handleClose() {
  visible.value = false
  callback.value(visible.value)
}

function getImage(img) {
  callback.value(img)
  visible.value = false
}

defineExpose({
  visible,
  callback,
  isMore,
  modelName,
  ISmodal,
  booleanVal,
  handleClose,
  getImage
})
</script>

<style scoped>
/* 统一组件中的特殊组件 */
:deep(.el-dialog__header) {
  display: none !important;
}
:deep(.el-dialog__body) {
  padding: 2px 20px 0 20px !important;
}
.selfDialogClose {
  display: inline-block;
  position: absolute;
  right: 0;
  top: 3px;
  pointer-events: auto;
  z-index: 999;
  font-size: 20px;
  color: #363f4d;
}
</style>
