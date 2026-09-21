<template>
  <div class="tag-manage-page">
    <div class="tag-manage-page__body">
      <el-card class="tag-group-card" shadow="never">
        <template #header>
          <div class="tag-card-header">
            <span class="tag-card-header__title">标签分组</span>
            <el-button class="tag-group-add" type="text" icon="el-icon-plus" @click="openGroupDialog()">新增</el-button>
          </div>
        </template>
        <div class="tag-group-panel">
          <div :class="['tag-group-all', { 'is-active': !searchForm.category_id }]" @click="handleAllCategoryClick">
            <span class="tag-group-node__main">
              <span class="tag-group-node__icon">
                <LegacyIcon name="el-icon-folder" />
              </span>
              <span class="tag-group-node__label">全部标签</span>
            </span>
            <el-dropdown
              class="tag-group-node__more"
              trigger="click"
              @command="handleGroupRootMenuCommand"
            >
              <span class="tag-group-node__more-trigger" @click.stop>
                <LegacyIcon name="el-icon-more" />
              </span>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="create">新增分组</el-dropdown-item>
                  <el-dropdown-item command="refresh">刷新列表</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
          <el-tree
            v-if="categoryTree.length"
            ref="categoryTreeRef"
            class="tag-group-tree"
            :data="categoryTree"
            node-key="id"
            default-expand-all
            highlight-current
            :props="{ label: 'name', children: 'children' }"
            @node-click="handleCategoryClick"
          >
            <template #default="{ node, data }">
              <span class="tag-group-node">
                <span class="tag-group-node__main">
                  <span class="tag-group-node__icon">
                    <LegacyIcon name="el-icon-folder" />
                  </span>
                  <span class="tag-group-node__label" :title="node.label">{{ node.label }}</span>
                </span>
                <el-dropdown
                  class="tag-group-node__more"
                  trigger="click"
                  @command="(command) => handleGroupMenuCommand(data, command)"
                >
                  <span class="tag-group-node__more-trigger" @click.stop>
                    <LegacyIcon name="el-icon-more" />
                  </span>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="edit">编辑分组</el-dropdown-item>
                      <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </span>
            </template>
          </el-tree>
          <el-empty v-else class="tag-group-empty" :image-size="64" description="暂无分组" />
        </div>
      </el-card>

      <el-card class="tag-list-card" shadow="never">
        <el-form :model="searchForm" inline class="tag-search-form" @submit.prevent>
          <el-form-item label="标签名称">
            <el-input v-model="searchForm.name" clearable placeholder="请输入标签名称" @keyup.enter="handleSearch" />
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="searchForm.status" clearable placeholder="全部">
              <el-option label="启用" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
            <el-button icon="el-icon-refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>

        <div class="action-row">
          <el-button type="primary" icon="el-icon-plus" @click="openTagDialog()">添加标签</el-button>
        </div>
        <el-table v-loading="loading" :data="tagList" row-key="id">
          <el-table-column prop="id" label="ID" width="80" align="center" />
          <el-table-column prop="name" label="标签名称" min-width="150" />
          <el-table-column prop="category_name" label="所属分组" min-width="140" />
          <el-table-column label="效果" min-width="150">
            <template #default="{ row }">
              <TagPreview v-if="row.name" :tag="row" />
              <span v-else class="empty-text">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="90" align="center" />
          <el-table-column label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-switch
                v-model="row.status"
                :active-value="1"
                :inactive-value="0"
                @change="value => handleTagStatus(row, value)"
              />
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
          <el-table-column label="操作" width="150" align="center" fixed="right">
            <template #default="{ row }">
              <el-button type="text" @click="openTagDialog(row)">编辑</el-button>
              <el-button type="text" class="tag-danger" @click="handleDeleteTag(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="tag-pagination">
          <el-pagination
            v-if="total > 0"
            v-model:current-page="searchForm.page"
            v-model:page-size="searchForm.limit"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handlePageChange"
          />
        </div>
      </el-card>
    </div>

    <el-dialog v-model="groupDialogVisible" :title="groupForm.id ? '编辑分组' : '新增分组'" width="520px">
      <el-form ref="groupFormRef" :model="groupForm" :rules="groupRules" label-width="96px">
        <el-form-item label="上级分组">
          <el-cascader
            v-model="groupForm.path"
            :options="categoryTree"
            :props="{ value: 'id', label: 'name', checkStrictly: true }"
            clearable
            placeholder="不选则为一级分组"
          />
        </el-form-item>
        <el-form-item label="分组名称" prop="name">
          <el-input v-model="groupForm.name" maxlength="30" show-word-limit />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="groupForm.sort" :min="0" :step="1" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="groupForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="groupDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="groupSaving" @click="handleSaveGroup">保存</el-button>
          <el-button v-if="groupForm.id" type="danger" plain @click="handleDeleteGroup">删除</el-button>
        </span>
      </template>
    </el-dialog>

    <el-dialog v-model="tagDialogVisible" :title="tagForm.id ? '编辑标签' : '添加标签'" width="620px">
      <el-form ref="tagFormRef" :model="tagForm" :rules="tagRules" label-width="96px">
        <el-form-item label="标签分组" prop="category_id">
          <el-select v-model="tagForm.category_id" class="tag-form-control" placeholder="请选择分组">
            <el-option v-for="item in flatCategories" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="标签名称" prop="name">
          <el-input v-model="tagForm.name" maxlength="30" show-word-limit />
        </el-form-item>
        <el-form-item label="效果设置">
          <el-radio-group v-model="tagForm.effect_type">
            <el-radio :label="1">自定义颜色</el-radio>
            <el-radio :label="2">图片</el-radio>
          </el-radio-group>
        </el-form-item>
        <template v-if="tagForm.effect_type === 1">
          <el-form-item label="文字颜色">
            <el-color-picker v-model="tagForm.text_color" />
          </el-form-item>
          <el-form-item label="背景颜色">
            <el-color-picker v-model="tagForm.bg_color" />
          </el-form-item>
          <el-form-item label="边框颜色">
            <el-color-picker v-model="tagForm.border_color" />
          </el-form-item>
        </template>
        <el-form-item v-else label="标签图片" class="tag-image-upload">
          <Upload v-model="tagForm.icon" :limit="1" />
        </el-form-item>
        <el-form-item label="效果预览">
          <div class="tag-effect-preview">
            <TagPreview :tag="tagPreviewData" />
            <span class="tag-effect-preview__tips">保存后前台列表、详情和选择弹窗将按此效果展示</span>
          </div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="tagForm.sort" :min="0" :step="1" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="tagForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="tagDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="tagSaving" @click="handleSaveTag">保存</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import Upload from '@/components/Upload/index'
