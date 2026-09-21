<template>
  <div class="notice-editor">
    <el-form :model="localConfig" label-width="100px">
      <el-form-item label="组件名称">
        <el-input v-model="localConfig.title" placeholder="公告" />
      </el-form-item>
      <el-form-item label="公告图标">
        <Upload v-model="localConfig.icon" :limit="1" />
      </el-form-item>
      <el-form-item label="滚动方式">
        <el-radio-group v-model="localConfig.scroll_type">
          <el-radio label="scroll">滚动</el-radio>
          <el-radio label="static">静止</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="背景颜色">
        <el-color-picker v-model="localConfig.bg_color" show-alpha />
        <el-input v-model="localConfig.bg_color" style="width: 150px; margin-left: 10px" />
      </el-form-item>
      <el-form-item label="文字颜色">
        <el-color-picker v-model="localConfig.text_color" />
        <el-input v-model="localConfig.text_color" style="width: 150px; margin-left: 10px" />
      </el-form-item>
      <el-form-item label="公告内容">
        <div v-for="(item, index) in localConfig.items" :key="index" class="notice-item">
          <el-input v-model="item.text" type="textarea" :rows="2" placeholder="公告内容" />
          <div class="notice-link-row">
            <DiyLinkSelectField
              class="link-selector"
              :model-value="item.link_url"
              :link-id="item.link_id"
              :link-name="item.link_name"
              :link-type="item.link_type"
              :platform-type="item.platform_type"
              placeholder="请选择或输入链接地址"
              dialog-title="选择公告链接"
              @update:modelValue="val => { item.link_url = val }"
              @select="row => handleLinkSelect(index, row)"
              @clear="() => clearSelectedLink(index)"
            />
            <LegacyIcon name="el-icon-circle-close" class=" notice-remove" @click="removeNotice(index)" />
          </div>
        </div>
        <el-button type="primary" @click="addNotice">添加公告</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'NoticeEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const fromProp = ref(false)
const localConfig = reactive({
  title: '公告',
  icon: '',
  scroll_type: 'scroll',
  bg_color: '#fffbe6',
  text_color: '#ed6a0c',
  items: [normalizeItem({ text: '欢迎使用DIY装修功能，可以自由配置首页展示内容', link_type: '', link_url: '' })]
})

function normalizeItem(item = {}) {
  return {
    text: item.text || '',
    link_type: item.link_type || '',
    link_url: item.link_url || '',
    link_id: item.link_id || 0,
    link_name: item.link_name || '',
    platform_type: item.platform_type || ''
  }
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined ? val.title : '公告',
    icon: val.icon || '',
    scroll_type: val.scroll_type || 'scroll',
    bg_color: val.bg_color || '#fffbe6',
    text_color: val.text_color || '#ed6a0c',
    items: Array.isArray(val.items) && val.items.length > 0
      ? val.items.map(item => normalizeItem(item))
      : [normalizeItem({ text: '', link_type: '', link_url: '' })]
  })
  nextTick(() => {
    fromProp.value = false
  })
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, val => {
  if (fromProp.value) return
  emit('change', {
    ...val,
    items: val.items.map(item => normalizeItem(item))
  })
}, {
  deep: true
})

function handleLinkSelect(index, row) {
  const current = normalizeItem(localConfig.items[index])
  localConfig.items[index] = {
    ...current,
    link_type: row.link_type || current.link_type,
    link_url: row.link_url || '',
    link_id: row.id || 0,
    link_name: row.link_name || '',
    platform_type: row.platform_type || ''
  }
}

function clearSelectedLink(index) {
  const current = normalizeItem(localConfig.items[index])
  localConfig.items[index] = {
    ...current,
    link_id: 0,
    link_name: '',
    platform_type: ''
  }
}

function addNotice() {
  localConfig.items.push(normalizeItem({ text: '', link_type: '', link_url: '' }))
}

function removeNotice(index) {
  localConfig.items.splice(index, 1)
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.notice-editor {
  .notice-item {
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
  }

  .notice-link-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 8px;
  }

  .link-selector {
    flex: 1;
    min-width: 0;
  }

  .notice-remove {
    flex-shrink: 0;
    margin-top: 8px;
    font-size: 16px;
    line-height: 1;
    color: #c0c4cc;
    cursor: pointer;
    transition: color 0.2s;

    &:hover {
      color: #f56c6c;
    }
  }
}
</style>
