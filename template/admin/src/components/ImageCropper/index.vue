<template>
  <div v-show="modelValue" class="vue-image-crop-upload">
    <div class="vicp-wrap">
      <div class="vicp-close" @click="off">
        <i class="vicp-icon4" />
      </div>

      <div v-show="step == 1" class="vicp-step1">
        <div
          class="vicp-drop-area"
          @dragleave="preventDefault"
          @dragover="preventDefault"
          @dragenter="preventDefault"
          @click="handleClick"
          @drop="handleChange"
        >
          <i v-show="loading != 1" class="vicp-icon1">
            <i class="vicp-icon1-arrow" />
            <i class="vicp-icon1-body" />
            <i class="vicp-icon1-bottom" />
          </i>
          <span v-show="loading !== 1" class="vicp-hint">{{ lang.hint }}</span>
          <span v-show="!isSupported" class="vicp-no-supported-hint">{{ lang.noSupported }}</span>
          <input v-show="false" v-if="step == 1" ref="fileinput" type="file" @change="handleChange">
        </div>
        <div v-show="hasError" class="vicp-error">
          <i class="vicp-icon2" />
          {{ errorMsg }}
        </div>
        <div class="vicp-operate">
          <a @click="off" @mousedown="ripple">{{ lang.btn.off }}</a>
        </div>
      </div>

      <div v-if="step == 2" class="vicp-step2">
        <div class="vicp-crop">
          <div v-show="true" class="vicp-crop-left">
            <div class="vicp-img-container">
              <img
                ref="img"
                :src="sourceImgUrl"
                :style="sourceImgStyle"
                class="vicp-img"
                draggable="false"
                @drag="preventDefault"
                @dragstart="preventDefault"
                @dragend="preventDefault"
                @dragleave="preventDefault"
                @dragover="preventDefault"
                @dragenter="preventDefault"
                @drop="preventDefault"
                @touchstart="imgStartMove"
                @touchmove="imgMove"
                @touchend="createImg"
                @touchcancel="createImg"
                @mousedown="imgStartMove"
                @mousemove="imgMove"
                @mouseup="createImg"
                @mouseout="createImg"
              >
              <div :style="sourceImgShadeStyle" class="vicp-img-shade vicp-img-shade-1" />
              <div :style="sourceImgShadeStyle" class="vicp-img-shade vicp-img-shade-2" />
            </div>

            <div class="vicp-range">
              <input
                :value="scale.range"
                type="range"
                step="1"
                min="0"
                max="100"
                @input="zoomChange"
              >
              <i
                class="vicp-icon5"
                @mousedown="startZoomSub"
                @mouseout="endZoomSub"
                @mouseup="endZoomSub"
              />
              <i
                class="vicp-icon6"
                @mousedown="startZoomAdd"
                @mouseout="endZoomAdd"
                @mouseup="endZoomAdd"
              />
            </div>

            <div v-if="!noRotate" class="vicp-rotate">
              <i @mousedown="startRotateLeft" @mouseout="endRotate" @mouseup="endRotate">↺</i>
              <i @mousedown="startRotateRight" @mouseout="endRotate" @mouseup="endRotate">↻</i>
            </div>
          </div>
          <div v-show="true" class="vicp-crop-right">
            <div class="vicp-preview">
              <div v-if="!noSquare" class="vicp-preview-item">
                <img :src="createImgUrl" :style="previewStyle">
                <span>{{ lang.preview }}</span>
              </div>
              <div v-if="!noCircle" class="vicp-preview-item vicp-preview-item-circle">
                <img :src="createImgUrl" :style="previewStyle">
                <span>{{ lang.preview }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="vicp-operate">
          <a @click="setStep(1)" @mousedown="ripple">{{ lang.btn.back }}</a>
          <a class="vicp-operate-btn" @click="prepareUpload" @mousedown="ripple">{{ lang.btn.save }}</a>
        </div>
      </div>

      <div v-if="step == 3" class="vicp-step3">
        <div class="vicp-upload">
          <span v-show="loading === 1" class="vicp-loading">{{ lang.loading }}</span>
          <div class="vicp-progress-wrap">
            <span v-show="loading === 1" :style="progressStyle" class="vicp-progress" />
          </div>
          <div v-show="hasError" class="vicp-error">
            <i class="vicp-icon2" />
            {{ errorMsg }}
          </div>
          <div v-show="loading === 2" class="vicp-success">
            <i class="vicp-icon3" />
            {{ lang.success }}
          </div>
        </div>
        <div class="vicp-operate">
          <a @click="setStep(2)" @mousedown="ripple">{{ lang.btn.back }}</a>
          <a @click="off" @mousedown="ripple">{{ lang.btn.close }}</a>
        </div>
      </div>
      <canvas v-show="false" ref="canvas" :width="width" :height="height" />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, toRefs, watch } from 'vue'