import TagPreview from '@/components/TagPreview/index'
import {
  getTagCategoryTree,
  createTagCategory,
  updateTagCategory,
  deleteTagCategory,
  getTagList,
  createTag,
  updateTag,
  deleteTag,
  setTagStatus
} from '@/api/tag'

defineOptions({
  name: 'ProductTagManage'
})

const TAG_COLOR_FIELDS = ['text_color', 'bg_color', 'border_color']
const tagType = 2

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const categoryTreeRef = ref(null)
const groupFormRef = ref(null)
const tagFormRef = ref(null)
const categoryTree = ref([])
const flatCategories = ref([])
const currentCategory = ref({})
const loading = ref(false)
const tagList = ref([])
const total = ref(0)
const groupDialogVisible = ref(false)
const groupSaving = ref(false)
const tagDialogVisible = ref(false)
const tagSaving = ref(false)

const searchForm = reactive({
  page: 1,
  limit: 20,
  name: '',
  status: '',
  category_id: ''
})

const groupForm = reactive(getDefaultGroupForm())
const tagForm = reactive(getDefaultTagForm())

const groupRules = {
  name: [{ required: true, message: '请输入分组名称', trigger: 'blur' }]
}

const tagRules = {
  category_id: [{ required: true, message: '请选择标签分组', trigger: 'change' }],
  name: [{ required: true, message: '请输入标签名称', trigger: 'blur' }]
}

