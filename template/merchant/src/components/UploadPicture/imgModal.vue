<template>
  <el-dialog v-model="visible" class="form upload-picture-dialog" width="1024px" title="选择图片" :close-on-click-modal="false">
    <UploadPictures
      v-if="visible"
      :is-choice="isMore"
      :grid-btn="gridBtn"
      :grid-pic="gridPic"
      :max-length="maxLength"
      @getPic="getImage"
    />
  </el-dialog>
</template>

<script setup>
import { computed } from 'vue'
import UploadPictures from './index.vue'
import { visible, callback, maxLength, close } from './controller'

defineOptions({
  name: 'ImgModal'
})

const gridBtn = {
  xl: 4,
  lg: 8,
  md: 8,
  sm: 8,
  xs: 8
}
const gridPic = {
  xl: 6,
  lg: 8,
  md: 12,
  sm: 12,
  xs: 12
}
const isMore = computed(() => maxLength.value > 1 ? '多选' : '单选')

function getImage(img) {
  callback.value(img)
  close()
}
</script>
<style lang="scss" scoped>

.form {
  :deep(.el-dialog__header) {
    border-bottom: 1px solid #eee;
  }

  :deep(.el-dialog__headerbtn) {
    top: 15px;
  }

  :deep(.form-button) {
    text-align: right;
  }

  :deep(.el-dialog__header) {
    padding-top: 10px;
  }

  :deep(.el-dialog__body) {
    padding: 24px 20px 0 20px !important;
  }

  :deep(.el-dialog__header) {
    .el-dialog__title {
      color: #303133;
      font-size: 14px;
      font-weight: 500;
    }
  }
}

</style>
