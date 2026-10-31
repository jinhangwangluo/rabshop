<template>
  <div class="diy-link-select-field">
    <div class="field-row">
      <el-input
        :model-value="innerValue"
        :placeholder="placeholder"
        clearable
        class="field-input"
        @input="handleManualInput"
        @clear="handleClear"
      >
        <template #suffix>
          <LegacyIcon
            name="el-icon-link"
            class="el-icon-link field-link-icon"
            title="选择链接"
            @click.stop="openDialog"
          />
        </template>
      </el-input>
    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="900px"
      custom-class="diy-link-selector-dialog"
      :close-on-click-modal="false"
      append-to-body
    >
      <div class="selector-layout">
        <div v-loading="categoryLoading" class="selector-left">
          <div class="selector-left__title">链接分类</div>
          <div class="selector-left__scroll">
            <el-tree
              ref="categoryTreeRef"
              class="link-category-tree"
              :data="categoryTree"
              node-key="id"
              highlight-current
              default-expand-all
              :expand-on-click-node="false"
              :props="treeProps"
              empty-text="暂无分类"
              @node-click="handleCategoryNodeClick"
            >
              <template #default="{ data }">
                <span class="tree-node">
                  <span class="tree-node__main">
                    <img
                      v-if="!data.pid"
                      class="tree-node__icon"
                      src="@/assets/images/file.jpg"
                      alt=""
                    >
                    <span class="tree-node__label">{{ data.name || data.label }}</span>
                  </span>
                  <el-tag v-if="data.status === 0" class="tree-node__status" size="mini" type="info">停用</el-tag>
                </span>
              </template>
            </el-tree>
          </div>
        </div>

        <div class="selector-right">
          <div class="selector-toolbar">
            <el-input
              v-model.trim="query.keyword"
              clearable
              placeholder="搜索链接名称/地址"
              class="toolbar-item toolbar-keyword"
              @keyup.enter="handleSearch"
            />
            <el-select v-model="query.link_type" clearable placeholder="链接类型" class="toolbar-item">
              <el-option v-for="item in linkTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
            <el-select v-model="query.platform_type" clearable placeholder="平台类型" class="toolbar-item">
              <el-option v-for="item in platformOptions" :key="item.value" :label="item.label" :value="item.value" />
            </el-select>
            <el-button type="primary" @click="handleSearch">查询</el-button>
          </div>

          <el-table
            v-loading="loading"
            :data="linkList"
            border
            :header-cell-style="tableHeaderCellStyle"
          >
            <el-table-column label="链接名称" min-width="80">
              <template #default="scope">
                <div class="link-name-cell">
                  <img v-if="scope.row.icon" :src="scope.row.icon" class="link-icon" alt="icon">
                  <div class="link-name-cell__content">
                    <div class="link-name">{{ scope.row.link_name }}</div>
                    <div v-if="scope.row.remark" class="link-remark">{{ scope.row.remark }}</div>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="link_url" label="链接地址" min-width="240" show-overflow-tooltip />
            <el-table-column label="类型" width="80" align="center">
              <template #default="scope">
                <el-tag size="mini" type="info">{{ getLinkTypeLabel(scope.row.link_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="平台" width="80" align="center">
              <template #default="scope">
                <el-tag size="mini">{{ getPlatformLabel(scope.row.platform_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="90" align="center">
              <template #default="scope">
                <el-button type="text" @click="handleSelect(scope.row)">选择</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div v-if="total > 0" class="pagination-wrap">
            <el-pagination
              :current-page="query.page"
              :page-size="query.limit"
              :page-sizes="[10, 20, 50]"
              :total="total"
              layout="total, sizes, prev, pager, next"
              @current-change="handlePageChange"
              @size-change="handleSizeChange"
            />
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, reactive, ref, toRefs, watch } from 'vue'
import { getDiyCategoryTree, getDiyLinkOptions } from '@/api/diy'

const LINK_TYPE_OPTIONS = [
  { label: '页面', value: 'page' },
  { label: '外链', value: 'url' },
  { label: '小程序', value: 'mini' }
]

const PLATFORM_OPTIONS = [
  { label: '通用', value: 'common' },
  { label: 'H5', value: 'h5' },
  { label: '小程序', value: 'mp' },
  { label: 'PC', value: 'pc' }
]

defineOptions({
  name: 'DiyLinkSelectField'
})

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  linkId: {
    type: [Number, String],
    default: 0
  },
  linkName: {
    type: String,
    default: ''
  },
  linkType: {
    type: String,
    default: ''
  },
  platformType: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: '请输入链接地址'
  },
  dialogTitle: {
    type: String,
    default: '选择链接'
  }
})

const emit = defineEmits(['update:modelValue', 'select', 'clear'])
const { modelValue, linkId, linkName, linkType, platformType, placeholder, dialogTitle } = toRefs(props)
const { proxy } = getCurrentInstance()

const dialogVisible = ref(false)
const loading = ref(false)
const categoryLoading = ref(false)
const categoryTree = ref([])
const linkList = ref([])
const total = ref(0)
const currentCategoryId = ref('')
const innerValue = ref(modelValue.value || '')
const categoryTreeRef = ref(null)
const treeProps = {
  children: 'children',
  label: 'name'
}
const query = reactive({
  page: 1,
  limit: 10,
  keyword: '',
  link_type: '',
  platform_type: ''
})
const linkTypeOptions = LINK_TYPE_OPTIONS
const platformOptions = PLATFORM_OPTIONS

const tableHeaderCellStyle = computed(() => ({
  background: 'var(--dashboard-surface-muted)',
  color: 'var(--dashboard-text-primary)',
  fontWeight: '500'
}))

watch(modelValue, val => {
  innerValue.value = val || ''
})

function openDialog() {
  dialogVisible.value = true
  ensureDialogData()
}

async function ensureDialogData() {
  await loadCategoryTree()
  await loadLinkList()
}

async function loadCategoryTree() {
  categoryLoading.value = true
  try {
    const res = await getDiyCategoryTree({ status: 1 })
    categoryTree.value = Array.isArray(res.data) ? res.data : []
    if (!currentCategoryId.value) {
      const firstNode = findFirstNode(categoryTree.value)
      if (firstNode) {
        currentCategoryId.value = firstNode.id
        nextTick(() => {
          categoryTreeRef.value && categoryTreeRef.value.setCurrentKey(firstNode.id)
        })
      }
    }
  } catch (e) {
    proxy.$message.error(e.message || '获取分类失败')
  } finally {
    categoryLoading.value = false
  }
}

function findFirstNode(list) {
  if (!Array.isArray(list) || list.length === 0) return null
  const first = list[0]
  if (Array.isArray(first.children) && first.children.length > 0) {
    return first.children[0]
  }
  return first
}

function handleCategoryNodeClick(data) {
  currentCategoryId.value = data.id
  query.page = 1
  loadLinkList()
}

async function loadLinkList() {
  loading.value = true
  try {
    const res = await getDiyLinkOptions({
      ...query,
      cate_id: currentCategoryId.value || ''
    })
    const data = res.data || {}
    linkList.value = data.list || []
    total.value = data.count || 0
  } catch (e) {
    proxy.$message.error(e.message || '获取链接失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  query.page = 1
  loadLinkList()
}

function handlePageChange(page) {
  query.page = page
  loadLinkList()
}

function handleSizeChange(limit) {
  query.limit = limit
  query.page = 1
  loadLinkList()
}

function handleManualInput(val) {
  innerValue.value = val
  emit('update:modelValue', val)
  if ((linkId.value || linkName.value) && val !== modelValue.value) {
    emit('clear')
  }
}

function handleSelect(row) {
  innerValue.value = row.link_url || ''
  emit('update:modelValue', innerValue.value)
  emit('select', row)
  dialogVisible.value = false
}

function handleClear() {
  innerValue.value = ''
  emit('update:modelValue', '')
  emit('clear')
}

function getLinkTypeLabel(value) {
  const target = linkTypeOptions.find(item => item.value === value)
  return target ? target.label : value || '未设置'
}

function getPlatformLabel(value) {
  const target = platformOptions.find(item => item.value === value)
  return target ? target.label : value || '未设置'
}
</script>

<style lang="scss" scoped>
.diy-link-select-field {
  .field-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .field-input {
    flex: 1;
  }

  .field-link-icon {
    color: var(--dashboard-text-muted);
    cursor: pointer;
    transition: color 0.2s;

    &:hover {
      color: var(--prev-color-primary);
    }
  }

  :deep(.field-input .el-input__suffix) {
    display: flex;
    align-items: center;
    right: 8px;
  }

  .selected-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
    font-size: 12px;
    color: var(--dashboard-text-secondary);
  }

  .selected-name {
    font-weight: 500;
    color: var(--dashboard-text-primary);
  }

  .clear-btn {
    padding: 0;
  }
}

.selector-layout {
  display: flex;
  gap: 16px;
  min-height: 460px;
}

.selector-left {
  display: flex;
  flex-direction: column;
  width: 220px;
  min-width: 220px;
  padding-right: 12px;
  border-right: 1px solid var(--dashboard-border);

  &__title {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
  }

  &__scroll {
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    padding-bottom: 10px;
  }

  :deep(.link-category-tree) {
    color: var(--dashboard-text-secondary);
    background: var(--dashboard-surface);
  }

  :deep(.link-category-tree .el-tree-node__content) {
    width: 100%;
    height: 36px;
    padding: 0 10px;
    box-sizing: border-box;
    color: var(--dashboard-text-secondary);
    background: var(--dashboard-surface);
    border-radius: 4px;
  }

  :deep(.link-category-tree .el-tree-node__content:hover),
  :deep(.link-category-tree .el-tree-node:focus > .el-tree-node__content) {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface-muted);
  }

  :deep(.link-category-tree .el-tree-node.is-current > .el-tree-node__content) {
    color: var(--prev-color-primary);
    background: var(--dashboard-accent-outline);
  }

  :deep(.link-category-tree .el-tree-node__expand-icon) {
    color: var(--dashboard-text-muted);
  }

  .tree-node {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-width: 0;
    padding-right: 8px;
    font-size: 13px;
    font-weight: 400;
    line-height: 13px;
  }

  .tree-node__main {
    display: flex;
    align-items: center;
    min-width: 0;
  }

  .tree-node__icon {
    width: 12px;
    height: 12px;
    margin-right: 8px;
    flex-shrink: 0;
  }

  .tree-node__label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .tree-node__status {
    flex-shrink: 0;
    margin-left: 6px;
  }
}

.selector-right {
  flex: 1;
  min-width: 0;
}

.selector-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;

  .toolbar-item {
    width: 140px;
  }

  .toolbar-keyword {
    width: 220px;
  }
}

.link-name-cell {
  display: flex;
  align-items: center;
  gap: 10px;

  &__content {
    min-width: 0;
  }
}

.link-icon {
  width: 28px;
  height: 28px;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
}

.link-name {
  font-weight: 500;
  color: var(--dashboard-text-primary);
}

.link-remark {
  margin-top: 2px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>

<style lang="scss">
.diy-link-selector-dialog {
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);

  .el-dialog__header {
    border-bottom: 1px solid var(--dashboard-border);
  }

  .el-dialog__title {
    color: var(--dashboard-text-primary);
  }

  .el-dialog__headerbtn .el-dialog__close {
    color: var(--dashboard-text-muted);
  }

  .el-dialog__body {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
  }
}
</style>