const tagPreviewData = computed(() => Object.assign({}, tagForm, {
  name: tagForm.name || '标签预览'
}))

function getDefaultGroupForm() {
  return { id: 0, tag_type: tagType, path: [], name: '', sort: 0, status: 1 }
}

function getDefaultTagForm() {
  return {
    id: 0,
    tag_type: tagType,
    category_id: '',
    name: '',
    effect_type: 1,
    text_color: '',
    bg_color: '',
    border_color: '',
    icon: '',
    sort: 0,
    status: 1
  }
}

function loadCategories() {
  getTagCategoryTree({ tag_type: tagType }).then(res => {
    categoryTree.value = res.data || []
    flatCategories.value = flattenCategories(categoryTree.value)
  })
}

function flattenCategories(list) {
  const result = []
  const walk = (items, prefix) => {
    items.forEach(item => {
      const name = prefix ? prefix + ' / ' + item.name : item.name
      result.push({ id: item.id, name })
      if (item.children && item.children.length) {
        walk(item.children, name)
      }
    })
  }
  walk(list || [], '')
  return result
}

function getList() {
  loading.value = true
  getTagList(Object.assign({}, searchForm, { tag_type: tagType }))
    .then(res => {
      const data = res.data || {}
      tagList.value = data.list || []
      total.value = data.total || 0
    })
    .finally(() => {
      loading.value = false
    })
}

function handleCategoryClick(row) {
  currentCategory.value = row || {}
  searchForm.category_id = row && row.id ? row.id : ''
  searchForm.page = 1
  getList()
}

function handleAllCategoryClick() {
  currentCategory.value = {}
  searchForm.category_id = ''
  searchForm.page = 1
  if (categoryTreeRef.value) {
    categoryTreeRef.value.setCurrentKey(null)
  }
  getList()
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, {
    name: '',
    status: '',
    category_id: '',
    page: 1,
    limit: 20
  })
  currentCategory.value = {}
  if (categoryTreeRef.value) {
    categoryTreeRef.value.setCurrentKey(null)
  }
  handleSearch()
}

function handleSizeChange(size) {
  searchForm.limit = size
  getList()
}

function handlePageChange(page) {
  searchForm.page = page
  getList()
}

function openGroupDialog(row) {
  Object.assign(groupForm, row && row.id
    ? { id: row.id, tag_type: tagType, path: row.path || [], name: row.name, sort: row.sort || 0, status: Number(row.status) }
    : getDefaultGroupForm())
  groupDialogVisible.value = true
}

function handleGroupRootMenuCommand(command) {
  if (command === 'create') {
    openGroupDialog()
    return
  }
  if (command === 'refresh') {
    loadCategories()
  }
}

function handleGroupMenuCommand(row, command) {
  if (command === 'edit') {
    openGroupDialog(row)
    return
  }
  if (command === 'delete') {
    handleDeleteGroupRow(row)
  }
}

function handleSaveGroup() {
  if (!groupFormRef.value) return
  groupFormRef.value.validate(valid => {
    if (!valid) return
    groupSaving.value = true
    const request = groupForm.id
      ? updateTagCategory(groupForm.id, groupForm)
      : createTagCategory(groupForm)
    request.then(res => {
      message.success(res.message || '保存成功')
      groupDialogVisible.value = false
      loadCategories()
    }).finally(() => {
      groupSaving.value = false
    })
  })
}

function handleDeleteGroup() {
  handleDeleteGroupRow(groupForm, true)
}

