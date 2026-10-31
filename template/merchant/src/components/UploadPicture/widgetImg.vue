<template>
  <div class="box">
    <upload-pictures
      v-if="route.query.fodder === 'dialog' || route.query.type === 'many'"
      :is-choice="isChoiceD"
      :grid-pic="gridPic"
      :grid-btn="gridBtn"
      :is-iframe="isIframe"
      @getPicD="getPicD"
    />
    <upload-pictures
      v-else
      :is-choice="isChoice"
      :is-iframe="isIframe"
      :grid-pic="gridPic"
      :grid-btn="gridBtn"
      @getPic="getPic"
    />
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router'
import UploadPictures from './index.vue'

defineOptions({
  name: 'WidgetImg'
})

const route = useRoute()
const isChoice = '单选'
const isChoiceD = '多选'
const isIframe = true // 是否全屏打开选择图片
const gridPic = {
  xl: 4,
  lg: 4,
  md: 8,
  sm: 12,
  xs: 12
}
const gridBtn = {
  xl: 4,
  lg: 4,
  md: 4,
  sm: 8,
  xs: 8
}

function getPicD(pc) {
  const fodder = route.query.fodder
  const pcs = window.form_create_helper.get(fodder) || []
  const paths = pc.map((item) => {
    return item.path
  })
  const concatPc = pcs.concat(paths)
  const pcList = Array.from(new Set(concatPc))
  window.form_create_helper.set(fodder, pcList)
  window.form_create_helper.close(fodder)
}

function getPic(pc) {
  const fodder = route.query.fodder
  window.form_create_helper.set(fodder, pc.path)
  window.form_create_helper.close(fodder)
}
</script>

<style scoped lang="stylus">
.box {
  width: 100%;
  background: #fff;
}
</style>
