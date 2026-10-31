<template>
  <div>
    <el-upload
      v-if="!image"
      class="avatar-uploader"
      :action="uri"
      :show-file-list="false"
      :headers="header"
      :on-success="handleAvatarSuccess"
      :before-upload="beforeAvatarUpload"
    >
      <LegacyIcon name="el-icon-camera-solid" class="avatar-uploader-icon" />
    </el-upload>
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
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import setting from '@/settings'
import { getToken } from '@/utils/auth'

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
const uri = setting.baseUri + '/setting/file/upload'
const header = {
  'X-Requested-With': 'XMLHttpRequest',
  'Authorization': 'Bearer ' + getToken()
}
const image = ref('')

watch(() => props.imageUri, value => {
  image.value = value || ''
}, { immediate: true })

function remove() {
  image.value = ''
  emit('removeUpload', '')
}

function handleAvatarSuccess(data) {
  if (data.status === 200) {
    const url = data.data.filePath
    image.value = url
    emit('changUpload', url)
  } else {
    ElMessage.error(data.msg)
  }
}

function beforeAvatarUpload(file) {
  const isJPG = props.fileType.indexOf(file.type) !== -1
  const isLt2M = file.size / 1024 / 1024 < props.size

  if (!isJPG) {
    ElMessage.error('上传头像图片只能是 JPG、PNG、GIF 格式!')
  }
  if (!isLt2M) {
    ElMessage.error('上传头像图片大小不能超过 2MB!')
  }
  return isJPG && isLt2M
}
</script>

<style scoped>

.el-upload .close{
  position: absolute;
  right: 0;
  top: 0;
  z-index: 10;
  font-size: 20px;
  cursor: pointer;
}
</style>