function handleDeleteGroupRow(row, shouldCloseDialog = false) {
  if (!row || !row.id) {
    return
  }
  confirm('确定删除该标签分组吗？', '提示', { type: 'warning' }).then(() => {
    deleteTagCategory(row.id).then(res => {
      message.success(res.message || '删除成功')
      if (shouldCloseDialog) {
        groupDialogVisible.value = false
      }
      currentCategory.value = {}
      searchForm.category_id = ''
      loadCategories()
      getList()
    })
  })
}

function openTagDialog(row) {
  const base = row && row.id
    ? normalizeTagColorFields(Object.assign(getDefaultTagForm(), row))
    : Object.assign(getDefaultTagForm(), { category_id: searchForm.category_id || '' })
  Object.assign(tagForm, base)
  tagDialogVisible.value = true
}

function normalizeTagColorFields(form) {
  const data = Object.assign({}, form)
  TAG_COLOR_FIELDS.forEach(field => {
    if (data[field] === null || typeof data[field] === 'undefined') {
      data[field] = ''
    }
  })
  return data
}

function handleSaveTag() {
  if (!tagFormRef.value) return
  tagFormRef.value.validate(valid => {
    if (!valid) return
    tagSaving.value = true
    const data = normalizeTagColorFields(tagForm)
    const request = data.id
      ? updateTag(data.id, data)
      : createTag(data)
    request.then(res => {
      message.success(res.message || '保存成功')
      tagDialogVisible.value = false
      getList()
    }).finally(() => {
      tagSaving.value = false
    })
  })
}

function handleDeleteTag(row) {
  confirm('确定删除该标签吗？', '提示', { type: 'warning' }).then(() => {
    deleteTag(row.id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    })
  })
}

function handleTagStatus(row, value) {
  setTagStatus(row.id, value).then(res => {
    message.success(res.message || '修改成功')
  }).catch(() => {
    row.status = value === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  loadCategories()
  getList()
})
</script>

<style lang="scss" scoped>
.tag-manage-page {
  min-height: calc(100vh - 124px);
  padding: 0;
  border-radius: 4px;
  background: var(--prev-bg-white);

  &__body {
    display: flex;
    align-items: stretch;
    min-height: calc(100vh - 124px);
  }
}

.tag-group-card {
  flex-shrink: 0;
  width: 260px;
  min-width: 260px;
  border: 0;
  border-right: 1px solid var(--prev-border-color-light);
  border-radius: 0;
  background: transparent;

  :deep(.el-card__header) {
    padding: 20px 20px 18px 24px;
    border-bottom: 0;
  }

  :deep(.el-card__body) {
    padding: 0 12px 20px 16px;
  }
}

.action-row {
    margin: 0 0 16px;
  }

.tag-list-card {
  flex: 1;
  min-width: 0;
  margin-left: 20px;
  margin-top: 20px;
  border: 0;
  border-radius: 0;
  background: transparent;

  :deep(.el-card__header) {
    padding: 0 13px 20px 0;
    border-bottom: 0;
  }

  :deep(.el-card__body) {
    padding: 0 13px 10px 0;
  }
}

.tag-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 32px;

  &__title {
    color: var(--ds-on-surface);
    font-size: 16px;
    font-weight: 600;
  }
}

.tag-group-add {
  padding: 0;
  color: var(--ds-primary);
  font-size: 14px;
  font-weight: 500;

  &:hover,
  &:focus {
    color: var(--ds-primary-hover);
  }
}

.tag-group-panel {
  min-height: 560px;
  overflow-x: hidden;
  overflow-y: auto;

  &::-webkit-scrollbar {
    display: none;
  }
}

.tag-group-all {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  height: 40px;
  margin-bottom: 4px;
  padding: 0 10px;
  border-radius: 2px;
  background: transparent;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;

  &:hover {
    background: var(--ds-surface-low);
  }

  &.is-active {
    background: var(--ds-surface-low);

    .tag-group-node__icon {
      color: var(--ds-primary);
    }

    .tag-group-node__label {
      color: var(--ds-primary);
    }
  }
}

