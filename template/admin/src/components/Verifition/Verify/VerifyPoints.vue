<template>
  <div ref="rootRef" style="position: relative">
    <div class="verify-img-out">
      <div
        class="verify-img-panel"
        :style="{
          width: setSize.imgWidth,
          height: setSize.imgHeight,
          'background-size': setSize.imgWidth + ' ' + setSize.imgHeight,
          'margin-bottom': vSpace + 'px',
        }"
      >
        <div v-show="showRefresh" class="verify-refresh" style="z-index: 3" @click="refresh">
          <i class="iconfont icon-refresh" />
        </div>
        <img
          ref="canvasRef"
          :src="pointBackImgBase ? 'data:image/png;base64,' + pointBackImgBase : defaultImg"
          alt=""
          style="width: 100%; height: 100%; display: block"
          @click="bindingClick ? canvasClick($event) : undefined"
        >

        <div
          v-for="(tempPoint, index) in tempPoints"
          :key="index"
          class="point-area"
          :style="{
            'background-color': '#1abd6c',
            color: '#fff',
            'z-index': 9999,
            width: '20px',
            height: '20px',
            'text-align': 'center',
            'line-height': '20px',
            'border-radius': '50%',
            position: 'absolute',
            top: parseInt(tempPoint.y - 10) + 'px',
            left: parseInt(tempPoint.x - 10) + 'px',
          }"
        >
          {{ index + 1 }}
        </div>
      </div>
    </div>
    <!-- 'height': this.barSize.height, -->
    <div
      class="verify-bar-area"
      :style="{
        width: setSize.imgWidth,
        color: barAreaColor,
        'border-color': barAreaBorderColor,
        'line-height': barSize.height,
      }"
    >
      <span class="verify-msg">{{ text }}</span>
    </div>
  </div>
</template>
<script setup>
/**
 * VerifyPoints
 * @description 点选
 * */
import {
  getCurrentInstance,
  nextTick,
  onMounted,
  reactive,
  ref,
  watch
} from 'vue'
import { aesEncrypt } from './../utils/ase'
import { resetSize as calcResetSize } from './../utils/util'
import { ajCaptcha, ajCaptchaCheck } from '@/api/common'

defineOptions({
  name: 'VerifyPoints'
})

const props = defineProps({
  // 弹出式pop，固定fixed
  mode: {
    type: String,
    default: 'fixed'
  },
  captchaType: {
    type: String
  },
  // 间隔
  vSpace: {
    type: Number,
    default: 5
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
const canvasRef = ref(null)
const secretKey = ref('')
const checkNum = 3
const fontPos = ref([])
const checkPosArr = ref([])
const num = ref(1)
const pointBackImgBase = ref('')
const poinTextList = ref([])
const backToken = ref('')
const setSize = reactive({
  imgHeight: 0,
  imgWidth: 0,
  barHeight: 0,
  barWidth: 0
})
const tempPoints = ref([])
const text = ref('')
const barAreaColor = ref(undefined)
const barAreaBorderColor = ref(undefined)
const showRefresh = ref(true)
const bindingClick = ref(true)

watch(() => props.captchaType, () => {
  init()
}, { immediate: true })

onMounted(() => {
  if (rootRef.value) {
    rootRef.value.onselectstart = function() {
      return false
    }
  }
})

function init() {
  fontPos.value.splice(0, fontPos.value.length)
  checkPosArr.value.splice(0, checkPosArr.value.length)
  num.value = 1
  getPictrue()
  nextTick(() => {
    if (proxy) {
      Object.assign(setSize, calcResetSize.call(proxy, proxy))
      emit('ready', proxy)
    }
  })
}

function canvasClick(e) {
  if (!canvasRef.value) {
    return
  }
  checkPosArr.value.push(getMousePos(canvasRef.value, e))
  if (num.value === checkNum) {
    num.value = createPoint(getMousePos(canvasRef.value, e))
    checkPosArr.value = pointTransfrom(checkPosArr.value, setSize)
    setTimeout(() => {
      const captchaVerification = secretKey.value
        ? aesEncrypt(backToken.value + '---' + JSON.stringify(checkPosArr.value), secretKey.value)
        : backToken.value + '---' + JSON.stringify(checkPosArr.value)
      const data = {
        captchaType: props.captchaType,
        pointJson: secretKey.value
          ? aesEncrypt(JSON.stringify(checkPosArr.value), secretKey.value)
          : JSON.stringify(checkPosArr.value),
        token: backToken.value
      }
      ajCaptchaCheck(data).then((res) => {
        if (res.repCode === '0000') {
          barAreaColor.value = '#4cae4c'
          barAreaBorderColor.value = '#5cb85c'
          text.value = '验证成功'
          bindingClick.value = false
          if (props.mode === 'pop') {
            setTimeout(() => {
              emit('close')
            }, 1500)
          }
          emit('success', { captchaVerification })
        } else {
          emit('error', proxy || null)
          barAreaColor.value = '#d9534f'
          barAreaBorderColor.value = '#d9534f'
          text.value = '验证失败'
          setTimeout(() => {
            refresh()
          }, 700)
        }
      })
    }, 400)
  }
  if (num.value < checkNum) {
    num.value = createPoint(getMousePos(canvasRef.value, e))
  }
}

function getMousePos(obj, e) {
  const x = e.offsetX
  const y = e.offsetY
  return { x, y }
}

function createPoint(pos) {
  tempPoints.value.push(Object.assign({}, pos))
  return ++num.value
}

function refresh() {
  tempPoints.value.splice(0, tempPoints.value.length)
  barAreaColor.value = '#000'
  barAreaBorderColor.value = '#ddd'
  bindingClick.value = true
  fontPos.value.splice(0, fontPos.value.length)
  checkPosArr.value.splice(0, checkPosArr.value.length)
  num.value = 1
  getPictrue()
  text.value = '验证失败'
  showRefresh.value = true
}

function getPictrue() {
  const data = {
    captchaType: props.captchaType,
    clientUid: localStorage.getItem('point'),
    ts: Date.now()
  }
  ajCaptcha(data).then((res) => {
    if (res.repCode === '0000') {
      pointBackImgBase.value = res.repData.originalImageBase64
      backToken.value = res.repData.token
      secretKey.value = res.repData.secretKey
      poinTextList.value = res.repData.wordList
      text.value = '请依次点击【' + poinTextList.value.join(',') + '】'
    } else {
      text.value = res.repMsg
    }

    if (res.repCode === '6201') {
      pointBackImgBase.value = null
    }
  })
}

function pointTransfrom(pointArr, imgSize) {
  const newPointArr = pointArr.map((p) => {
    const x = Math.round((310 * p.x) / parseInt(imgSize.imgWidth))
    const y = Math.round((155 * p.y) / parseInt(imgSize.imgHeight))
    return { x, y }
  })
  return newPointArr
}

defineExpose({
  refresh,
  init
})
</script>
