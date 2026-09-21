<template>
  <div class="component-style-editor">
    <!-- 图片样式 -->
    <div v-if="showImageStyleSection" class="style-section">
      <div class="style-section__title">图片样式</div>

      <el-form label-width="80px">
        <el-form-item label="图片圆角">
          <div class="radius-mode-row">
            <span class="radius-mode-label">{{ localConfig.image_radius_mode === 'all' ? '全部' : '独立' }}</span>
            <div class="radius-mode-btns">
              <span
                class="radius-mode-btn"
                :class="{ active: localConfig.image_radius_mode === 'all' }"
                @click="localConfig.image_radius_mode = 'all'"
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="2" width="14" height="14" rx="3" stroke="currentColor" stroke-width="1.5" /></svg>
              </span>
              <span
                class="radius-mode-btn"
                :class="{ active: localConfig.image_radius_mode === 'each' }"
                @click="localConfig.image_radius_mode = 'each'"
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M5 2H2v3M16 5V2h-3M13 16h3v-3M2 13v3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              </span>
            </div>
          </div>
        </el-form-item>

        <!-- 全部模式 -->
        <template v-if="localConfig.image_radius_mode === 'all'">
          <el-form-item label="圆角大小">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.image_radius" :min="0" :max="100" class="slider-main" />
              <div class="number-input-with-unit">
                <el-input-number v-model="localConfig.image_radius" :min="0" :max="100" :controls="false" class="number-input" />
                <span class="number-unit">px</span>
              </div>
            </div>
          </el-form-item>
        </template>

        <!-- 独立模式 -->
        <template v-else>
          <el-form-item label="左上">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.image_radius_lt" :min="0" :max="100" class="slider-main" />
              <div class="number-input-with-unit">
                <el-input-number v-model="localConfig.image_radius_lt" :min="0" :max="100" :controls="false" class="number-input" />
                <span class="number-unit">px</span>
              </div>
            </div>
          </el-form-item>
          <el-form-item label="右上">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.image_radius_rt" :min="0" :max="100" class="slider-main" />
              <div class="number-input-with-unit">
                <el-input-number v-model="localConfig.image_radius_rt" :min="0" :max="100" :controls="false" class="number-input" />
                <span class="number-unit">px</span>
              </div>
            </div>
          </el-form-item>
          <el-form-item label="左下">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.image_radius_lb" :min="0" :max="100" class="slider-main" />
              <div class="number-input-with-unit">
                <el-input-number v-model="localConfig.image_radius_lb" :min="0" :max="100" :controls="false" class="number-input" />
                <span class="number-unit">px</span>
              </div>
            </div>
          </el-form-item>
          <el-form-item label="右下">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.image_radius_rb" :min="0" :max="100" class="slider-main" />
              <div class="number-input-with-unit">
                <el-input-number v-model="localConfig.image_radius_rb" :min="0" :max="100" :controls="false" class="number-input" />
                <span class="number-unit">px</span>
              </div>
            </div>
          </el-form-item>
        </template>

        <!-- 格子间距（仅图片魔方） -->
        <el-form-item v-if="componentType === 'image_cube'" label="格子间距">
          <div class="slider-input-row">
            <el-slider v-model="localConfig.gap" :min="0" :max="20" class="slider-main" />
            <div class="number-input-with-unit">
              <el-input-number v-model="localConfig.gap" :min="0" :max="20" :controls="false" class="number-input" />
              <span class="number-unit">px</span>
            </div>
          </div>
        </el-form-item>
      </el-form>
    </div>

    <div class="style-divider" />

    <!-- 通用样式 -->
    <div class="style-section">
      <div class="style-section__title">通用样式</div>

      <el-form label-width="80px">
        <!-- 图标背景色（仅导航组件显示） -->
        <el-form-item v-if="componentType === 'navigation'" label="图标背景色">
          <div class="color-row">
            <el-color-picker v-model="localConfig.icon_bg_color" show-alpha size="mini" class="color-picker-sm" />
            <el-input v-model="localConfig.icon_bg_color" class="color-input" />
            <span class="reset-link" @click="localConfig.icon_bg_color = ''">重置</span>
          </div>
        </el-form-item>

        <!-- 指示器颜色（仅轮播图组件显示） -->
        <el-form-item v-if="componentType === 'banner'" label="指示器颜色">
          <div class="color-row">
            <el-color-picker v-model="localConfig.indicator_color" show-alpha size="mini" class="color-picker-sm" />
            <el-input v-model="localConfig.indicator_color" class="color-input" />
            <span class="reset-link" @click="localConfig.indicator_color = ''">重置</span>
          </div>
        </el-form-item>

        <!-- 组件背景（渐变双色） -->
        <el-form-item v-if="showBackgroundStyleSection" label="组件背景">
          <div class="gradient-bg-wrap">
            <div class="color-row">
              <el-color-picker v-model="localConfig.bg_color_start" show-alpha size="mini" class="color-picker-sm" />
              <el-input v-model="localConfig.bg_color_start" class="color-input" />
              <span class="reset-link" @click="localConfig.bg_color_start = '#ffffff'">重置</span>
            </div>
            <div class="gradient-connector">
              <span class="connector-icon" />
            </div>
            <div class="color-row">
              <el-color-picker v-model="localConfig.bg_color_end" show-alpha size="mini" class="color-picker-sm" />
              <el-input v-model="localConfig.bg_color_end" class="color-input" />
              <span class="reset-link" @click="localConfig.bg_color_end = '#ffffff'">重置</span>
            </div>
          </div>
        </el-form-item>

        <!-- 底部背景 -->
        <el-form-item v-if="showBackgroundStyleSection" label="底部背景">
          <div class="color-row">
            <el-color-picker v-model="localConfig.bottom_bg_color" show-alpha size="mini" class="color-picker-sm" />
            <el-input v-model="localConfig.bottom_bg_color" class="color-input" />
            <span class="reset-link" @click="localConfig.bottom_bg_color = '#f5f5f5'">重置</span>
          </div>
        </el-form-item>

        <!-- 上边距 -->
        <el-form-item label="上边距">
          <div class="slider-input-row">
            <el-slider v-model="localConfig.margin_top" :min="0" :max="100" class="slider-main" />
            <el-input-number v-model="localConfig.margin_top" :min="0" :max="100" :controls="false" class="number-input" />
          </div>
        </el-form-item>

        <!-- 下边距 -->
        <el-form-item label="下边距">
          <div class="slider-input-row">
            <el-slider v-model="localConfig.margin_bottom" :min="0" :max="100" class="slider-main" />
            <el-input-number v-model="localConfig.margin_bottom" :min="0" :max="100" :controls="false" class="number-input" />
          </div>
        </el-form-item>

        <!-- 左右外边距 -->
        <el-form-item label="左右外边距">
          <div class="slider-input-row">
            <el-slider v-model="localConfig.padding_lr" :min="0" :max="50" class="slider-main" />
            <el-input-number v-model="localConfig.padding_lr" :min="0" :max="50" :controls="false" class="number-input" />
          </div>
        </el-form-item>

        <!-- 左右内边距 -->
        <el-form-item label="左右内边距">
          <div class="slider-input-row">
            <el-slider v-model="localConfig.padding_x" :min="0" :max="50" class="slider-main" />
            <el-input-number v-model="localConfig.padding_x" :min="0" :max="50" :controls="false" class="number-input" />
          </div>
        </el-form-item>

        <!-- 页面上间距 -->
        <el-form-item label="页面上间距">
          <div class="slider-input-row">
            <el-slider v-model="localConfig.page_margin_top" :min="0" :max="100" class="slider-main" />
            <el-input-number v-model="localConfig.page_margin_top" :min="0" :max="100" :controls="false" class="number-input" />
          </div>
        </el-form-item>

        <!-- 背景圆角模式 -->
        <el-form-item label="背景圆角">
          <div class="radius-mode-row">
            <span class="radius-mode-label">{{ localConfig.radius_mode === 'all' ? '全部' : '独立' }}</span>
            <div class="radius-mode-btns">
              <span
                class="radius-mode-btn"
                :class="{ active: localConfig.radius_mode === 'all' }"
                @click="localConfig.radius_mode = 'all'"
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="2" width="14" height="14" rx="3" stroke="currentColor" stroke-width="1.5" /></svg>
              </span>
              <span
                class="radius-mode-btn"
                :class="{ active: localConfig.radius_mode === 'each' }"
                @click="localConfig.radius_mode = 'each'"
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M5 2H2v3M16 5V2h-3M13 16h3v-3M2 13v3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
              </span>
            </div>
          </div>
        </el-form-item>

        <!-- 全部模式 -->
        <template v-if="localConfig.radius_mode === 'all'">
          <el-form-item label="圆角值">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.border_radius" :min="0" :max="100" class="slider-main" />
              <el-input-number v-model="localConfig.border_radius" :min="0" :max="100" :controls="false" class="number-input" />
            </div>
          </el-form-item>
        </template>

        <!-- 独立模式 -->
        <template v-else>
          <el-form-item label="左上">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.border_radius_lt" :min="0" :max="100" class="slider-main" />
              <el-input-number v-model="localConfig.border_radius_lt" :min="0" :max="100" :controls="false" class="number-input" />
            </div>
          </el-form-item>
          <el-form-item label="右上">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.border_radius_rt" :min="0" :max="100" class="slider-main" />
              <el-input-number v-model="localConfig.border_radius_rt" :min="0" :max="100" :controls="false" class="number-input" />
            </div>
          </el-form-item>
          <el-form-item label="左下">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.border_radius_lb" :min="0" :max="100" class="slider-main" />
              <el-input-number v-model="localConfig.border_radius_lb" :min="0" :max="100" :controls="false" class="number-input" />
            </div>
          </el-form-item>
          <el-form-item label="右下">
            <div class="slider-input-row">
              <el-slider v-model="localConfig.border_radius_rb" :min="0" :max="100" class="slider-main" />
              <el-input-number v-model="localConfig.border_radius_rb" :min="0" :max="100" :controls="false" class="number-input" />
            </div>
          </el-form-item>
        </template>
      </el-form>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, toRefs, watch } from 'vue'

