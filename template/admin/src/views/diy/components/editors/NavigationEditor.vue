<template>
  <div class="navigation-editor">
    <div class="section-tip">建议：图标尺寸80*80px；鼠标拖拽可调整导航顺序</div>

    <el-form label-width="100px">
      <el-form-item label="组件名称">
        <el-input v-model="localConfig.title" placeholder="快捷导航" />
      </el-form-item>
      <el-form-item label="一行展示">
        <el-radio-group v-model="localConfig.columns">
          <el-radio :label="4">4个</el-radio>
          <el-radio :label="5">5个</el-radio>
          <el-radio :label="6">6个</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="显示标题">
        <el-switch v-model="localConfig.show_title" />
      </el-form-item>
    </el-form>

    <draggable
      v-model="localConfig.items"
      item-key="id"
      handle=".nav-drag-handle"
      class="nav-list"
    >
      <template #item="{ element: item, index }">
        <div :key="item.id || index" class="nav-card">
          <span class="nav-drag-handle"><LegacyIcon name="el-icon-rank" /></span>

          <div class="card-thumb card-thumb--center">
            <!-- 有图标：显示预览 + 悬浮删除 -->
            <template v-if="item.icon">
              <img :src="item.icon" alt="icon">
              <div class="thumb-delete" @click.stop="onIconChange(index, '')">
                <LegacyIcon name="el-icon-delete" />
              </div>
            </template>
            <!-- 无图标：显示上传组件 -->
            <template v-else>
              <div class="thumb-placeholder">
                <LegacyIcon name="el-icon-picture" />
              </div>
              <Upload
                :ref="'upload_' + index"
                :model-value="item.icon"
                :limit="1"
                class="thumb-upload-overlay"
                @update:modelValue="val => onIconChange(index, val)"
              />
            </template>
          </div>

          <div class="card-fields">
            <div class="card-field-row">
              <span class="field-label">名称</span>
              <el-input v-model="item.name" placeholder="不超过4个字" class="field-input" />
            </div>
            <div class="card-field-row card-field-row--link">
              <span class="field-label">链接</span>
              <DiyLinkSelectField
                class="field-selector"
                :model-value="item.link_url"
                :link-id="item.link_id"
                :link-name="item.link_name"
                :link-type="item.link_type"
                :platform-type="item.platform_type"
                placeholder="请选择或输入链接地址"
                dialog-title="选择导航链接"
                @update:modelValue="val => { item.link_url = val }"
                @select="row => handleSelectLink(index, row)"
                @clear="() => clearSelectedLink(index)"
              />
            </div>
          </div>

          <LegacyIcon name="el-icon-circle-close" class=" card-remove" @click="removeNav(index)" />
        </div>
      </template>
    </draggable>

    <div class="add-btn-wrap">
      <el-button class="add-btn" @click="addNav">
        <LegacyIcon name="el-icon-plus" /> 添加导航
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import draggable from 'vuedraggable'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'NavigationEditor'
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
  title: '快捷导航',
  columns: 4,
  show_title: true,
  items: []
})

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

function resolveShowTitle(config = {}) {
  if (config.show_title !== undefined) {
    return normalizeSwitchValue(config.show_title, true)
  }
  if (config.show_text !== undefined) {
    return normalizeSwitchValue(config.show_text, true)
  }
  return true
}

function normalizeNavItem(item = {}, idx = 0) {
  return {
    id: item.id || idx,
    name: item.name || item.title || item.text || '',
    icon: item.icon || item.pic || item.image || '',
    link_type: item.link_type || 'page',
    link_url: item.link_url || item.link || '',
    link_id: item.link_id || 0,
    link_name: item.link_name || '',
    platform_type: item.platform_type || ''
  }
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  fromProp.value = true
  if (val.menuList !== undefined && val.menuStyleConfig !== undefined) {
    const tabVal = Number(val.menuStyleConfig.tabVal) || 0
    const metaItems = val.__editor_meta && Array.isArray(val.__editor_meta.items) ? val.__editor_meta.items : []
    Object.assign(localConfig, {
      title: val.menuStyleConfig.title || '快捷导航',
      columns: tabVal === 0 ? 4 : tabVal === 1 ? 5 : 6,
      show_title: normalizeSwitchValue(val.menuStyleConfig.isShow, true),
      items: (val.menuList.list || []).map((item, idx) => normalizeNavItem({
        id: idx,
        name: item.name || item.title || '',
        icon: item.pic || item.icon || '',
        link_type: 'page',
        link_url: item.link || '',
        ...(metaItems[idx] || {})
      }, idx))
    })
  } else {
    Object.assign(localConfig, {
      title: val.title !== undefined ? val.title : '快捷导航',
      columns: normalizeColumns(val.columns, 4),
      show_title: resolveShowTitle(val),
      items: (Array.isArray(val.items) ? val.items : (Array.isArray(val.icons) ? val.icons : []))
        .map((item, idx) => normalizeNavItem(item, idx))
    })
  }
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

function addNav() {
  localConfig.items.push(normalizeNavItem({
    id: Date.now(),
    name: '',
    icon: '',
    link_type: 'page',
    link_url: ''
  }))
}

function removeNav(index) {
  localConfig.items.splice(index, 1)
}

function onIconChange(index, val) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    icon: val
  }
}