.tag-group-tree {
  background: transparent;

  :deep(.el-tree-node) {
    margin-right: 0;
  }

  :deep(.el-tree-node__content) {
    height: 40px;
    padding-right: 10px;
    border-radius: 2px;
    background: transparent;
    transition: background 0.2s ease, color 0.2s ease;

    &:hover {
      background: var(--ds-surface-low);
    }
  }

  :deep(.el-tree--highlight-current .el-tree-node.is-current > .el-tree-node__content),
  :deep(.el-tree-node.is-current > .el-tree-node__content) {
    background: var(--ds-surface-low);

    .tag-group-node__icon {
      color: var(--ds-primary);
    }

    .tag-group-node__label {
      color: var(--ds-primary);
    }
  }

  :deep(.el-tree-node__expand-icon) {
    padding: 0 4px 0 0;
    color: var(--ds-on-surface-variant);
    font-size: 12px;
  }

  :deep(.el-tree-node__expand-icon.is-leaf) {
    color: transparent;
  }
}

.tag-group-node {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-width: 0;

  &__main {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
  }

  &__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    color: var(--ds-primary);

    i {
      font-size: 16px;
      line-height: 1;
    }
  }

  &__label {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    color: var(--ds-on-surface-variant);
    font-size: 14px;
    font-weight: 400;
    line-height: 20px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__more {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
  }

  &__more-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 2px;
    color: var(--ds-on-surface-variant);
    cursor: pointer;
    transition: color 0.2s ease;

    i {
      font-size: 18px;
      line-height: 1;
    }

    &:hover {
      color: var(--ds-primary);
    }
  }
}

.tag-group-empty {
  padding-top: 90px;
}

.tag-search-form {
  margin-bottom: 16px;

  :deep(.el-form-item) {
    margin-bottom: 12px;
  }
}

.tag-form-control {
  width: 100%;
}

.tag-effect-preview {
  display: flex;
  align-items: center;
  flex-wrap: wrap;

  &__tips {
    margin-left: 12px;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
  }
}

.tag-image-upload {
  :deep(.avatar-uploader .el-upload) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-color: var(--prev-border-color-base);
    border-radius: 6px;
    background: var(--prev-bg-white);
    overflow: inherit;
  }

  :deep(.avatar-uploader .el-upload:hover) {
    border-color: var(--prev-color-primary);
  }

  :deep(.avatar-uploader .avatar-uploader-icon) {
    width: 64px;
    height: 64px;
    line-height: 64px;
    color: var(--prev-color-text-secondary);
  }

  :deep(.avatar-uploader .avatar) {
    width: 64px;
    height: 64px;
    border-radius: 6px;
    object-fit: cover;
  }
}

.empty-text {
  color: var(--prev-color-text-secondary);
  font-size: 12px;
}

.tag-pagination {
  margin-top: 16px;
  text-align: right;
}

.tag-danger {
  color: var(--dashboard-danger);
}

@media (max-width: 1200px) {
  .tag-group-card {
    width: 240px;
    min-width: 240px;
  }
}

@media (max-width: 960px) {
  .tag-manage-page__body {
    display: block;
    min-height: auto;
  }

  .tag-group-card {
    width: 100%;
    min-width: 0;
    margin-bottom: 16px;
    border-right: 0;
    border-bottom: 1px solid var(--prev-border-color-light);

    :deep(.el-card__header) {
      padding-right: 0;
    }

    :deep(.el-card__body) {
      padding-right: 0;
    }
  }

  .tag-list-card {
    margin-left: 0;

    :deep(.el-card__header),
    :deep(.el-card__body) {
      padding-right: 0;
    }
  }

  .tag-group-panel {
    min-height: auto;
  }
}
</style>