const IMAGE_STYLE_COMPONENTS = ['banner', 'navigation', 'image_cube', 'content', 'live', 'special', 'product', 'seckill', 'hot_zone']

const DEFAULT_STYLE_CONFIG = {
  image_radius_mode: 'all',
  image_radius: 0,
  image_radius_lt: 0,
  image_radius_rt: 0,
  image_radius_lb: 0,
  image_radius_rb: 0,
  gap: 4,

  icon_bg_color: '',
  indicator_color: '',
  bg_color_start: '#ffffff',
  bg_color_end: '#ffffff',
  bottom_bg_color: '#f5f5f5',
  margin_top: 0,
  margin_bottom: 0,
  padding_lr: 0,
  padding_x: 10,
  page_margin_top: 0,
  radius_mode: 'all',
  border_radius: 0,
  border_radius_lt: 0,
  border_radius_rt: 0,
  border_radius_lb: 0,
  border_radius_rb: 0
}

defineOptions({
  name: 'ComponentStyleEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  componentType: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['change'])
const { config, componentType } = toRefs(props)
const syncingFromProp = ref(false)
const localConfig = reactive({ ...DEFAULT_STYLE_CONFIG })

const showImageStyleSection = computed(() => IMAGE_STYLE_COMPONENTS.includes(componentType.value))
const showBackgroundStyleSection = computed(() => componentType.value !== 'search')

const numericFields = [
  'image_radius', 'image_radius_lt', 'image_radius_rt', 'image_radius_lb', 'image_radius_rb',
  'gap',
  'margin_top', 'margin_bottom', 'padding_lr', 'padding_x', 'page_margin_top',
  'border_radius', 'border_radius_lt', 'border_radius_rt', 'border_radius_lb', 'border_radius_rb'
]

function syncConfig(val) {
  syncingFromProp.value = true
  // 仅用传入值覆盖默认值，不会将默认值写回父组件
  const incoming = (val && typeof val === 'object' && !Array.isArray(val)) ? val : {}
  const merged = { ...DEFAULT_STYLE_CONFIG, ...incoming }
  // 确保数值型字段为 Number
  numericFields.forEach(key => {
    if (merged[key] !== undefined && merged[key] !== null) {
      merged[key] = Number(merged[key]) || 0
    }
  })
  Object.assign(localConfig, merged)
  nextTick(() => {
    syncingFromProp.value = false
  })
}

watch(config, syncConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, () => {
  if (syncingFromProp.value) return
  emit('change', { ...localConfig })
}, {
  deep: true
})
</script>

<style lang="scss" scoped>
.component-style-editor {
  padding: 4px 0;
}

.style-section {
  padding: 0 0 8px;

  &__title {
    font-size: 13px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
    margin-bottom: 12px;
    padding-left: 2px;
  }
}

.style-divider {
  height: 1px;
  background: var(--dashboard-border-soft);
  margin: 12px 0 16px;
}

.slider-input-row {
  display: flex;
  align-items: center;
  gap: 10px;

  .slider-main {
    flex: 1;
    min-width: 0;
  }

  .number-input-with-unit {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }

  .number-input {
    width: 60px;
    flex-shrink: 0;

    :deep(.el-input__inner) {
      text-align: center;
      padding: 0 4px;
    }
  }

  .number-unit {
    font-size: 12px;
    color: var(--dashboard-text-muted);
    line-height: 1;
  }
}

.radius-mode-row {
  display: flex;
  align-items: center;
  gap: 12px;

  .radius-mode-label {
    font-size: 12px;
    color: var(--dashboard-text-secondary);
    min-width: 24px;
  }

  .radius-mode-btns {
    display: flex;
    gap: 4px;
  }

  .radius-mode-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid var(--dashboard-border);
    border-radius: 4px;
    cursor: pointer;
    color: var(--dashboard-text-muted);
    background: var(--dashboard-surface);
    transition: all 0.2s;

    &:hover {
      border-color: var(--prev-color-primary);
      color: var(--prev-color-primary);
    }

    &.active {
      border-color: var(--prev-color-primary);
      background: var(--prev-color-primary-light-9);
      color: var(--prev-color-primary);
    }

    svg {
      pointer-events: none;
    }
  }
}

.gradient-bg-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.color-row {
  display: flex;
  align-items: center;
  gap: 8px;

  .color-picker-sm {
    flex-shrink: 0;
  }

  .color-input {
    flex: 1;
    min-width: 0;
  }

  .reset-link {
    flex-shrink: 0;
    font-size: 12px;
    color: var(--prev-color-primary);
    cursor: pointer;
    white-space: nowrap;

    &:hover {
      color: var(--dashboard-accent-light);
    }
  }
}

.gradient-connector {
  display: flex;
  align-items: center;
  padding-left: 12px;

  .connector-icon {
    display: block;
    width: 16px;
    height: 20px;
    border-left: 2px solid var(--dashboard-border);
    border-bottom: 2px solid var(--dashboard-border);
    border-bottom-left-radius: 4px;
    position: relative;
    top: -4px;
  }
}
</style>
