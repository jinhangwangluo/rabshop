<template>
  <div class="user-service-menu-editor">
    <div class="section-tip">鼠标拖拽可调整菜单顺序</div>

    <el-form label-width="90px">
      <el-form-item label="模块标题">
        <el-input v-model="localConfig.title" placeholder="必备服务" />
      </el-form-item>
      <el-form-item label="一行展示">
        <el-radio-group v-model="localConfig.columns">
          <el-radio :label="3">3个</el-radio>
          <el-radio :label="4">4个</el-radio>
          <el-radio :label="5">5个</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>

    <draggable v-model="localConfig.items" item-key="id" handle=".menu-drag-handle" class="menu-list">
      <template #item="{ element: item, index }">
        <div :key="item.id || index" class="menu-card">
          <span class="menu-drag-handle"><LegacyIcon name="el-icon-rank" /></span>

          <div class="card-thumb">
            <template v-if="item.image">
              <img :src="item.image" alt="menu">
              <div class="thumb-delete" @click.stop="onImageChange(index, '')">
                <LegacyIcon name="el-icon-delete" />
              </div>
            </template>
            <template v-else>
              <div class="thumb-placeholder">
                <LegacyIcon name="el-icon-picture" />
              </div>
              <Upload
                :ref="'upload_' + index"
                :model-value="item.image"
                :limit="1"
                class="thumb-upload-overlay"
                @update:modelValue="val => onImageChange(index, val)"
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
                dialog-title="选择服务菜单链接"
                @update:modelValue="val => { item.link_url = val }"
                @select="row => handleSelectLink(index, row)"
                @clear="() => clearSelectedLink(index)"
              />
            </div>
          </div>

          <LegacyIcon name="el-icon-circle-close" class=" card-remove" @click="removeMenu(index)" />
        </div>
      </template>
    </draggable>

    <div class="add-btn-wrap">
      <el-button class="add-btn" @click="addMenu"><LegacyIcon name="el-icon-plus" /> 添加菜单</el-button>
    </div>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import draggable from 'vuedraggable'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'UserServiceMenuEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const syncingFromProp = ref(false)
const localConfig = reactive({
  title: '必备服务',
  columns: 4,
  items: []
})

function normalizeMenuItem(item = {}, index = 0) {
  return {
    id: item.id || index,
    name: item.name || item.text || '',
    image: item.image || item.icon || '',
    link_type: item.link_type || 'page',
    link_url: item.link_url || item.url || '',
    link_id: item.link_id || 0,
    link_name: item.link_name || '',
    platform_type: item.platform_type || ''
  }
}

function normalizeConfig(val = {}) {
  const next = val && typeof val === 'object' ? val : {}
  return {
    title: next.title || '必备服务',
    columns: [3, 4, 5].includes(Number(next.columns)) ? Number(next.columns) : 4,
    items: (Array.isArray(next.items) ? next.items : []).map((item, idx) => normalizeMenuItem(item, idx))
  }
}

function applyConfig(val) {
  syncingFromProp.value = true
  Object.assign(localConfig, normalizeConfig(val))
  nextTick(() => {
    syncingFromProp.value = false
  })
}

function addMenu() {
  localConfig.items.push(normalizeMenuItem({ id: Date.now(), link_type: 'page' }))
}

function removeMenu(index) {
  localConfig.items.splice(index, 1)
}

function onImageChange(index, val) {
  localConfig.items[index] = { ...localConfig.items[index], image: val }
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
  localConfig.items[index] = { ...localConfig.items[index], link_id: 0, link_name: '', platform_type: '' }
}

function emitConfig() {
  emit('change', {
    title: localConfig.title,
    columns: localConfig.columns,
    items: localConfig.items.map(item => ({
      name: item.name,
      image: item.image,
      link_type: item.link_type,
      link_url: item.link_url,
      link_id: item.link_id || 0,
      link_name: item.link_name || '',
      platform_type: item.platform_type || ''
    }))
  })
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, () => {
  if (!syncingFromProp.value) {
    emitConfig()
  }
}, {
  deep: true
})
</script>

<style lang="scss" scoped>
.user-service-menu-editor {
  padding: 4px 0;

  .section-tip { margin-bottom: 12px; color: #e6a23c; font-size: 12px; line-height: 1.5; }
  .menu-list { margin-top: 12px; }
  .menu-card { position: relative; display: flex; align-items: center; padding: 10px 28px 10px 10px; margin-bottom: 14px; background: var(--dashboard-surface-muted); border: 1px solid var(--dashboard-border); border-radius: 6px; box-shadow: none; overflow: hidden; }
  .menu-drag-handle { margin-right: 10px; color: var(--dashboard-text-muted); cursor: move; font-size: 16px; flex-shrink: 0; }
  .card-thumb { position: relative; width: 52px; height: 52px; margin-right: 10px; flex-shrink: 0; border-radius: 4px; overflow: hidden; background: var(--dashboard-surface); border: 1px dashed var(--dashboard-border); cursor: pointer; }
  .card-thumb img { display: block; width: 100%; height: 100%; object-fit: cover; }
  .thumb-placeholder { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: var(--dashboard-text-muted); font-size: 20px; }
  .thumb-upload-overlay { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; }
  .thumb-delete { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; color: #fff; background: rgba(0, 0, 0, 0.45); }
  .card-thumb:hover .thumb-delete { display: flex; }
  .card-fields { flex: 1; min-width: 0; }
  .card-field-row { display: flex; align-items: center; margin-bottom: 8px; }
  .card-field-row:last-child { margin-bottom: 0; }
  .field-label { width: 70px; color: var(--dashboard-text-secondary); font-size: 12px; flex-shrink: 0; }
  .field-input, .field-selector { flex: 1; min-width: 0; }
  .card-remove { position: absolute; top: 8px; right: 8px; color: var(--dashboard-text-muted); cursor: pointer; }
  .add-btn-wrap { margin-top: 8px; }
  .add-btn { width: 100%; border-style: dashed; }
}
</style>
