<template>
  <div :id="id" ref="dropzoneEl" :action="url" class="dropzone">
    <input type="file" name="file">
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import DropzoneLib from 'dropzone'
import 'dropzone/dist/dropzone.css'
// import { getToken } from 'api/qiniu';

DropzoneLib.autoDiscover = false

const props = defineProps({
  id: {
    type: String,
    required: true
  },
  url: {
    type: String,
    required: true
  },
  clickable: {
    type: Boolean,
    default: true
  },
  defaultMsg: {
    type: String,
    default: '上传图片'
  },
  acceptedFiles: {
    type: String,
    default: ''
  },
  thumbnailHeight: {
    type: Number,
    default: 200
  },
  thumbnailWidth: {
    type: Number,
    default: 200
  },
  showRemoveLink: {
    type: Boolean,
    default: true
  },
  maxFilesize: {
    type: Number,
    default: 2
  },
  maxFiles: {
    type: Number,
    default: 3
  },
  autoProcessQueue: {
    type: Boolean,
    default: true
  },
  useCustomDropzoneOptions: {
    type: Boolean,
    default: false
  },
  defaultImg: {
    default: '',
    type: [String, Array]
  },
  couldPaste: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'dropzone-success',
  'dropzone-fileAdded',
  'dropzone-removedFile',
  'dropzone-error',
  'dropzone-successmultiple'
])

const dropzoneEl = ref(null)
const dropzone = ref(null)
const initOnce = ref(true)

watch(() => props.defaultImg, (val) => {
  if (!val || val.length === 0) {
    initOnce.value = false
    return
  }
  if (!initOnce.value || !dropzone.value) return
  initImages(val)
  initOnce.value = false
})

onMounted(() => {
  const element = dropzoneEl.value || document.getElementById(props.id)
  if (!element) return
  dropzone.value = new DropzoneLib(element, {
    clickable: props.clickable,
    thumbnailWidth: props.thumbnailWidth,
    thumbnailHeight: props.thumbnailHeight,
    maxFiles: props.maxFiles,
    maxFilesize: props.maxFilesize,
    dictRemoveFile: 'Remove',
    addRemoveLinks: props.showRemoveLink,
    acceptedFiles: props.acceptedFiles,
    autoProcessQueue: props.autoProcessQueue,
    dictDefaultMessage: '<i style="margin-top: 3em;display: inline-block" class="material-icons">' + props.defaultMsg + '</i><br>Drop files here to upload',
    dictMaxFilesExceeded: '只能一个图',
    previewTemplate: '<div class="dz-preview dz-file-preview">  <div class="dz-image" style="width:' + props.thumbnailWidth + 'px;height:' + props.thumbnailHeight + 'px" ><img style="width:' + props.thumbnailWidth + 'px;height:' + props.thumbnailHeight + 'px" data-dz-thumbnail /></div>  <div class="dz-details"><div class="dz-size"><span data-dz-size></span></div> <div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>  <div class="dz-error-message"><span data-dz-errormessage></span></div>  <div class="dz-success-mark"> <i class="material-icons">done</i> </div>  <div class="dz-error-mark"><i class="material-icons">error</i></div></div>',
    init() {
      const val = props.defaultImg
      if (!val) return
      if (Array.isArray(val)) {
        if (val.length === 0) return
        val.map((v, i) => {
          const mockFile = { name: 'name' + i, size: 12345, url: v }
          this.options.addedfile.call(this, mockFile)
          this.options.thumbnail.call(this, mockFile, v)
          mockFile.previewElement.classList.add('dz-success')
          mockFile.previewElement.classList.add('dz-complete')
          initOnce.value = false
          return true
        })
      } else {
        const mockFile = { name: 'name', size: 12345, url: val }
        this.options.addedfile.call(this, mockFile)
        this.options.thumbnail.call(this, mockFile, val)
        mockFile.previewElement.classList.add('dz-success')
        mockFile.previewElement.classList.add('dz-complete')
        initOnce.value = false
      }
    },
    accept: (file, done) => {
      /* 七牛*/
      done()
    },
    sending: (file, xhr, formData) => {
      // formData.append('token', file.token);
      // formData.append('key', file.key);
      initOnce.value = false
    }
  })

  if (props.couldPaste) {
    document.addEventListener('paste', pasteImg)
  }

  dropzone.value.on('success', file => {
    emit('dropzone-success', file, dropzone.value.element)
  })
  dropzone.value.on('addedfile', file => {
    emit('dropzone-fileAdded', file)
  })
  dropzone.value.on('removedfile', file => {
    emit('dropzone-removedFile', file)
  })
  dropzone.value.on('error', (file, error, xhr) => {
    emit('dropzone-error', file, error, xhr)
  })
  dropzone.value.on('successmultiple', (file, error, xhr) => {
    emit('dropzone-successmultiple', file, error, xhr)
  })
})