import request from '@/utils/request'
import language from './utils/language.js'
import mimes from './utils/mimes.js'
import data2blob from './utils/data2blob.js'
import effectRipple from './utils/effectRipple.js'

defineOptions({
  name: 'ImageCropper'
})

const props = defineProps({
  field: {
    type: String,
    default: 'avatar'
  },
  ki: {
    type: Number,
    default: 0
  },
  modelValue: {
    type: Boolean,
    default: true
  },
  url: {
    type: String,
    default: ''
  },
  params: {
    type: Object,
    default: null
  },
  headers: {
    type: Object,
    default: null
  },
  width: {
    type: Number,
    default: 200
  },
  height: {
    type: Number,
    default: 200
  },
  noRotate: {
    type: Boolean,
    default: true
  },
  noCircle: {
    type: Boolean,
    default: false
  },
  noSquare: {
    type: Boolean,
    default: false
  },
  maxSize: {
    type: Number,
    default: 10240
  },
  langType: {
    type: String,
    default: 'zh'
  },
  langExt: {
    type: Object,
    default: null
  },
  imgFormat: {
    type: String,
    default: 'png'
  },
  withCredentials: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'close', 'crop-success', 'crop-upload-success', 'crop-upload-fail'])

const allowImgFormat = ['jpg', 'png']
const tempImgFormat = allowImgFormat.indexOf(props.imgFormat) === -1 ? 'jpg' : props.imgFormat
const baseLang = JSON.parse(JSON.stringify(language[props.langType] ? language[props.langType] : language.en))
if (props.langExt) {
  Object.assign(baseLang, props.langExt)
}

const state = reactive({
  imgFormat: tempImgFormat,
  mime: mimes[tempImgFormat],
  lang: baseLang,
  isSupported: typeof FormData === 'function',
  isSupportTouch: typeof document !== 'undefined' ? Object.prototype.hasOwnProperty.call(document, 'ontouchstart') : false,
  step: 1,
  loading: 0,
  progress: 0,
  hasError: false,
  errorMsg: '',
  sourceImg: null,
  sourceImgUrl: '',
  createImgUrl: '',
  sourceImgMouseDown: {
    on: false,
    mX: 0,
    mY: 0,
    x: 0,
    y: 0
  },
  previewContainer: {
    width: 100,
    height: 100
  },
  sourceImgContainer: {
    width: 240,
    height: 184
  },
  scale: {
    zoomAddOn: false,
    zoomSubOn: false,
    range: 1,
    rotateLeft: false,
    rotateRight: false,
    degree: 0,
    x: 0,
    y: 0,
    width: 0,
    height: 0,
    maxWidth: 0,
    maxHeight: 0,
    minWidth: 0,
    minHeight: 0,
    naturalWidth: 0,
    naturalHeight: 0
  }
})

const {
  lang,
  isSupported,
  isSupportTouch,
  step,
  loading,
  progress,
  hasError,
  errorMsg,
  sourceImgUrl,
  createImgUrl,
  sourceImgMouseDown,
  previewContainer,
  sourceImgContainer,
  scale
} = toRefs(state)
const fileinput = ref(null)
const img = ref(null)
const canvas = ref(null)

const progressStyle = computed(() => ({
  width: state.progress + '%'
}))

const sourceImgMasking = computed(() => {
  const ratio = props.width / props.height
  const sic = state.sourceImgContainer
  const sicRatio = sic.width / sic.height
  let x = 0
  let y = 0
  let w = sic.width
  let h = sic.height
  let scaleValue = 1
  if (ratio < sicRatio) {
    scaleValue = sic.height / props.height
    w = sic.height * ratio
    x = (sic.width - w) / 2
  }
  if (ratio > sicRatio) {
    scaleValue = sic.width / props.width
    h = sic.width / ratio
    y = (sic.height - h) / 2
  }
  return {
    scale: scaleValue,
    x,
    y,
    width: w,
    height: h
  }
})

const sourceImgStyle = computed(() => {
  const sim = sourceImgMasking.value
  const top = state.scale.y + sim.y + 'px'
  const left = state.scale.x + sim.x + 'px'
  const degree = state.scale.degree
  return {
    top,
    left,
    width: state.scale.width + 'px',
    height: state.scale.height + 'px',
    transform: 'rotate(' + degree + 'deg)',
    '-ms-transform': 'rotate(' + degree + 'deg)',
    '-moz-transform': 'rotate(' + degree + 'deg)',
    '-webkit-transform': 'rotate(' + degree + 'deg)',
    '-o-transform': 'rotate(' + degree + 'deg)'
  }
})

