<template>
  <div>
    <image-upload
      v-if="limit <= 1"
      :size="size"
      :file-type="fileType"
      :image-uri="modelValue"
      @changUpload="changUpload"
      @removeUpload="removeUpload"
    />
    <images-upload
      v-else
      :limit="limit"
      :file-type="fileType"
      :image-uris="modelValue"
      @changUpload="changUpload"
      @removeUpload="removeUpload"
    />
  </div>
</template>

<script setup>
import ImageUpload from './components/Image'
import ImagesUpload from './components/Images'

defineOptions({
  name: 'Upload'
})

defineProps({
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
  },
  modelValue: {
    type: [String, Array],
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

function changUpload(data) {
  emit('update:modelValue', data)
}

function removeUpload(data) {
  emit('update:modelValue', data)
  console.log(data)
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
