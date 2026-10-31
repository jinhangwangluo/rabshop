<template>
  <div ref="rootRef" style="position: relative">
    <div v-if="type === '2'" class="verify-img-out" :style="{ height: parseInt(setSize.imgHeight) + vSpace + 'px' }">
      <div class="verify-img-panel" :style="{ width: setSize.imgWidth, height: setSize.imgHeight }">
        <img
          :src="backImgBase ? 'data:image/png;base64,' + backImgBase : defaultImg"
          alt=""
          style="width: 100%; height: 100%; display: block"
        >
        <div v-show="showRefresh" class="verify-refresh" @click="refresh"><i class="iconfont icon-refresh" /></div>
        <transition name="tips">
          <span v-if="tipWords" class="verify-tips" :class="passFlag ? 'suc-bg' : 'err-bg'">{{ tipWords }}</span>
        </transition>
      </div>
    </div>
    <!-- 公共部分 -->
    <div
      ref="barAreaRef"
      class="verify-bar-area"
      :style="{ width: setSize.imgWidth, height: barSize.height, 'line-height': barSize.height }"
    >
      <span class="verify-msg" v-text="text" />
      <div
        class="verify-left-bar"
        :style="{
          width: leftBarWidth !== undefined ? leftBarWidth : barSize.height,
          height: barSize.height,
          'border-color': leftBarBorderColor,
          transaction: transitionWidth,
        }"
      >
        <span class="verify-msg" v-text="finishText" />
        <div
          class="verify-move-block"
          :style="{
            width: barSize.height,
            height: barSize.height,
            'background-color': moveBlockBackgroundColor,
            left: moveBlockLeft,
            transition: transitionLeft,
          }"
          @touchstart="start"
          @mousedown="start"
        >
          <i :class="['verify-icon iconfont', iconClass]" :style="{ color: iconColor }" />
          <div
            v-if="type === '2'"
            class="verify-sub-block"
            :style="{
              width: Math.floor((parseInt(setSize.imgWidth) * 47) / 310) + 'px',
              height: setSize.imgHeight,
              top: '-' + (parseInt(setSize.imgHeight) + vSpace) + 'px',
              'background-size': setSize.imgWidth + ' ' + setSize.imgHeight,
            }"
          >
            <img
              :src="'data:image/png;base64,' + blockBackImgBase"
              alt=""
              style="width: 100%; height: 100%; display: block"
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
/**
 * VerifySlide
 * @description 滑块
 * */
import {
  getCurrentInstance,
  nextTick,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch
} from 'vue'
import { aesEncrypt } from './../utils/ase'
import { resetSize as calcResetSize } from './../utils/util'
import { ajCaptcha, ajCaptchaCheck } from '@/api/common'

defineOptions({
  name: 'VerifySlide'
})

