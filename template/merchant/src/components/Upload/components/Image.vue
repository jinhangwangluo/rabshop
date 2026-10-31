<template>
  <div>
    <div
      v-if="!image"
      class="avatar-uploader"
      @click="handleUpload"
    >
      <div class="el-upload">
        <LegacyIcon name="el-icon-camera-solid" class="avatar-uploader-icon" />
      </div>
    </div>
    <div
      v-else
      class="avatar-uploader"
    >
      <div class="el-upload">
        <el-image
          :src="image"
          class="avatar"
          :preview-src-list="[image]"
        />
        <LegacyIcon name="el-icon-circle-close" class="close" @click="remove" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, ref, watch } from 'vue'

defineOptions({
  name: 'ImageUpload'
})

const props = defineProps({
  size: {
    type: Number,
    default: 2
  },
  fileType: {
    type: Array,
    default: () => {
      return ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']
    }
  },
  imageUri: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['removeUpload', 'changUpload'])
const { proxy } = getCurrentInstance() || {}
const image = ref('')

watch(() => props.imageUri, value => {
  image.value = value || ''
}, { immediate: true })

function remove() {
  image.value = ''
  emit('removeUpload', '')
}

function handleUpload() {
  if (!proxy || typeof proxy.$imgModal !== 'function') {
    return
  }
  proxy.$imgModal((result) => {
    image.value = result.path
    emit('changUpload', image.value)
  }, 1)
}

function handleAvatarSuccess(data) {
  if (data.status === 200) {
    const url = data.data.filePath
    image.value = url
    emit('changUpload', url)
  } else if (proxy && proxy.$message) {
    proxy.$message.error(data.msg)
  }
}

function beforeAvatarUpload(file) {
  const isJPG = props.fileType.indexOf(file.type) !== -1
  const isLt2M = file.size / 1024 / 1024 < props.size

  if (!isJPG && proxy && proxy.$message) {
    proxy.$message.error('上传头像图片只能是 JPG、PNG、GIF 格式!')
  }
  if (!isLt2M && proxy && proxy.$message) {
    proxy.$message.error('上传头像图片大小不能超过 2MB!')
  }
  return isJPG && isLt2M
}

defineExpose({
  handleAvatarSuccess,
  beforeAvatarUpload
})
</script>

<style scoped>

/* 头像框尺寸：需高于全局 .avatar-uploader 样式与 LegacyIcon 内部 .legacy-icon
   （width/height: 1em）的特异性，否则图标会被压缩成 28px */
.avatar-uploader .avatar-uploader-icon {
  font-size: 28px;
  color: #8c939d;
  width: 58px;
  height: 58px;
  line-height: 58px;
  text-align: center;
}

.avatar-uploader .avatar {
  width: 58px;
  height: 58px;
  display: block;
}

.el-upload .close {
  position: absolute;
  right: -7px;
  top: -7px;
  background-color: #ffffff;
  border-radius: 50%;
  z-index: 10;
  font-size: 20px;
  cursor: pointer;
}
</style>
