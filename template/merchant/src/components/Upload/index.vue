<template>
  <div>
    <image-upload
      v-if="limit <= 1"
      :size="size"
      :file-type="fileType"
      :image-uri="uploadValue"
      @changUpload="changUpload"
      @removeUpload="removeUpload"
    />
    <images-upload
      v-else
      :limit="limit"
      :file-type="fileType"
      :image-uris="uploadValue"
      @changUpload="changUpload"
      @removeUpload="removeUpload"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ImageUpload from './components/Image'
import ImagesUpload from './components/Images'

defineOptions({
  name: 'Upload'
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
    default: undefined
  },
  value: {
    type: [String, Array],
    default: undefined
  }
})

const emit = defineEmits(['update:modelValue', 'input'])
const uploadValue = computed(() => {
  if (typeof props.modelValue !== 'undefined') {
    return props.modelValue
  }
  if (typeof props.value !== 'undefined') {
    return props.value
  }
  return props.limit <= 1 ? '' : []
})

function emitUpload(data) {
  emit('update:modelValue', data)
  emit('input', data)
}

function changUpload(data) {
  emitUpload(data)
}

function removeUpload(data) {
  emitUpload(data)
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