onBeforeUnmount(() => {
  document.removeEventListener('paste', pasteImg)
  if (dropzone.value) {
    dropzone.value.destroy()
    dropzone.value = null
  }
})

function removeAllFiles() {
  if (dropzone.value) {
    dropzone.value.removeAllFiles(true)
  }
}

function processQueue() {
  if (dropzone.value) {
    dropzone.value.processQueue()
  }
}

function pasteImg(event) {
  const items = (event.clipboardData || event.originalEvent.clipboardData).items
  if (dropzone.value && items[0].kind === 'file') {
    dropzone.value.addFile(items[0].getAsFile())
  }
}

function initImages(val) {
  if (!val || !dropzone.value) return
  if (Array.isArray(val)) {
    val.map((v, i) => {
      const mockFile = { name: 'name' + i, size: 12345, url: v }
      dropzone.value.options.addedfile.call(dropzone.value, mockFile)
      dropzone.value.options.thumbnail.call(dropzone.value, mockFile, v)
      mockFile.previewElement.classList.add('dz-success')
      mockFile.previewElement.classList.add('dz-complete')
      return true
    })
  } else {
    const mockFile = { name: 'name', size: 12345, url: val }
    dropzone.value.options.addedfile.call(dropzone.value, mockFile)
    dropzone.value.options.thumbnail.call(dropzone.value, mockFile, val)
    mockFile.previewElement.classList.add('dz-success')
    mockFile.previewElement.classList.add('dz-complete')
  }
}

defineExpose({
  dropzone,
  removeAllFiles,
  processQueue
})
</script>

<style scoped>
    .dropzone {
        border: 2px solid #E5E5E5;
        font-family: 'Roboto', sans-serif;
        color: #777;
        transition: background-color .2s linear;
        padding: 5px;
    }

    .dropzone:hover {
        background-color: #F6F6F6;
    }

    i {
        color: #CCC;
    }

    .dropzone .dz-image img {
        width: 100%;
        height: 100%;
    }

    .dropzone input[name='file'] {
        display: none;
    }

    .dropzone .dz-preview .dz-image {
        border-radius: 0px;
    }

    .dropzone .dz-preview:hover .dz-image img {
        transform: none;
        filter: none;
        width: 100%;
        height: 100%;
    }

    .dropzone .dz-preview .dz-details {
        bottom: 0px;
        top: 0px;
        color: white;
        background-color: rgba(33, 150, 243, 0.8);
        transition: opacity .2s linear;
        text-align: left;
    }

    .dropzone .dz-preview .dz-details .dz-filename span, .dropzone .dz-preview .dz-details .dz-size span {
        background-color: transparent;
    }

    .dropzone .dz-preview .dz-details .dz-filename:not(:hover) span {
        border: none;
    }

    .dropzone .dz-preview .dz-details .dz-filename:hover span {
        background-color: transparent;
        border: none;
    }

    .dropzone .dz-preview .dz-remove {
        position: absolute;
        z-index: 30;
        color: white;
        margin-left: 15px;
        padding: 10px;
        top: inherit;
        bottom: 15px;
        border: 2px white solid;
        text-decoration: none;
        text-transform: uppercase;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 1.1px;
        opacity: 0;
    }

    .dropzone .dz-preview:hover .dz-remove {
        opacity: 1;
    }

    .dropzone .dz-preview .dz-success-mark, .dropzone .dz-preview .dz-error-mark {
        margin-left: -40px;
        margin-top: -50px;
    }

    .dropzone .dz-preview .dz-success-mark i, .dropzone .dz-preview .dz-error-mark i {
        color: white;
        font-size: 5rem;
    }
</style>
