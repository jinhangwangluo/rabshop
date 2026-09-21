<template>
  <div class="search-editor">
    <el-form :model="localConfig" label-width="100px">
      <el-form-item label="占位文字">
        <el-input v-model="localConfig.placeholder" placeholder="搜索课程、专题、直播" />
      </el-form-item>

      <el-form-item label="左侧图标">
        <el-switch v-model="localConfig.show_left_icon" />
      </el-form-item>

      <el-form-item v-if="localConfig.show_left_icon" label="图标图片">
        <Upload
          :model-value="localConfig.logoConfig.url"
          :limit="1"
          class="search-icon-upload"
          @update:modelValue="handleIconChange"
        />
        <div class="form-tip">不上传时使用默认搜索图标</div>
      </el-form-item>

      <el-form-item label="搜索框样式">
        <el-radio-group v-model="localConfig.shape">
          <el-radio label="rounded">圆角</el-radio>
          <el-radio label="square">方形</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="框体颜色">
        <el-color-picker v-model="localConfig.box_bg_color" />
        <el-input v-model="localConfig.box_bg_color" class="color-input" />
      </el-form-item>

      <el-form-item label="文字颜色">
        <el-color-picker v-model="localConfig.text_color" />
        <el-input v-model="localConfig.text_color" class="color-input" />
      </el-form-item>

      <el-form-item label="跳转链接">
        <DiyLinkSelectField
          :model-value="localConfig.link_url"
          :link-id="localConfig.link_id"
          :link-name="localConfig.link_name"
          :link-type="localConfig.link_type"
          :platform-type="localConfig.platform_type"
          placeholder="请选择搜索点击跳转链接"
          dialog-title="选择搜索跳转链接"
          @update:modelValue="val => { localConfig.link_url = val }"
          @select="handleLinkSelect"
          @clear="clearSelectedLink"
        />
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { reactive, ref, watch, nextTick } from 'vue'
import Upload from '@/components/Upload'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

const DEFAULT_SEARCH_CONFIG = {
  placeholder: '搜索课程、专题、直播',
  show_left_icon: true,
  logoConfig: { url: '' },
  shape: 'rounded',
  box_bg_color: '#ffffff',
  text_color: '#8a8f98',
  link_type: 'page',
  link_url: '/pages/search/index',
  link_id: 0,
  link_name: '',
  platform_type: ''
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function pickValue(value, fallback = '') {
  if (value === undefined || value === null) {
    return fallback
  }
  if (isPlainObject(value)) {
    if (value.value !== undefined) return value.value
    if (value.val !== undefined) return value.val
    if (value.tabVal !== undefined) return value.tabVal
  }
  return value
}

function firstColor(value, fallback = '') {
  if (typeof value === 'string') {
    return value || fallback
  }
  if (Array.isArray(value)) {
    return firstColor(value[0], fallback)
  }
  if (isPlainObject(value)) {
    return firstColor(value.item || value.color, fallback)
  }
  return fallback
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

defineOptions({
  name: 'SearchEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const localConfig = reactive(JSON.parse(JSON.stringify(DEFAULT_SEARCH_CONFIG)))
const fromProp = ref(false)
const lastEmit = ref('')

function normalizeConfig(config = {}) {
  const source = config && typeof config === 'object' ? config : {}
  const logoConfig = isPlainObject(source.logoConfig) ? source.logoConfig : {}
  const editorLink = isPlainObject(source.__editor_link) ? source.__editor_link : {}
  const iconUrl = logoConfig.url || source.left_icon_url || source.icon_url || source.left_icon || ''
  return {
    placeholder: source.placeholder || pickValue(source.tipConfig, DEFAULT_SEARCH_CONFIG.placeholder) || DEFAULT_SEARCH_CONFIG.placeholder,
    show_left_icon: normalizeSwitchValue(source.show_left_icon, DEFAULT_SEARCH_CONFIG.show_left_icon),
    logoConfig: { url: iconUrl },
    shape: source.shape === 'square' ? 'square' : 'rounded',
    box_bg_color: source.box_bg_color || firstColor(source.searchBoxColor, DEFAULT_SEARCH_CONFIG.box_bg_color),
    text_color: source.text_color || firstColor(source.tipColor, firstColor(source.txtColor, DEFAULT_SEARCH_CONFIG.text_color)),
    link_type: source.link_type || editorLink.link_type || DEFAULT_SEARCH_CONFIG.link_type,
    link_url: source.link_url || editorLink.link_url || DEFAULT_SEARCH_CONFIG.link_url,
    link_id: source.link_id || editorLink.link_id || 0,
    link_name: source.link_name || editorLink.link_name || '',
    platform_type: source.platform_type || editorLink.platform_type || ''
  }
}

function emitConfig() {
  const config = normalizeConfig(localConfig)
  lastEmit.value = JSON.stringify(config)
  emit('change', config)
}

function applyConfig(val) {
  const nextConfig = normalizeConfig(val)
  if (lastEmit.value && JSON.stringify(nextConfig) === lastEmit.value) return
  fromProp.value = true
  Object.assign(localConfig, nextConfig)
  nextTick(() => {
    fromProp.value = false
    emitConfig()
  })
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, () => {
  if (fromProp.value) return
  emitConfig()
}, {
  deep: true
})

function handleIconChange(url) {
  if (!localConfig.logoConfig || typeof localConfig.logoConfig !== 'object') {
    localConfig.logoConfig = { url: '' }
  }
  localConfig.logoConfig.url = url || ''
}

function handleLinkSelect(row) {
  localConfig.link_type = row.link_type || 'page'
  localConfig.link_url = row.link_url || ''
  localConfig.link_id = row.link_id || row.id || 0
  localConfig.link_name = row.link_name || ''
  localConfig.platform_type = row.platform_type || ''
}

function clearSelectedLink() {
  localConfig.link_id = 0
  localConfig.link_name = ''
  localConfig.platform_type = ''
}

function getConfig() {
  return normalizeConfig(localConfig)
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.search-editor {
  .color-input {
    width: 150px;
    margin-left: 10px;
  }

  .search-icon-upload {
    display: inline-block;
    vertical-align: top;
  }

  .form-tip {
    margin-top: 6px;
    font-size: 12px;
    line-height: 18px;
    color: #909399;
  }
}
</style>
