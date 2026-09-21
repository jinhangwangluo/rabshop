<template>
  <div class="flex">
    <div ref="selection" class="flex flex-wrap-gap">
      <div
        v-for="(item,index) in images"
        :key="index"
        class="avatar-uploader upload-ml10"
      >
        <div class="el-upload image-item" @mouseenter="hoverIndex = index" @mouseleave="hoverIndex = -1">
          <el-image
            :src="item"
            class="avatar"
          />
          <transition name="fade">
            <div v-show="hoverIndex === index" class="image-mask">
              <LegacyIcon name="el-icon-view" @click.stop="preview(item)" />
            </div>
          </transition>
          <LegacyIcon name="el-icon-circle-close" class="close" @click="remove(index)" />
        </div>
      </div>
      <div
        v-if="images.length < limit"
        class="avatar-uploader upload-add-btn"
        :class="images.length ? 'ml10':''"
        @click="handleUpload"
      >
        <div class="el-upload">
          <LegacyIcon name="el-icon-camera-solid" class="avatar-uploader-icon" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Sortable from 'sortablejs'

defineOptions({
  name: 'ImagesUpload'
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
  imageUris: {
    type: Array,
    default() {
      return []
    }
  },
  limit: {
    type: Number,
    default: 1
  }
})

const emit = defineEmits(['changUpload', 'removeUpload'])
const { proxy } = getCurrentInstance() || {}
const selection = ref(null)
const images = ref([])
const hoverIndex = ref(-1)
let sortable = null

watch(() => props.imageUris, value => {
  images.value = JSON.parse(JSON.stringify(value || []))
}, { deep: true, immediate: true })

onMounted(() => {
  setSort()
})

onBeforeUnmount(() => {
  if (sortable) {
    sortable.destroy()
    sortable = null
  }
})

function handleUpload() {
  if (!proxy || typeof proxy.$imgModal !== 'function') {
    return
  }
  proxy.$imgModal((result) => {
    result.map(img => {
      images.value.push(img.path)
    })
    emit('changUpload', images.value)
  }, props.limit)
}

function setSort() {
  if (!selection.value) {
    return
  }
  sortable = Sortable.create(selection.value, {
    ghostClass: 'sortable-ghost',
    handle: '.avatar',
    animation: 150,
    filter: '.upload-add-btn',
    setData(dataTransfer) {
      dataTransfer.setData('Text', '')
    },
    onEnd: (evt) => {
      const { oldIndex, newIndex } = evt
      if (oldIndex === newIndex) return

      const el = selection.value
      const children = Array.from(el.children)
      if (newIndex > oldIndex) {
        el.insertBefore(children[newIndex], children[oldIndex])
      } else {
        el.insertBefore(children[newIndex], children[oldIndex].nextSibling)
      }

      const value = JSON.parse(JSON.stringify(images.value))
      const moved = value.splice(oldIndex, 1)[0]
      value.splice(newIndex, 0, moved)
      images.value = value
      emit('changUpload', images.value)
    }
  })
}

function remove(index) {
  images.value.splice(index, 1)
  emit('removeUpload', images.value)
}

function preview(url) {
  if (!proxy || typeof proxy.$imageAmplify !== 'function') {
    return
  }
  proxy.$imageAmplify(url)
}

function handleAvatarSuccess(data) {
  if (data.status === 200) {
    const url = data.data.filePath
    images.value.push(url)
    emit('changUpload', images.value)
  } else if (proxy && proxy.$message) {
    proxy.$message.error(data.message)
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

/* 多图添加按钮的图标框尺寸：与 Image.vue 单图一致。
   需高于全局 .avatar-uploader 样式与 LegacyIcon 内部 .legacy-icon
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
  overflow: hidden;
}

.flex-wrap-gap{
  flex-wrap: wrap;
  gap: 10px;
}

.avatar-uploader:not(.upload-add-btn) .avatar {
  cursor: grab;
}

.avatar-uploader:not(.upload-add-btn) .avatar:active {
  cursor: grabbing;
}

.sortable-ghost {
  opacity: 0.4;
  border: 2px dashed #409eff;
  border-radius: 6px;
}

.image-item {
  position: relative;
}

.image-mask {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 5;
  cursor: default;
}

.image-mask .el-icon-view {
  font-size: 22px;
  color: #fff;
  cursor: pointer;
  transition: transform 0.2s;
}

.image-mask .el-icon-view:hover {
  transform: scale(1.2);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter,
.fade-leave-to {
  opacity: 0;
}

</style>
