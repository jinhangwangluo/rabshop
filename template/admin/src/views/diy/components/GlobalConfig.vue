<template>
  <div class="global-config">
    <el-form :model="localConfig" label-width="100px">
      <!-- 背景设置 -->
      <div class="config-section">
        <div class="section-title">背景设置</div>
        <el-form-item label="背景类型">
          <el-radio-group v-model="localConfig.background.type">
            <el-radio label="color">纯色</el-radio>
            <el-radio label="gradient">渐变</el-radio>
            <el-radio label="image">图片</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 纯色背景 -->
        <el-form-item v-if="localConfig.background.type === 'color'" label="背景颜色">
          <el-color-picker v-model="localConfig.background.color" show-alpha />
          <el-input v-model="localConfig.background.color" placeholder="留空表示无颜色" style="width: 150px; margin-left: 10px" />
        </el-form-item>

        <!-- 渐变背景 -->
        <template v-if="localConfig.background.type === 'gradient'">
          <el-form-item label="渐变类型">
            <el-radio-group v-model="localConfig.background.gradient.type">
              <el-radio label="linear">线性渐变</el-radio>
              <el-radio label="radial">径向渐变</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="渐变角度">
            <el-slider v-model="localConfig.background.gradient.angle" :min="0" :max="360" show-input />
          </el-form-item>
          <el-form-item label="渐变颜色">
            <div v-for="(item, index) in localConfig.background.gradient.colors" :key="index" :title="item" class="color-item">
              <el-color-picker v-model="localConfig.background.gradient.colors[index]" />
              <el-input v-model="localConfig.background.gradient.colors[index]" style="width: 120px; margin-left: 10px" />
              <el-button type="danger" icon="el-icon-delete" circle @click="removeGradientColor(index)" />
            </div>
            <el-button type="primary" @click="addGradientColor">添加颜色</el-button>
          </el-form-item>
        </template>

        <!-- 图片背景 -->
        <template v-if="localConfig.background.type === 'image'">
          <el-form-item label="背景图片">
            <Upload v-model="localConfig.background.image" :limit="1" />
          </el-form-item>
          <el-form-item label="图片模式">
            <el-radio-group v-model="localConfig.background.image_mode">
              <el-radio label="cover">覆盖</el-radio>
              <el-radio label="repeat">平铺</el-radio>
            </el-radio-group>
          </el-form-item>
        </template>
      </div>

      <!-- 标题栏设置 -->
      <div class="config-section">
        <div class="section-title">标题栏设置</div>
        <el-form-item label="显示标题栏">
          <el-switch v-model="localConfig.title_bar.show" />
        </el-form-item>
        <template v-if="localConfig.title_bar.show">
          <el-form-item label="标题文字">
            <el-input v-model="localConfig.title_bar.title" placeholder="首页" />
          </el-form-item>
          <el-form-item label="背景颜色">
            <el-color-picker v-model="localConfig.title_bar.bg_color" />
            <el-input v-model="localConfig.title_bar.bg_color" style="width: 150px; margin-left: 10px" />
          </el-form-item>
          <el-form-item label="文字颜色">
            <el-color-picker v-model="localConfig.title_bar.text_color" />
            <el-input v-model="localConfig.title_bar.text_color" style="width: 150px; margin-left: 10px" />
          </el-form-item>
        </template>
      </div>

      <!-- 页面设置 -->
      <div class="config-section">
        <div class="section-title">页面设置</div>
        <el-form-item label="下拉刷新">
          <el-switch v-model="localConfig.pull_down_refresh" />
        </el-form-item>
        <el-form-item label="页面内边距">
          <el-slider v-model="localConfig.page_padding" :min="0" :max="60" :step="2" show-input />
          <span class="tips">单位：rpx</span>
        </el-form-item>
      </div>
    </el-form>
  </div>
</template>

<script setup>
import { getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'

function createDefaultConfig() {
  return {
    background: {
      type: 'color',
      color: '#f5f5f5',
      gradient: {
        type: 'linear',
        angle: 180,
        colors: ['#f5f5f5', '#f5f5f5']
      },
      image: '',
      image_mode: 'cover'
    },
    title_bar: {
      show: true,
      title: '首页',
      bg_color: '#ffffff',
      text_color: '#333333'
    },
    pull_down_refresh: true,
    page_padding: 24
  }
}

defineOptions({
  name: 'GlobalConfig'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const { proxy } = getCurrentInstance()
const localConfig = reactive(createDefaultConfig())
const syncingFromParent = ref(false)

function mergeConfig(defaultConfig, userConfig) {
  const result = { ...defaultConfig }
  for (const key in userConfig) {
    if (typeof userConfig[key] === 'object' && userConfig[key] !== null && !Array.isArray(userConfig[key])) {
      result[key] = mergeConfig(defaultConfig[key] || {}, userConfig[key])
    } else {
      result[key] = userConfig[key]
    }
  }
  return result
}

function syncConfig(val) {
  const nextConfig = mergeConfig(createDefaultConfig(), val || {})
  if (JSON.stringify(nextConfig) === JSON.stringify(localConfig)) {
    return
  }
  syncingFromParent.value = true
  Object.assign(localConfig, nextConfig)
  nextTick(() => {
    syncingFromParent.value = false
  })
}

watch(() => props.config, syncConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, val => {
  if (syncingFromParent.value) {
    return
  }
  emit('change', JSON.parse(JSON.stringify(val)))
}, {
  deep: true
})

function addGradientColor() {
  localConfig.background.gradient.colors.push('#ffffff')
}

function removeGradientColor(index) {
  if (localConfig.background.gradient.colors.length > 2) {
    localConfig.background.gradient.colors.splice(index, 1)
  } else {
    proxy.$message.warning('至少需要两个颜色')
  }
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.global-config {
  .config-section {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--dashboard-border-soft);

    &:last-child {
      border-bottom: none;
    }
  }

  .section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
    margin-bottom: 16px;
    padding-left: 8px;
    border-left: 3px solid var(--prev-color-primary);
  }

  .color-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
  }

  .tips {
    font-size: 12px;
    color: var(--dashboard-text-muted);
    margin-left: 10px;
  }
}
</style>
