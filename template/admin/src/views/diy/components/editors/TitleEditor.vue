<template>
  <div class="title-editor">
    <el-form label-width="100px">
      <el-form-item label="标题文字">
        <el-input v-model="localConfig.title" placeholder="请输入标题" />
      </el-form-item>
      <el-form-item label="副标题">
        <el-input v-model="localConfig.subtitle" placeholder="请输入副标题（可选）" />
      </el-form-item>
      <el-form-item label="标题样式">
        <el-radio-group v-model="localConfig.style">
          <el-radio label="normal">普通</el-radio>
          <el-radio label="bold">加粗</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="对齐方式">
        <el-radio-group v-model="localConfig.align">
          <el-radio label="left">左对齐</el-radio>
          <el-radio label="center">居中</el-radio>
          <el-radio label="right">右对齐</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="文字颜色">
        <el-color-picker v-model="localConfig.text_color" />
        <el-input v-model="localConfig.text_color" style="width: 110px; margin-left: 8px" />
      </el-form-item>
      <el-form-item label="背景颜色">
        <el-color-picker v-model="localConfig.bg_color" show-alpha />
        <el-input v-model="localConfig.bg_color" style="width: 110px; margin-left: 8px" />
      </el-form-item>
      <el-form-item label="显示更多">
        <el-switch v-model="localConfig.show_more" />
      </el-form-item>
    </el-form>

    <div v-if="localConfig.show_more" class="more-link-card">
      <div class="card-header">更多链接</div>
      <div class="card-body">
        <div class="card-field-row">
          <span class="field-label">类型</span>
          <el-select v-model="localConfig.more_link_type" placeholder="链接类型" class="field-select">
            <el-option label="页面" value="page" />
            <el-option label="外链" value="url" />
            <el-option label="小程序" value="mini" />
          </el-select>
        </div>
        <div class="card-field-row card-field-row--link">
          <span class="field-label">链接</span>
          <DiyLinkSelectField
            :model-value="localConfig.more_link_url"
            :link-id="localConfig.more_link_id"
            :link-name="localConfig.more_link_name"
            :link-type="localConfig.more_link_type"
            :platform-type="localConfig.more_platform_type"
            placeholder="请选择更多链接"
            dialog-title="选择更多链接"
            class="field-selector"
            @update:modelValue="val => { localConfig.more_link_url = val }"
            @select="handleLinkSelect"
            @clear="clearSelectedLink"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'TitleEditor'
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
  title: '区块标题',
  subtitle: '',
  style: 'normal',
  align: 'left',
  text_color: '#333333',
  bg_color: '#ffffff',
  show_more: false,
  more_link_type: 'page',
  more_link_url: '',
  more_link_id: 0,
  more_link_name: '',
  more_platform_type: ''
})

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined ? val.title : '区块标题',
    subtitle: val.subtitle !== undefined ? val.subtitle : '',
    style: val.style || 'normal',
    align: val.align || 'left',
    text_color: val.text_color || '#333333',
    bg_color: val.bg_color || '#ffffff',
    show_more: val.show_more !== undefined ? val.show_more : false,
    more_link_type: val.more_link_type || 'page',
    more_link_url: val.more_link_url || '',
    more_link_id: val.more_link_id || 0,
    more_link_name: val.more_link_name || '',
    more_platform_type: val.more_platform_type || ''
  })
  nextTick(() => {
    fromProp.value = false
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

function handleLinkSelect(row) {
  localConfig.more_link_type = row.link_type || localConfig.more_link_type || 'page'
  localConfig.more_link_url = row.link_url || ''
  localConfig.more_link_id = row.id || 0
  localConfig.more_link_name = row.link_name || ''
  localConfig.more_platform_type = row.platform_type || ''
}

function clearSelectedLink() {
  localConfig.more_link_id = 0
  localConfig.more_link_name = ''
  localConfig.more_platform_type = ''
}

function emitConfig() {
  emit('change', { ...localConfig })
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.title-editor {
  padding: 4px 0;

  .more-link-card {
    margin-top: 4px;
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    overflow: hidden;

    .card-header {
      padding: 8px 12px;
      font-size: 12px;
      color: #909399;
      background: #f5f7fa;
      border-bottom: 1px solid #ebeef5;
    }

    .card-body {
      padding: 10px 12px;
    }

    .card-field-row {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;

      &:last-child {
        margin-bottom: 0;
      }

      .field-label {
        font-size: 12px;
        color: #909399;
        width: 28px;
        flex-shrink: 0;
      }

      .field-select {
        flex: 1;
        min-width: 0;
      }
    }

    .card-field-row--link {
      align-items: flex-start;

      .field-selector {
        flex: 1;
        min-width: 0;
      }
    }
  }
}
</style>
