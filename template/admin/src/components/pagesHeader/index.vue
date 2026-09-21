<template>
  <el-card
    shadow="never"
    class="box-card"
  >
    <div class="acea-row row-middle">
      <div
        class="font-sm after-line"
        @click="goBack"
      >
        <LegacyIcon name="el-icon-arrow-left" />
        <span class="pl10">返回</span>
      </div>
      <span class="ht_title ml10">{{ title }}</span>
    </div>
  </el-card>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
const { title, backUrl } = defineProps({
  title: {
    type: String,
    default: ''
  },
  backUrl: {
    type: String,
    default: ''
  }
})

function goBack() {
  if (backUrl) {
    router.push({
      path: backUrl
    })
  } else {
    router.back()
  }
}
</script>

<style scoped>
.after-line {
  display: inline-block;
  position: relative;
  margin-right: 16px;
  color: rgba(0, 0, 0, 0.85);
  cursor: pointer;
}
.after-line:after {
  content: '';
  position: absolute;
  top: 0;
  right: -16px;
  width: 1px;
  height: 16px;
  background: #eee;
}
.font-sm {
  font-size: 12px;
}
.ht_title {
  font-weight: 500;
  font-size: 18px;
}
</style>