const sourceImgShadeStyle = computed(() => {
  const sic = state.sourceImgContainer
  const sim = sourceImgMasking.value
  const w = sim.width === sic.width ? sim.width : (sic.width - sim.width) / 2
  const h = sim.height === sic.height ? sim.height : (sic.height - sim.height) / 2
  return {
    width: w + 'px',
    height: h + 'px'
  }
})

const previewStyle = computed(() => {
  const ratio = props.width / props.height
  const pc = state.previewContainer
  let w = pc.width
  let h = pc.height
  const pcRatio = w / h
  if (ratio < pcRatio) {
    w = pc.height * ratio
  }
  if (ratio > pcRatio) {
    h = pc.width / ratio
  }
  return {
    width: w + 'px',
    height: h + 'px'
  }
})

watch(() => props.modelValue, (newValue) => {
  if (newValue && state.loading !== 1) {
    reset()
  }
})

function ripple(e) {
  effectRipple(e)
}

function off() {
  setTimeout(() => {
    emit('update:modelValue', false)
    emit('close')
    if (state.step === 3 && state.loading === 2) {
      setStep(1)
    }
  }, 200)
}

function setStep(no) {
  setTimeout(() => {
    state.step = no
  }, 200)
}

function preventDefault(e) {
  e.preventDefault()
  return false
}

function handleClick(e) {
  if (state.loading !== 1) {
    if (e.target !== fileinput.value) {
      e.preventDefault()
      if (fileinput.value) {
        fileinput.value.click()
      }
    }
  }
}

function handleChange(e) {
  e.preventDefault()
  if (state.loading !== 1) {
    const files = e.target.files || e.dataTransfer.files
    reset()
    if (files && files[0] && checkFile(files[0])) {
      setSourceImg(files[0])
    }
  }
}

function checkFile(file) {
  const { lang: langValue } = state
  if (file.type.indexOf('image') === -1) {
    state.hasError = true
    state.errorMsg = langValue.error.onlyImg
    return false
  }
  if (file.size / 1024 > props.maxSize) {
    state.hasError = true
    state.errorMsg = langValue.error.outOfSize + props.maxSize + 'kb'
    return false
  }
  return true
}

function reset() {
  state.loading = 0
  state.hasError = false
  state.errorMsg = ''
  state.progress = 0
}

function setSourceImg(file) {
  const fr = new FileReader()
  fr.onload = () => {
    state.sourceImgUrl = fr.result
    startCrop()
  }
  fr.readAsDataURL(file)
}

function startCrop() {
  const ratio = props.width / props.height
  const sim = sourceImgMasking.value
  const image = new Image()
  image.src = state.sourceImgUrl
  image.onload = () => {
    const nWidth = image.naturalWidth
    const nHeight = image.naturalHeight
    const nRatio = nWidth / nHeight
    let w = sim.width
    let h = sim.height
    let x = 0
    let y = 0
    if (nWidth < props.width || nHeight < props.height) {
      state.hasError = true
      state.errorMsg = state.lang.error.lowestPx + props.width + '*' + props.height
      return false
    }
    if (ratio > nRatio) {
      h = w / nRatio
      y = (sim.height - h) / 2
    }
    if (ratio < nRatio) {
      w = h * nRatio
      x = (sim.width - w) / 2
    }
    state.scale.range = 0
    state.scale.x = x
    state.scale.y = y
    state.scale.width = w
    state.scale.height = h
    state.scale.degree = 0
    state.scale.minWidth = w
    state.scale.minHeight = h
    state.scale.maxWidth = nWidth * sim.scale
    state.scale.maxHeight = nHeight * sim.scale
    state.scale.naturalWidth = nWidth
    state.scale.naturalHeight = nHeight
    state.sourceImg = image
    createImg()
    setStep(2)
  }
}

function imgStartMove(e) {
  e.preventDefault()
  if (state.isSupportTouch && !e.targetTouches) {
    return false
  }
  const et = e.targetTouches ? e.targetTouches[0] : e
  state.sourceImgMouseDown.mX = et.screenX
  state.sourceImgMouseDown.mY = et.screenY
  state.sourceImgMouseDown.x = state.scale.x
  state.sourceImgMouseDown.y = state.scale.y
  state.sourceImgMouseDown.on = true
}