function handleSelectLink(index, row) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    link_type: row.link_type || localConfig.items[index].link_type,
    link_url: row.link_url || '',
    link_id: row.id || 0,
    link_name: row.link_name || '',
    platform_type: row.platform_type || ''
  }
}

function clearSelectedLink(index) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    link_id: 0,
    link_name: '',
    platform_type: ''
  }
}

function emitConfig() {
  const columns = normalizeColumns(localConfig.columns, 4)
  const showTitle = normalizeSwitchValue(localConfig.show_title, true)
  emit('change', {
    title: localConfig.title,
    columns,
    show_title: showTitle,
    show_text: showTitle,
    __editor_meta: {
      items: localConfig.items.map(item => ({
        link_id: item.link_id || 0,
        link_name: item.link_name || '',
        platform_type: item.platform_type || ''
      }))
    },
    items: localConfig.items.map(item => ({
      name: item.name,
      icon: item.icon,
      link_type: item.link_type,
      link_url: item.link_url,
      link_id: item.link_id || 0,
      link_name: item.link_name || '',
      platform_type: item.platform_type || ''
    }))
  })
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.navigation-editor {
  padding: 4px 0;

  .section-tip {
    font-size: 12px;
    color: #e6a23c;
    margin-bottom: 12px;
    line-height: 1.5;
  }

  .nav-list {
    margin-top: 12px;
  }

  .nav-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 28px 10px 10px;
    margin-bottom: 14px;
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    overflow: hidden;

    .nav-drag-handle {
      color: #c0c4cc;
      cursor: move;
      font-size: 16px;
      flex-shrink: 0;

      &:hover {
        color: #909399;
      }
    }

    .card-thumb {
      position: relative;
      width: 52px;
      height: 52px;
      flex-shrink: 0;
      border-radius: 4px;
      overflow: hidden;
      background: #f5f7fa;
      border: 1px dashed #dcdfe6;
      cursor: pointer;

      &:hover {
        border-color: var(--prev-color-primary);
      }

      img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }

      .thumb-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c0c4cc;
        font-size: 20px;
      }

      .thumb-upload-overlay {
        position: absolute;
        inset: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;

        :deep(.el-upload) {
          width: 100%;
          height: 100%;
          display: block;
        }

        :deep(.el-upload-dragger),
        :deep(.el-upload--text) {
          width: 100%;
          height: 100%;
          display: block;
        }
      }

      .thumb-delete {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.45);
        color: #fff;
        font-size: 16px;
        opacity: 0;
        transition: opacity 0.2s;
        cursor: pointer;
      }

      &:hover .thumb-delete {
        opacity: 1;
      }
    }

    .card-fields {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 5px;
      min-width: 0;
    }

    .card-field-row {
      display: flex;
      align-items: center;
      gap: 6px;

      .field-label {
        font-size: 12px;
        color: #909399;
        width: 28px;
        flex-shrink: 0;
      }

      .field-input,
      .field-select,
      .field-selector {
        flex: 1;
        min-width: 0;
      }

      .field-link-icon {
        cursor: pointer;
        line-height: 32px;
        color: #909399;
      }
    }

    .card-field-row--link {
      align-items: flex-start;
    }

    .card-remove {
      position: absolute;
      top: 4px;
      right: 6px;
      font-size: 16px;
      color: #c0c4cc;
      cursor: pointer;
      transition: color 0.2s;
      z-index: 2;
      line-height: 1;

      &:hover {
        color: #f56c6c;
      }
    }
  }

  .add-btn-wrap {
    margin-top: 4px;
  }

  .add-btn {
    width: 100%;
    border-style: dashed;
    color: var(--prev-color-primary);
    border-color: var(--dashboard-accent-light);
    background: var(--prev-color-primary-light-9);
    font-size: 13px;

    &:hover {
      background: var(--prev-color-primary-light-9);
      border-color: var(--prev-color-primary);
    }
  }
}
</style>