const props = defineProps({
  captchaType: {
    type: String
  },
  type: {
    type: String,
    default: '1'
  },
  // 弹出式pop，固定fixed
  mode: {
    type: String,
    default: 'fixed'
  },
  vSpace: {
    type: Number,
    default: 5
  },
  explain: {
    type: String,
    default: '向右滑动完成验证'
  },
  imgSize: {
    type: Object,
    default() {
      return {
        width: '310px',
        height: '155px'
      }
    }
  },
  blockSize: {
    type: Object,
    default() {
      return {
        width: '50px',
        height: '50px'
      }
    }
  },
  barSize: {
    type: Object,
    default() {
      return {
        width: '310px',
        height: '40px'
      }
    }
  },
  defaultImg: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['ready', 'success', 'error', 'close'])
const { proxy } = getCurrentInstance() || {}
const rootRef = ref(null)
const barAreaRef = ref(null)
const secretKey = ref('')
const passFlag = ref('')
const backImgBase = ref('')
const blockBackImgBase = ref('')
const backToken = ref('')
const startMoveTime = ref('')
const endMovetime = ref('')
const tipWords = ref('')
const text = ref('')
const finishText = ref('')
const setSize = reactive({
  imgHeight: 0,
  imgWidth: 0,
  barHeight: 0,
  barWidth: 0
})
const top = ref(0)
const left = ref(0)
const moveBlockLeft = ref(undefined)
const leftBarWidth = ref(undefined)
const moveBlockBackgroundColor = ref(undefined)
const leftBarBorderColor = ref('#ddd')
const iconColor = ref(undefined)
const iconClass = ref('icon-right')
const status = ref(false)
const isEnd = ref(false)
const showRefresh = ref(true)
const transitionLeft = ref('')
const transitionWidth = ref('')
const startLeft = ref(0)

function removeListeners() {
  window.removeEventListener('touchmove', handleWindowMove)
  window.removeEventListener('mousemove', handleWindowMove)
  window.removeEventListener('touchend', handleWindowEnd)
  window.removeEventListener('mouseup', handleWindowEnd)
}

function handleWindowMove(e) {
  move(e)
}

function handleWindowEnd() {
  end()
}

watch(() => props.type, () => {
  init()
}, { immediate: true })

onMounted(() => {
  if (rootRef.value) {
    rootRef.value.onselectstart = function() {
      return false
    }
  }
})

onBeforeUnmount(() => {
  removeListeners()
})

function init() {
  text.value = props.explain
  getPictrue()
  nextTick(() => {
    if (proxy) {
      const nextSize = calcResetSize.call(proxy, proxy)
      Object.assign(setSize, nextSize)
      emit('ready', proxy)
    }
  })

  removeListeners()
  window.addEventListener('touchmove', handleWindowMove)
  window.addEventListener('mousemove', handleWindowMove)
  window.addEventListener('touchend', handleWindowEnd)
  window.addEventListener('mouseup', handleWindowEnd)
}

function start(e) {
  e = e || window.event
  const x = e.touches ? e.touches[0].pageX : e.clientX
  if (!barAreaRef.value) {
    return
  }
  startLeft.value = Math.floor(x - barAreaRef.value.getBoundingClientRect().left)
  startMoveTime.value = +new Date()
  if (isEnd.value === false) {
    text.value = ''
    moveBlockBackgroundColor.value = '#337ab7'
    leftBarBorderColor.value = '#337AB7'
    iconColor.value = '#fff'
    e.stopPropagation()
    status.value = true
  }
}

function move(e) {
  e = e || window.event
  if (status.value && isEnd.value === false && barAreaRef.value) {
    const x = e.touches ? e.touches[0].pageX : e.clientX
    const barArea = barAreaRef.value
    let moveBlockLeftValue = x - barArea.getBoundingClientRect().left
    if (moveBlockLeftValue >= barArea.offsetWidth - parseInt(parseInt(props.blockSize.width) / 2) - 2) {
      moveBlockLeftValue = barArea.offsetWidth - parseInt(parseInt(props.blockSize.width) / 2) - 2
    }
    if (moveBlockLeftValue <= 0) {
      moveBlockLeftValue = parseInt(parseInt(props.blockSize.width) / 2)
    }
    moveBlockLeft.value = moveBlockLeftValue - startLeft.value + 'px'
    leftBarWidth.value = moveBlockLeftValue - startLeft.value + 'px'
  }
}

function end() {
  endMovetime.value = +new Date()
  if (status.value && isEnd.value === false) {
    let moveLeftDistance = parseInt((moveBlockLeft.value || '').replace('px', ''))
    moveLeftDistance = (moveLeftDistance * 310) / parseInt(setSize.imgWidth)
    const data = {
      captchaType: props.captchaType,
      pointJson: secretKey.value
        ? aesEncrypt(JSON.stringify({ x: moveLeftDistance, y: 5.0 }), secretKey.value)
        : JSON.stringify({ x: moveLeftDistance, y: 5.0 }),
      token: backToken.value
    }
    ajCaptchaCheck(data)
      .then(() => {
        moveBlockBackgroundColor.value = '#5cb85c'
        leftBarBorderColor.value = '#5cb85c'
        iconColor.value = '#fff'
        iconClass.value = 'icon-check'
        showRefresh.value = false
        isEnd.value = true
        if (props.mode === 'pop') {
          setTimeout(() => {
            emit('close')
          }, 1500)
        }
        passFlag.value = true
        tipWords.value = `${((endMovetime.value - startMoveTime.value) / 1000).toFixed(2)}s验证成功`
        const captchaVerification = secretKey.value
          ? aesEncrypt(backToken.value + '---' + JSON.stringify({ x: moveLeftDistance, y: 5.0 }), secretKey.value)
          : backToken.value + '---' + JSON.stringify({ x: moveLeftDistance, y: 5.0 })
        setTimeout(() => {
          tipWords.value = ''
          emit('success', { captchaVerification })
        }, 1000)
      })
      .catch((res) => {
        moveBlockBackgroundColor.value = '#d9534f'
        leftBarBorderColor.value = '#d9534f'
        iconColor.value = '#fff'
        iconClass.value = 'icon-close'
        passFlag.value = false
        setTimeout(() => {
          refresh()
        }, 1000)
        emit('error', proxy || null)
        tipWords.value = '验证失败'
        setTimeout(() => {
          tipWords.value = ''
        }, 1000)
      })
    status.value = false
  }
}

function refresh() {
  showRefresh.value = true
  finishText.value = ''
  transitionLeft.value = 'left .3s'
  moveBlockLeft.value = 0
  leftBarWidth.value = undefined
  transitionWidth.value = 'width .3s'
  leftBarBorderColor.value = '#ddd'
  moveBlockBackgroundColor.value = '#fff'
  iconColor.value = '#000'
  iconClass.value = 'icon-right'
  isEnd.value = false
  getPictrue()
  setTimeout(() => {
    transitionWidth.value = ''
    transitionLeft.value = ''
    text.value = props.explain
  }, 300)
}

function getPictrue() {
  const data = {
    captchaType: props.captchaType,
    clientUid: localStorage.getItem('slider'),
    ts: Date.now()
  }
  ajCaptcha(data)
    .then((res) => {
      backImgBase.value = res.data.originalImageBase64
      blockBackImgBase.value = res.data.jigsawImageBase64
      backToken.value = res.data.token
      secretKey.value = res.data.secretKey
    })
    .catch((res) => {
      tipWords.value = res.msg
      backImgBase.value = null
      blockBackImgBase.value = null
    })
}

defineExpose({
  refresh,
  init
})
</script>
