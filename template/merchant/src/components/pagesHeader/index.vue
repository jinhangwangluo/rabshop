<template>
  <div class="page-curator-inline-header">
    <div class="page-curator-back" @click="goBack">
      <LegacyIcon name="el-icon-arrow-left" />
      <span>返回</span>
    </div>
    <span class="page-curator-inline-title">{{ title }}</span>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

defineOptions({
  name: 'PagesHeader'
})

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

<style scoped lang="scss">
.page-curator-inline-header {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-height: 36px;
}

.page-curator-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding-right: 12px;
  border-right: 1px solid var(--ds-outline-ghost);
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: color 0.2s ease;

  &:hover {
    color: var(--ds-primary-hover);
  }
}

.page-curator-inline-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--ds-on-surface);
}
</style>