function imgMove(e) {
  e.preventDefault()
  if (state.isSupportTouch && !e.targetTouches) {
    return false
  }
  const et = e.targetTouches ? e.targetTouches[0] : e
  const on = state.sourceImgMouseDown.on
  const mX = state.sourceImgMouseDown.mX
  const mY = state.sourceImgMouseDown.mY
  const x = state.sourceImgMouseDown.x
  const y = state.sourceImgMouseDown.y
  const sim = sourceImgMasking.value
  const nX = et.screenX
  const nY = et.screenY
  const dX = nX - mX
  const dY = nY - mY
  let rX = x + dX
  let rY = y + dY
  if (!on) return
  if (rX > 0) {
    rX = 0
  }
  if (rY > 0) {
    rY = 0
  }
  if (rX < sim.width - state.scale.width) {
    rX = sim.width - state.scale.width
  }
  if (rY < sim.height - state.scale.height) {
    rY = sim.height - state.scale.height
  }
  state.scale.x = rX
  state.scale.y = rY
}

function startRotateRight() {
  state.scale.rotateRight = true
  const rotate = () => {
    if (state.scale.rotateRight) {
      const degree = ++state.scale.degree
      createImg(degree)
      setTimeout(() => {
        rotate()
      }, 60)
    }
  }
  rotate()
}

function startRotateLeft() {
  state.scale.rotateLeft = true
  const rotate = () => {
    if (state.scale.rotateLeft) {
      const degree = --state.scale.degree
      createImg(degree)
      setTimeout(() => {
        rotate()
      }, 60)
    }
  }
  rotate()
}

function endRotate() {
  state.scale.rotateLeft = false
  state.scale.rotateRight = false
}

function startZoomAdd() {
  state.scale.zoomAddOn = true
  const zoom = () => {
    if (state.scale.zoomAddOn) {
      const range = state.scale.range >= 100 ? 100 : ++state.scale.range
      zoomImg(range)
      setTimeout(() => {
        zoom()
      }, 60)
    }
  }
  zoom()
}

function endZoomAdd() {
  state.scale.zoomAddOn = false
}

function startZoomSub() {
  state.scale.zoomSubOn = true
  const zoom = () => {
    if (state.scale.zoomSubOn) {
      const range = state.scale.range <= 0 ? 0 : --state.scale.range
      zoomImg(range)
      setTimeout(() => {
        zoom()
      }, 60)
    }
  }
  zoom()
}

function endZoomSub() {
  state.scale.zoomSubOn = false
}

function zoomChange(e) {
  zoomImg(e.target.value)
}

function zoomImg(newRange) {
  const sim = sourceImgMasking.value
  const {
    maxWidth,
    maxHeight,
    minWidth,
    minHeight,
    width,
    height,
    x,
    y
  } = state.scale
  const sWidth = sim.width
  const sHeight = sim.height
  const nWidth = minWidth + ((maxWidth - minWidth) * newRange) / 100
  const nHeight = minHeight + ((maxHeight - minHeight) * newRange) / 100
  let nX = sWidth / 2 - (nWidth / width) * (sWidth / 2 - x)
  let nY = sHeight / 2 - (nHeight / height) * (sHeight / 2 - y)
  if (nX > 0) {
    nX = 0
  }
  if (nY > 0) {
    nY = 0
  }
  if (nX < sWidth - nWidth) {
    nX = sWidth - nWidth
  }
  if (nY < sHeight - nHeight) {
    nY = sHeight - nHeight
  }
  state.scale.x = nX
  state.scale.y = nY
  state.scale.width = nWidth
  state.scale.height = nHeight
  state.scale.range = newRange
  setTimeout(() => {
    if (state.scale.range === newRange) {
      createImg()
    }
  }, 300)
}

function createImg(e) {
  const canvasEl = canvas.value
  if (!canvasEl || !state.sourceImg) {
    return
  }
  const ctx = canvasEl.getContext('2d')
  const simScale = sourceImgMasking.value.scale
  const x = state.scale.x
  const y = state.scale.y
  const width = state.scale.width
  const height = state.scale.height
  const degree = state.scale.degree
  if (e) {
    state.sourceImgMouseDown.on = false
  }
  canvasEl.width = props.width
  canvasEl.height = props.height
  ctx.clearRect(0, 0, props.width, props.height)
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, props.width, props.height)
  ctx.translate(props.width * 0.5, props.height * 0.5)
  ctx.rotate((Math.PI * degree) / 180)
  ctx.translate(-props.width * 0.5, -props.height * 0.5)
  ctx.drawImage(
    state.sourceImg,
    x / simScale,
    y / simScale,
    width / simScale,
    height / simScale
  )
  state.createImgUrl = canvasEl.toDataURL(state.mime)
}

function prepareUpload() {
  const { url, field, ki } = props
  emit('crop-success', state.createImgUrl, field, ki)
  if (typeof url === 'string' && url) {
    upload()
  } else {
    off()
  }
}

