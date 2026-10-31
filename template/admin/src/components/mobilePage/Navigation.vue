<template>
  <common-wrapper :config="config">
    <div class="nav-grid" :style="gridStyle">
      <div v-if="icons.length === 0" class="nav-empty">
        <LegacyIcon name="el-icon-s-grid" />
        <span>导航图标</span>
      </div>
      <div
        v-for="(icon, index) in icons"
        :key="index"
        class="nav-item"
        :style="itemStyle"
      >
        <div class="nav-icon" :style="imageRadiusStyle">
          <img v-if="icon.image" :src="icon.image" alt="">
          <LegacyIcon v-else-if="icon.icon" :name="icon.icon" />
          <LegacyIcon v-else name="el-icon-picture" />
        </div>
        <div v-if="showText" class="nav-text">{{ icon.text || '导航' }}</div>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'
import { getImageRadiusStyle } from './imageRadius'

defineOptions({
  name: 'Navigation',
  cname: '导航',
  icon: '#iconzujian-daohang',
  defaultName: 'homeMenu',
  configName: 'c_navigation',
  type: 0
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const icons = ref([])
const columns = ref(4)
const style = ref('grid')
const showText = ref(true)
const bgColor = ref('#ffffff')
const textColor = ref('#333333')

const gridStyle = computed(() => ({
  background: bgColor.value
}))

const itemStyle = computed(() => {
  const gap = 8
  return {
    width: `calc(${100 / columns.value}% - ${gap}px)`
  }
})

const imageRadiusStyle = computed(() => getImageRadiusStyle(props.config && props.config.style_config))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function normalizeColumns(value, defaultValue = 4) {
  const nextValue = Number(value)
  return [4, 5, 6].includes(nextValue) ? nextValue : defaultValue
}

function normalizeSwitchValue(value, defaultValue = true) {
  if (value === undefined || value === null || value === '') {
    return defaultValue
  }
  if (value === true || value === 'true' || value === 1 || value === '1') {
    return true
  }
  if (value === false || value === 'false' || value === 0 || value === '0') {
    return false
  }
  return Boolean(value)
}

function resolveShowText(config = {}) {
  if (config.show_title !== undefined) {
    return normalizeSwitchValue(config.show_title, true)
  }
  if (config.show_text !== undefined) {
    return normalizeSwitchValue(config.show_text, true)
  }
  return true
}

function setConfig(data) {
  if (!data) return
  if (data.items !== undefined) {
    icons.value = (data.items || []).map(item => ({
      image: item.icon || '',
      text: item.name || '',
      link: item.link_url || ''
    }))
    columns.value = normalizeColumns(data.columns, 4)
    style.value = 'grid'
    showText.value = resolveShowText(data)
    bgColor.value = data.bg_color || '#ffffff'
    textColor.value = data.text_color || '#333333'
  } else if (data.icons !== undefined) {
    icons.value = data.icons || []
    columns.value = normalizeColumns(data.columns, 5)
    style.value = data.style || 'grid'
    showText.value = resolveShowText(data)
    bgColor.value = data.bg_color || '#ffffff'
    textColor.value = data.text_color || '#333333'
  }
}
</script>

<style scoped lang="scss">
.nav-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  background: #fff;
}

.nav-empty {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px 0;
  color: #c0c4cc;
  font-size: 12px;

  i {
    font-size: 32px;
    margin-bottom: 8px;
  }
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 4px;
  cursor: pointer;
}

.nav-icon {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin-bottom: 6px;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  i {
    font-size: 24px;
    color: #909399;
  }
}

.nav-text {
  font-size: 10px;
  color: #606266;
  text-align: center;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