function upload() {
  const fmData = new FormData()
  fmData.append(
    props.field,
    data2blob(state.createImgUrl, state.mime),
    props.field + '.' + state.imgFormat
  )
  if (typeof props.params === 'object' && props.params) {
    Object.keys(props.params).forEach(k => {
      fmData.append(k, props.params[k])
    })
  }
  reset()
  state.loading = 1
  setStep(3)
  request({
    url: props.url,
    method: 'post',
    data: fmData
  })
    .then(resData => {
      state.loading = 2
      emit('crop-upload-success', resData.data)
    })
    .catch(err => {
      if (props.modelValue) {
        state.loading = 3
        state.hasError = true
        state.errorMsg = state.lang.fail
        emit('crop-upload-fail', err, props.field, props.ki)
      }
    })
}
</script>

<style lang="scss">
@charset "UTF-8";
@-webkit-keyframes vicp_progress {
  0% {
    background-position-y: 0;
  }
  100% {
    background-position-y: 40px;
  }
}
@keyframes vicp_progress {
  0% {
    background-position-y: 0;
  }
  100% {
    background-position-y: 40px;
  }
}
@-webkit-keyframes vicp {
  0% {
    opacity: 0;
    -webkit-transform: scale(0) translatey(-60px);
    transform: scale(0) translatey(-60px);
  }
  100% {
    opacity: 1;
    -webkit-transform: scale(1) translatey(0);
    transform: scale(1) translatey(0);
  }
}
@keyframes vicp {
  0% {
    opacity: 0;
    -webkit-transform: scale(0) translatey(-60px);
    transform: scale(0) translatey(-60px);
  }
  100% {
    opacity: 1;
    -webkit-transform: scale(1) translatey(0);
    transform: scale(1) translatey(0);
  }
}
.vue-image-crop-upload {
  position: fixed;
  display: block;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  z-index: 10000;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.65);
  -webkit-tap-highlight-color: transparent;
  -moz-tap-highlight-color: transparent;
}
.vue-image-crop-upload .vicp-wrap {
  -webkit-box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  position: fixed;
  display: block;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  z-index: 10000;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  margin: auto;
  width: 600px;
  height: 330px;
  padding: 25px;
  background-color: #fff;
  border-radius: 2px;
  -webkit-animation: vicp 0.12s ease-in;
  animation: vicp 0.12s ease-in;
}
.vue-image-crop-upload .vicp-wrap .vicp-close {
  position: absolute;
  right: -30px;
  top: -30px;
}
.vue-image-crop-upload .vicp-wrap .vicp-close .vicp-icon4 {
  position: relative;
  display: block;
  width: 30px;
  height: 30px;
  cursor: pointer;
  -webkit-transition: -webkit-transform 0.18s;
  transition: -webkit-transform 0.18s;
  transition: transform 0.18s;
  transition: transform 0.18s, -webkit-transform 0.18s;
  -webkit-transform: rotate(0);
  -ms-transform: rotate(0);
  transform: rotate(0);
}
.vue-image-crop-upload .vicp-wrap .vicp-close .vicp-icon4::after,
.vue-image-crop-upload .vicp-wrap .vicp-close .vicp-icon4::before {
  -webkit-box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  content: "";
  position: absolute;
  top: 12px;
  left: 4px;
  width: 20px;
  height: 3px;
  -webkit-transform: rotate(45deg);
  -ms-transform: rotate(45deg);
  transform: rotate(45deg);
  background-color: #fff;
}
.vue-image-crop-upload .vicp-wrap .vicp-close .vicp-icon4::after {
  -webkit-transform: rotate(-45deg);
  -ms-transform: rotate(-45deg);
  transform: rotate(-45deg);
}
.vue-image-crop-upload .vicp-wrap .vicp-close .vicp-icon4:hover {
  -webkit-transform: rotate(90deg);
  -ms-transform: rotate(90deg);
  transform: rotate(90deg);
}
.vue-image-crop-upload .vicp-wrap .vicp-step1 .vicp-drop-area {
  position: relative;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  padding: 35px;
  height: 170px;
  background-color: rgba(0, 0, 0, 0.03);
  text-align: center;
  border: 1px dashed rgba(0, 0, 0, 0.08);
  overflow: hidden;
}
.vue-image-crop-upload .vicp-wrap .vicp-step1 .vicp-drop-area .vicp-icon1 {
  display: block;
  margin: 0 auto 6px;
  width: 42px;
  height: 42px;
  overflow: hidden;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step1
  .vicp-drop-area
  .vicp-icon1
  .vicp-icon1-arrow {
  display: block;
  margin: 0 auto;
  width: 0;
  height: 0;
  border-bottom: 14.7px solid rgba(0, 0, 0, 0.3);
  border-left: 14.7px solid transparent;
  border-right: 14.7px solid transparent;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step1
  .vicp-drop-area
  .vicp-icon1
  .vicp-icon1-body {
  display: block;
  width: 12.6px;
  height: 14.7px;
  margin: 0 auto;
  background-color: rgba(0, 0, 0, 0.3);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step1
  .vicp-drop-area
  .vicp-icon1
  .vicp-icon1-bottom {
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  display: block;
  height: 12.6px;
  border: 6px solid rgba(0, 0, 0, 0.3);
  border-top: none;
}
.vue-image-crop-upload .vicp-wrap .vicp-step1 .vicp-drop-area .vicp-hint {
  display: block;
  padding: 15px;
  font-size: 14px;
  color: #666;
  line-height: 30px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step1
  .vicp-drop-area
  .vicp-no-supported-hint {
  display: block;
  position: absolute;
  top: 0;
  left: 0;
  padding: 30px;
  width: 100%;
  height: 60px;
  line-height: 30px;
  background-color: #eee;
  text-align: center;
  color: #666;
  font-size: 14px;
}
.vue-image-crop-upload .vicp-wrap .vicp-step1 .vicp-drop-area:hover {
  cursor: pointer;
  border-color: rgba(0, 0, 0, 0.1);
  background-color: rgba(0, 0, 0, 0.05);
}
.vue-image-crop-upload .vicp-wrap .vicp-step2 .vicp-crop {
  overflow: hidden;
}
.vue-image-crop-upload .vicp-wrap .vicp-step2 .vicp-crop .vicp-crop-left {
  float: left;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-img-container {
  position: relative;
  display: block;
  width: 240px;
  height: 180px;
  background-color: #e5e5e0;
  overflow: hidden;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-img-container
  .vicp-img {
  position: absolute;
  display: block;
  cursor: move;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-img-container
  .vicp-img-shade {
  -webkit-box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.18);
  box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.18);
  position: absolute;
  background-color: rgba(241, 242, 243, 0.8);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-img-container
  .vicp-img-shade.vicp-img-shade-1 {
  top: 0;
  left: 0;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-img-container
  .vicp-img-shade.vicp-img-shade-2 {
  bottom: 0;
  right: 0;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-rotate {
  position: relative;
  width: 240px;
  height: 18px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-rotate
  i {
  display: block;
  width: 18px;
  height: 18px;
  border-radius: 100%;
  line-height: 18px;
  text-align: center;
  font-size: 12px;
  font-weight: bold;
  background-color: rgba(0, 0, 0, 0.08);
  color: #fff;
  overflow: hidden;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-rotate
  i:hover {
  -webkit-box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  cursor: pointer;
  background-color: rgba(0, 0, 0, 0.14);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-rotate
  i:first-child {
  float: left;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-rotate
  i:last-child {
  float: right;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range {
  position: relative;
  margin: 30px 0 10px 0;
  width: 240px;
  height: 18px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon5,
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon6 {
  position: absolute;
  top: 0;
  width: 18px;
  height: 18px;
  border-radius: 100%;
  background-color: rgba(0, 0, 0, 0.08);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon5:hover,
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon6:hover {
  -webkit-box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  cursor: pointer;
  background-color: rgba(0, 0, 0, 0.14);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon5 {
  left: 0;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon5::before {
  position: absolute;
  content: "";
  display: block;
  left: 3px;
  top: 8px;
  width: 12px;
  height: 2px;
  background-color: #fff;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon6 {
  right: 0;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon6::before {
  position: absolute;
  content: "";
  display: block;
  left: 3px;
  top: 8px;
  width: 12px;
  height: 2px;
  background-color: #fff;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  .vicp-icon6::after {
  position: absolute;
  content: "";
  display: block;
  top: 3px;
  left: 8px;
  width: 2px;
  height: 12px;
  background-color: #fff;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"] {
  display: block;
  padding-top: 5px;
  margin: 0 auto;
  width: 180px;
  height: 8px;
  vertical-align: top;
  background: transparent;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  cursor: pointer;
  /* 滑块
               ---------------------------------------------------------------*/
  /* 轨道
               ---------------------------------------------------------------*/
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:focus {
  outline: none;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-webkit-slider-thumb {
  -webkit-box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.18);
  box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.18);
  -webkit-appearance: none;
  appearance: none;
  margin-top: -3px;
  width: 12px;
  height: 12px;
  background-color: #61c091;
  border-radius: 100%;
  border: none;
  -webkit-transition: 0.2s;
  transition: 0.2s;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-moz-range-thumb {
  box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.18);
  -moz-appearance: none;
  appearance: none;
  width: 12px;
  height: 12px;
  background-color: #61c091;
  border-radius: 100%;
  border: none;
  -webkit-transition: 0.2s;
  transition: 0.2s;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-ms-thumb {
  box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.18);
  appearance: none;
  width: 12px;
  height: 12px;
  background-color: #61c091;
  border: none;
  border-radius: 100%;
  -webkit-transition: 0.2s;
  transition: 0.2s;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:active::-moz-range-thumb {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  width: 14px;
  height: 14px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:active::-ms-thumb {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  width: 14px;
  height: 14px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:active::-webkit-slider-thumb {
  -webkit-box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.23);
  margin-top: -4px;
  width: 14px;
  height: 14px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-webkit-slider-runnable-track {
  -webkit-box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  width: 100%;
  height: 6px;
  cursor: pointer;
  border-radius: 2px;
  border: none;
  background-color: rgba(68, 170, 119, 0.3);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-moz-range-track {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  width: 100%;
  height: 6px;
  cursor: pointer;
  border-radius: 2px;
  border: none;
  background-color: rgba(68, 170, 119, 0.3);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-ms-track {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.12);
  width: 100%;
  cursor: pointer;
  background: transparent;
  border-color: transparent;
  color: transparent;
  height: 6px;
  border-radius: 2px;
  border: none;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-ms-fill-lower {
  background-color: rgba(68, 170, 119, 0.3);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]::-ms-fill-upper {
  background-color: rgba(68, 170, 119, 0.15);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:focus::-webkit-slider-runnable-track {
  background-color: rgba(68, 170, 119, 0.5);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:focus::-moz-range-track {
  background-color: rgba(68, 170, 119, 0.5);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:focus::-ms-fill-lower {
  background-color: rgba(68, 170, 119, 0.45);
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-left
  .vicp-range
  input[type="range"]:focus::-ms-fill-upper {
  background-color: rgba(68, 170, 119, 0.25);
}
.vue-image-crop-upload .vicp-wrap .vicp-step2 .vicp-crop .vicp-crop-right {
  float: right;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-right
  .vicp-preview {
  height: 150px;
  overflow: hidden;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-right
  .vicp-preview
  .vicp-preview-item {
  position: relative;
  padding: 5px;
  width: 100px;
  height: 100px;
  float: left;
  margin-right: 16px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-right
  .vicp-preview
  .vicp-preview-item
  span {
  position: absolute;
  bottom: -30px;
  width: 100%;
  font-size: 14px;
  color: #bbb;
  display: block;
  text-align: center;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-right
  .vicp-preview
  .vicp-preview-item
  img {
  position: absolute;
  display: block;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  margin: auto;
  padding: 3px;
  background-color: #fff;
  border: 1px solid rgba(0, 0, 0, 0.15);
  overflow: hidden;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-right
  .vicp-preview
  .vicp-preview-item.vicp-preview-item-circle {
  margin-right: 0;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step2
  .vicp-crop
  .vicp-crop-right
  .vicp-preview
  .vicp-preview-item.vicp-preview-item-circle
  img {
  border-radius: 100%;
}
.vue-image-crop-upload .vicp-wrap .vicp-step3 .vicp-upload {
  position: relative;
  -webkit-box-sizing: border-box;
  box-sizing: border-box;
  padding: 35px;
  height: 170px;
  background-color: rgba(0, 0, 0, 0.03);
  text-align: center;
  border: 1px dashed #ddd;
}
.vue-image-crop-upload .vicp-wrap .vicp-step3 .vicp-upload .vicp-loading {
  display: block;
  padding: 15px;
  font-size: 16px;
  color: #999;
  line-height: 30px;
}
.vue-image-crop-upload .vicp-wrap .vicp-step3 .vicp-upload .vicp-progress-wrap {
  margin-top: 12px;
  background-color: rgba(0, 0, 0, 0.08);
  border-radius: 3px;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step3
  .vicp-upload
  .vicp-progress-wrap
  .vicp-progress {
  position: relative;
  display: block;
  height: 5px;
  border-radius: 3px;
  background-color: #4a7;
  -webkit-box-shadow: 0 2px 6px 0 rgba(68, 170, 119, 0.3);
  box-shadow: 0 2px 6px 0 rgba(68, 170, 119, 0.3);
  -webkit-transition: width 0.15s linear;
  transition: width 0.15s linear;
  background-image: -webkit-linear-gradient(
    135deg,
    rgba(255, 255, 255, 0.2) 25%,
    transparent 25%,
    transparent 50%,
    rgba(255, 255, 255, 0.2) 50%,
    rgba(255, 255, 255, 0.2) 75%,
    transparent 75%,
    transparent
  );
  background-image: linear-gradient(
    -45deg,
    rgba(255, 255, 255, 0.2) 25%,
    transparent 25%,
    transparent 50%,
    rgba(255, 255, 255, 0.2) 50%,
    rgba(255, 255, 255, 0.2) 75%,
    transparent 75%,
    transparent
  );
  background-size: 40px 40px;
  -webkit-animation: vicp_progress 0.5s linear infinite;
  animation: vicp_progress 0.5s linear infinite;
}
.vue-image-crop-upload
  .vicp-wrap
  .vicp-step3
  .vicp-upload
  .vicp-progress-wrap
  .vicp-progress::after {
  content: "";
  position: absolute;
  display: block;
  top: -3px;
  right: -3px;
  width: 9px;
  height: 9px;
  border: 1px solid rgba(245, 246, 247, 0.7);
  -webkit-box-shadow: 0 1px 4px 0 rgba(68, 170, 119, 0.7);
  box-shadow: 0 1px 4px 0 rgba(68, 170, 119, 0.7);
  border-radius: 100%;
  background-color: #4a7;
}
.vue-image-crop-upload .vicp-wrap .vicp-step3 .vicp-upload .vicp-error,
.vue-image-crop-upload .vicp-wrap .vicp-step3 .vicp-upload .vicp-success {
  height: 100px;
  line-height: 100px;
}
.vue-image-crop-upload .vicp-wrap .vicp-operate {
  position: absolute;
  right: 20px;
  bottom: 20px;
}
.vue-image-crop-upload .vicp-wrap .vicp-operate a {
  position: relative;
  float: left;
  display: block;
  margin-left: 10px;
  width: 100px;
  height: 36px;
  line-height: 36px;
  text-align: center;
  cursor: pointer;
  font-size: 14px;
  color: #4a7;
  border-radius: 2px;
  overflow: hidden;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}
.vue-image-crop-upload .vicp-wrap .vicp-operate a:hover {
  background-color: rgba(0, 0, 0, 0.03);
}
.vue-image-crop-upload .vicp-wrap .vicp-error,
.vue-image-crop-upload .vicp-wrap .vicp-success {
  display: block;
  font-size: 14px;
  line-height: 24px;
  height: 24px;
  color: #d10;
  text-align: center;
  vertical-align: top;
}
.vue-image-crop-upload .vicp-wrap .vicp-success {
  color: #4a7;
}
.vue-image-crop-upload .vicp-wrap .vicp-icon3 {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  top: 4px;
}
.vue-image-crop-upload .vicp-wrap .vicp-icon3::after {
  position: absolute;
  top: 3px;
  left: 6px;
  width: 6px;
  height: 10px;
  border-width: 0 2px 2px 0;
  border-color: #4a7;
  border-style: solid;
  -webkit-transform: rotate(45deg);
  -ms-transform: rotate(45deg);
  transform: rotate(45deg);
  content: "";
}
.vue-image-crop-upload .vicp-wrap .vicp-icon2 {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  top: 4px;
}
.vue-image-crop-upload .vicp-wrap .vicp-icon2::after,
.vue-image-crop-upload .vicp-wrap .vicp-icon2::before {
  content: "";
  position: absolute;
  top: 9px;
  left: 4px;
  width: 13px;
  height: 2px;
  background-color: #d10;
  -webkit-transform: rotate(45deg);
  -ms-transform: rotate(45deg);
  transform: rotate(45deg);
}
.vue-image-crop-upload .vicp-wrap .vicp-icon2::after {
  -webkit-transform: rotate(-45deg);
  -ms-transform: rotate(-45deg);
  transform: rotate(-45deg);
}
.e-ripple {
  position: absolute;
  border-radius: 100%;
  background-color: rgba(0, 0, 0, 0.15);
  background-clip: padding-box;
  pointer-events: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
  -webkit-transform: scale(0);
  -ms-transform: scale(0);
  transform: scale(0);
  opacity: 1;
}
.e-ripple.z-active {
  opacity: 0;
  -webkit-transform: scale(2);
  -ms-transform: scale(2);
  transform: scale(2);
  -webkit-transition: opacity 1.2s ease-out, -webkit-transform 0.6s ease-out;
  transition: opacity 1.2s ease-out, -webkit-transform 0.6s ease-out;
  transition: opacity 1.2s ease-out, transform 0.6s ease-out;
  transition: opacity 1.2s ease-out, transform 0.6s ease-out,
    -webkit-transform 0.6s ease-out;
}
</style>
