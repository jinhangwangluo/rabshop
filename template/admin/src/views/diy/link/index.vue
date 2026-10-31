<template>
  <div class="diy-link-page">
    <div class="page-layout">
      <div class="layout-left">
        <el-card shadow="never" class="left-card">
          <template #header>
            <div class="pane-header">
              <div>
                <div class="pane-title">DIY 链接分类</div>
                <div class="pane-desc">支持一级、二级分类管理</div>
              </div>
              <div class="pane-actions">
                <div class="pane-action" @click="handleAddRootCategory">
                  <LegacyIcon name="el-icon-plus" />
                  <span>添加</span>
                </div>
              </div>
            </div>
          </template>

          <div v-loading="categoryLoading" class="tree-wrap">
            <el-tree
              ref="categoryTreeRef"
              :data="categoryTree"
              node-key="id"
              highlight-current
              default-expand-all
              :expand-on-click-node="false"
              :props="treeProps"
              empty-text="暂无分类"
              @node-click="handleCategorySelect"
            >
              <template #default="{ data }">
                <div
                  :class="['category-item', { active: Number(currentCategoryId) === Number(data.id) }]"
                  @click.stop="handleCategorySelect(data)"
                >
                  <div class="category-main">
                    <span class="category-icon">
                      <LegacyIcon name="el-icon-folder" />
                    </span>
                    <div class="category-content">
                      <span class="category-name">{{ data.name || data.label }}</span>
                    </div>
                  </div>
                  <el-dropdown
                    class="category-more"
                    trigger="click"
                    @command="(command) => handleCategoryMenuCommand(data, command)"
                  >
                    <span class="category-more-trigger" @click.stop>
                      <LegacyIcon name="el-icon-more" />
                    </span>
                    <template #dropdown>
                      <el-dropdown-menu>
                        <el-dropdown-item v-if="!data.pid" command="add_child">添加子分类</el-dropdown-item>
                        <el-dropdown-item command="edit">编辑分类</el-dropdown-item>
                        <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                      </el-dropdown-menu>
                    </template>
                  </el-dropdown>
                </div>
              </template>
            </el-tree>
          </div>
        </el-card>
      </div>

      <div class="layout-right">
        <el-card shadow="never" class="right-card">
          <div class="card-header right-card-header">
            <div>
              <div class="card-title">{{ currentCategory ? `${currentCategory.name} - 链接列表` : '链接列表' }}</div>
              <div class="card-desc">共 {{ linkTotal }} 条数据</div>
            </div>
            <div class="header-actions">
              <el-input
                v-model.trim="linkQuery.keyword"
                clearable
                placeholder="搜索名称/地址"
                class="search-input"
                @keyup.enter="handleSearch"
              />
              <el-select v-model="linkQuery.link_type" clearable placeholder="链接类型" class="filter-select">
                <el-option v-for="item in linkTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
              </el-select>
              <el-select v-model="linkQuery.platform_type" clearable placeholder="平台类型" class="filter-select">
                <el-option v-for="item in platformOptions" :key="item.value" :label="item.label" :value="item.value" />
              </el-select>
              <el-button class="ml10" @click="handleSearch">查询</el-button>
              <el-button type="primary" icon="el-icon-plus" :disabled="!currentCategoryId" @click="handleAddLink">添加链接</el-button>
            </div>
          </div>

          <el-table
            v-loading="linkLoading"
            :data="linkList"
          >
            <el-table-column prop="id" label="ID" width="80" align="center" />
            <el-table-column label="链接名称" min-width="220">
              <template #default="scope">
                <div class="link-name-cell">
                  <img v-if="scope.row.icon" :src="scope.row.icon" class="link-icon" alt="icon">
                  <div class="link-name-content">
                    <div class="link-name">{{ scope.row.link_name }}</div>
                    <div v-if="scope.row.remark" class="link-remark">{{ scope.row.remark }}</div>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="link_url" label="链接地址" min-width="280" show-overflow-tooltip />
            <el-table-column label="链接类型" width="110" align="center">
              <template #default="scope">
                <el-tag size="mini" type="info">{{ getLinkTypeLabel(scope.row.link_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="平台类型" width="110" align="center">
              <template #default="scope">
                <el-tag size="mini">{{ getPlatformLabel(scope.row.platform_type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="sort" label="排序" width="90" align="center" />
            <el-table-column label="状态" width="100" align="center">
              <template #default="scope">
                <el-switch
                  v-model="scope.row.status"
                  :active-value="1"
                  :inactive-value="0"
                  @change="handleLinkStatusChange(scope.row)"
                />
              </template>
            </el-table-column>
            <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
            <el-table-column label="操作" width="180" align="center" fixed="right">
              <template #default="scope">
                <el-button type="text" @click="handleEditLink(scope.row)">编辑</el-button>
                <el-button type="text" class="danger-btn" @click="handleDeleteLink(scope.row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div v-if="linkTotal > 0" class="pagination-wrap">
            <el-pagination
              :current-page="linkQuery.page"
              :page-size="linkQuery.limit"
              :page-sizes="[10, 20, 50]"
              :total="linkTotal"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="handleLinkSizeChange"
              @current-change="handleLinkPageChange"
            />
          </div>
        </el-card>
      </div>
    </div>

    <el-dialog
      v-model="categoryDialogVisible"
      :title="categoryDialogTitle"
      width="520px"
      :close-on-click-modal="false"
      @closed="resetCategoryForm"
    >
      <el-form ref="categoryFormRef" :model="categoryForm" :rules="categoryRules" label-width="100px">
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="categoryForm.name" maxlength="30" show-word-limit placeholder="请输入分类名称" />
        </el-form-item>
        <el-form-item label="上级分类">
          <el-select v-model="categoryForm.parent_id" placeholder="请选择上级分类" style="width: 100%">
            <el-option label="一级分类" :value="0" />
            <el-option
              v-for="item in topCategoryOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
              :disabled="item.id === categoryForm.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="categoryForm.sort" :min="0" :max="9999" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="categoryForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="categoryDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="categorySubmitting" @click="submitCategoryForm">确定</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog
      v-model="linkDialogVisible"
      :title="linkDialogTitle"
      width="760px"
      :close-on-click-modal="false"
      @closed="resetLinkForm"
    >
      <el-form ref="linkFormRef" :model="linkForm" :rules="linkRules" label-width="100px">
        <el-form-item label="所属分类" prop="cate_id">
          <el-select v-model="linkForm.cate_id" placeholder="请选择所属分类" style="width: 100%">
            <el-option
              v-for="item in flatCategoryOptions"
              :key="item.id"
              :label="item.label"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="链接名称" prop="link_name">
          <el-input v-model="linkForm.link_name" maxlength="100" show-word-limit placeholder="请输入链接名称" />
        </el-form-item>
        <el-form-item label="链接地址" prop="link_url">
          <el-input v-model="linkForm.link_url" maxlength="500" :placeholder="linkUrlPlaceholder" />
        </el-form-item>
        <el-form-item label="链接类型" prop="link_type">
          <el-radio-group v-model="linkForm.link_type">
            <el-radio v-for="item in linkTypeOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="平台类型" prop="platform_type">
          <el-radio-group v-model="linkForm.platform_type">
            <el-radio v-for="item in platformOptions" :key="item.value" :label="item.value">{{ item.label }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="isMiniLinkType" label="小程序 AppID" prop="mini_appid">
          <el-input
            v-model="linkForm.mini_appid"
            maxlength="100"
            show-word-limit
            placeholder="请输入小程序 AppID"
          />
        </el-form-item>
        <el-form-item v-if="isMiniLinkType" label="小程序 Path" prop="mini_path">
          <el-input
            v-model="linkForm.mini_path"
            maxlength="500"
            show-word-limit
            placeholder="请输入小程序页面路径，如 pages/index/index"
          />
        </el-form-item>
        <el-form-item label="图标">
          <Upload v-model="linkForm.icon" :limit="1" />
        </el-form-item>
        <el-form-item label="参数说明">
          <el-input v-model="linkForm.param_desc" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="请输入参数说明" />
        </el-form-item>
        <el-form-item label="示例">
          <el-input v-model="linkForm.example" maxlength="255" placeholder="请输入使用示例" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="linkForm.remark" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="请输入备注" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="linkForm.sort" :min="0" :max="9999" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-switch v-model="linkForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="linkDialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="linkSubmitting" @click="submitLinkForm">确定</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import {
  getDiyCategoryList,
  getDiyCategoryDetail,
  createDiyCategory,
  updateDiyCategory,
  deleteDiyCategory,
  getDiyLinkList,
  getDiyLinkDetail,
  createDiyLink,
  updateDiyLink,
  deleteDiyLink,
  toggleDiyLinkStatus
} from '@/api/diy'

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

function createDefaultCategoryForm() {
  return {
    id: null,
    name: '',
    parent_id: 0,
    sort: 0,
    status: 1
  }
}

function createDefaultLinkForm(cateId = '') {
  return {
    id: null,
    cate_id: cateId || '',
    link_name: '',
    link_url: '',
    link_type: 'page',
    platform_type: 'common',
    mini_appid: '',
    mini_path: '',
    icon: '',
    param_desc: '',
    example: '',
    remark: '',
    sort: 0,
    status: 1
  }
}

defineOptions({
  name: 'DiyLinkManage'
})

const { proxy } = getCurrentInstance()
const categoryTreeRef = ref(null)
const categoryFormRef = ref(null)
const linkFormRef = ref(null)
const categoryLoading = ref(false)
const linkLoading = ref(false)
const categorySubmitting = ref(false)
const linkSubmitting = ref(false)
const categoryTree = ref([])
const currentCategory = ref(null)
const currentCategoryId = ref('')
const categoryDialogVisible = ref(false)
const linkDialogVisible = ref(false)
const categoryDialogTitle = ref('添加分类')
const linkDialogTitle = ref('添加链接')
const treeProps = {
  children: 'children',
  label: 'name'
}
const categoryForm = reactive(createDefaultCategoryForm())
const linkForm = reactive(createDefaultLinkForm())
const linkQuery = reactive({
  page: 1,
  limit: 20,
  keyword: '',
  link_type: '',
  platform_type: ''
})
const linkList = ref([])
const linkTotal = ref(0)
const linkTypeOptions = LINK_TYPE_OPTIONS
const platformOptions = PLATFORM_OPTIONS

const canAddChild = computed(() => !!(currentCategory.value && Number(currentCategory.value.pid) === 0))
const isMiniLinkType = computed(() => linkForm.link_type === 'mini')
const linkUrlPlaceholder = computed(() => (
  isMiniLinkType.value ? '小程序类型可留空，保存时默认使用下方 Path 作为链接地址' : '请输入链接地址'
))
const topCategoryOptions = computed(() => categoryTree.value.map(item => ({
  id: item.id,
  name: item.name || item.label
})))
const flatCategoryOptions = computed(() => {
  const result = []
  const walk = (list, prefix = '') => {
    list.forEach(item => {
      const name = item.name || item.label
      result.push({ id: item.id, label: prefix ? `${prefix} / ${name}` : name })
      if (Array.isArray(item.children) && item.children.length > 0) {
        walk(item.children, prefix ? `${prefix} / ${name}` : name)
      }
    })
  }
  walk(categoryTree.value)
  return result
})

function resetCategoryFormValues() {
  Object.assign(categoryForm, createDefaultCategoryForm())
}

function resetLinkFormValues() {
  Object.assign(linkForm, createDefaultLinkForm(currentCategoryId.value))
}

function getCategoryTreeSnapshot() {
  return Array.isArray(categoryTree.value) ? categoryTree.value : []
}

watch(() => linkForm.link_type, (value) => {
  if (value !== 'mini' && linkFormRef.value) {
    nextTick(() => {
      linkFormRef.value && linkFormRef.value.clearValidate(['mini_appid', 'mini_path'])
    })
  }
})

async function loadCategoryTree(selectId) {
  categoryLoading.value = true
  try {
    const res = await getDiyCategoryList()
    const data = res.data || {}
    categoryTree.value = data.list || []
    const targetId = selectId || currentCategoryId.value || findFirstCategoryId(getCategoryTreeSnapshot())
    if (targetId) {
      const current = findCategoryById(getCategoryTreeSnapshot(), targetId)
      if (current) {
        currentCategory.value = current
        currentCategoryId.value = current.id
        nextTick(() => {
          categoryTreeRef.value && categoryTreeRef.value.setCurrentKey(current.id)
        })
        await loadLinkList()
        return
      }
    }
    currentCategory.value = null
    currentCategoryId.value = ''
    linkList.value = []
    linkTotal.value = 0
  } catch (e) {
    proxy.$message.error(e.message || '获取分类失败')
  } finally {
    categoryLoading.value = false
  }
}

function findFirstCategoryId(list) {
  if (!Array.isArray(list) || list.length === 0) return ''
  if (list[0].children && list[0].children.length > 0) return list[0].children[0].id
  return list[0].id
}

function findCategoryById(list, id) {
  let target = null
  const walk = (items) => {
    for (const item of items) {
      if (Number(item.id) === Number(id)) {
        target = item
        return
      }
      if (item.children && item.children.length > 0) {
        walk(item.children)
        if (target) return
      }
    }
  }
  walk(list)
  return target
}

function handleCategorySelect(data) {
  currentCategory.value = data
  currentCategoryId.value = data.id
  linkQuery.page = 1
  void loadLinkList()
}

function handleAddRootCategory() {
  categoryDialogTitle.value = '添加一级分类'
  resetCategoryFormValues()
  categoryDialogVisible.value = true
}

function handleAddChildCategory(category = null) {
  const parent = category || currentCategory.value
  if (!parent || Number(parent.pid) !== 0) {
    proxy.$message.warning('请选择一级分类后再添加二级分类')
    return
  }
  categoryDialogTitle.value = '添加二级分类'
  Object.assign(categoryForm, {
    ...createDefaultCategoryForm(),
    parent_id: parent.id
  })
  categoryDialogVisible.value = true
}

function handleCategoryMenuCommand(category, command) {
  if (command === 'add_child') {
    handleAddChildCategory(category)
    return
  }
  if (command === 'edit') {
    void handleEditCategory(category)
    return
  }
  if (command === 'delete') {
    handleDeleteCategory(category)
  }
}

async function handleEditCategory(category) {
  try {
    const res = await getDiyCategoryDetail(category.id)
    const data = res.data || category
    const path = Array.isArray(data.path) ? data.path : []
    categoryDialogTitle.value = '编辑分类'
    Object.assign(categoryForm, {
      id: data.id,
      name: data.name,
      parent_id: path.length > 0 ? path[path.length - 1] : 0,
      sort: data.sort || 0,
      status: data.status
    })
    categoryDialogVisible.value = true
  } catch (e) {
    proxy.$message.error(e.message || '获取分类详情失败')
  }
}

function handleDeleteCategory(category) {
  proxy.$confirm(`确定删除分类「${category.name || category.label}」吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async() => {
    try {
      const res = await deleteDiyCategory(category.id)
      proxy.$message.success(res.message || '删除成功')
      const needReset = Number(currentCategoryId.value) === Number(category.id)
      await loadCategoryTree(needReset ? '' : currentCategoryId.value)
    } catch (e) {
      proxy.$message.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

function submitCategoryForm() {
  categoryFormRef.value.validate(async(valid) => {
    if (!valid) return
    categorySubmitting.value = true
    try {
      const payload = {
        name: categoryForm.name,
        sort: categoryForm.sort,
        status: categoryForm.status,
        pid: categoryForm.parent_id,
        path: categoryForm.parent_id ? [categoryForm.parent_id] : []
      }
      let res
      if (categoryForm.id) {
        res = await updateDiyCategory(categoryForm.id, payload)
      } else {
        res = await createDiyCategory(payload)
      }
      proxy.$message.success(res.message || '操作成功')
      const targetId = categoryForm.id || currentCategoryId.value || categoryForm.parent_id
      categoryDialogVisible.value = false
      await loadCategoryTree(targetId)
    } catch (e) {
      proxy.$message.error(e.message || '操作失败')
    } finally {
      categorySubmitting.value = false
    }
  })
}

function resetCategoryForm() {
  categoryFormRef.value && categoryFormRef.value.resetFields()
  resetCategoryFormValues()
}

async function loadLinkList() {
  if (!currentCategoryId.value) {
    linkList.value = []
    linkTotal.value = 0
    return
  }
  linkLoading.value = true
  try {
    const res = await getDiyLinkList({
      ...linkQuery,
      cate_id: currentCategoryId.value
    })
    const data = res.data || {}
    linkList.value = data.list || []
    linkTotal.value = data.count || 0
  } catch (e) {
    proxy.$message.error(e.message || '获取链接列表失败')
  } finally {
    linkLoading.value = false
  }
}

function handleSearch() {
  linkQuery.page = 1
  void loadLinkList()
}

function handleLinkPageChange(page) {
  linkQuery.page = page
  void loadLinkList()
}

function handleLinkSizeChange(limit) {
  linkQuery.limit = limit
  linkQuery.page = 1
  void loadLinkList()
}

function handleAddLink() {
  if (!currentCategoryId.value) {
    proxy.$message.warning('请先选择分类')
    return
  }
  linkDialogTitle.value = '添加链接'
  resetLinkFormValues()
  linkDialogVisible.value = true
}

async function handleEditLink(row) {
  try {
    const res = await getDiyLinkDetail(row.id)
    const data = res.data || row
    linkDialogTitle.value = '编辑链接'
    Object.assign(linkForm, {
      id: data.id,
      cate_id: data.cate_id,
      link_name: data.link_name,
      link_url: data.link_url,
      link_type: data.link_type,
      platform_type: data.platform_type,
      mini_appid: data.mini_appid || '',
      mini_path: data.mini_path || '',
      icon: data.icon || '',
      param_desc: data.param_desc || '',
      example: data.example || '',
      remark: data.remark || '',
      sort: data.sort || 0,
      status: data.status
    })
    linkDialogVisible.value = true
  } catch (e) {
    proxy.$message.error(e.message || '获取链接详情失败')
  }
}

function handleDeleteLink(row) {
  proxy.$confirm(`确定删除链接「${row.link_name}」吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async() => {
    try {
      const res = await deleteDiyLink(row.id)
      proxy.$message.success(res.message || '删除成功')
      void loadLinkList()
    } catch (e) {
      proxy.$message.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

function handleLinkStatusChange(row) {
  const currentStatus = row.status
  const previousStatus = row.status === 1 ? 0 : 1
  return toggleDiyLinkStatus(row.id, currentStatus).then(res => {
    proxy.$message.success(res.message || '状态更新成功')
  }).catch(e => {
    row.status = previousStatus
    proxy.$message.error(e.message || '状态更新失败')
  })
}

function submitLinkForm() {
  linkFormRef.value.validate(async(valid) => {
    if (!valid) return
    linkSubmitting.value = true
    try {
      const payload = buildLinkPayload()
      let res
      if (payload.id) {
        const id = payload.id
        delete payload.id
        res = await updateDiyLink(id, payload)
      } else {
        delete payload.id
        res = await createDiyLink(payload)
      }
      proxy.$message.success(res.message || '操作成功')
      linkDialogVisible.value = false
      if (Number(currentCategoryId.value) !== Number(payload.cate_id)) {
        await loadCategoryTree(payload.cate_id)
      } else {
        await loadLinkList()
      }
    } catch (e) {
      proxy.$message.error(e.message || '操作失败')
    } finally {
      linkSubmitting.value = false
    }
  })
}

function buildLinkPayload() {
  const payload = {
    ...linkForm,
    link_url: String(linkForm.link_url || '').trim(),
    mini_appid: String(linkForm.mini_appid || '').trim(),
    mini_path: String(linkForm.mini_path || '').trim()
  }
  if (payload.link_type === 'mini') {
    payload.link_url = payload.mini_path
  }
  if (payload.link_type !== 'mini') {
    payload.mini_appid = ''
    payload.mini_path = ''
  }
  return payload
}

function resetLinkForm() {
  linkFormRef.value && linkFormRef.value.resetFields()
  resetLinkFormValues()
}

function getLinkTypeLabel(value) {
  const target = linkTypeOptions.find(item => item.value === value)
  return target ? target.label : value || '未设置'
}

function getPlatformLabel(value) {
  const target = platformOptions.find(item => item.value === value)
  return target ? target.label : value || '未设置'
}

onBeforeMount(() => {
  void loadCategoryTree()
})
</script>

<style lang="scss" scoped>
.diy-link-page {
  min-height: calc(100vh - 124px);
  padding: 0;
  border-radius: 4px;
  background: var(--prev-bg-white);
}

.page-layout {
  display: flex;
  align-items: stretch;
  min-height: calc(100vh - 124px);
}

.layout-left {
  width: 260px;
  min-width: 260px;
  flex-shrink: 0;
}

.layout-right {
  flex: 1;
  min-width: 0;
  margin-left: 20px;
  margin-top: 20px;
}

.left-card,
.right-card {
  height: 100%;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.left-card {
  border-right: 1px solid var(--prev-border-color-light);

  :deep(.el-card__header) {
    padding: 20px 20px 18px 24px;
    border-bottom: 0;
  }

  :deep(.el-card__body) {
    padding: 0 12px 20px 16px;
  }
}

.right-card {
  :deep(.el-card__body) {
    padding: 0 13px 10px 0;
  }
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 32px;
}

.pane-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 32px;
}

.pane-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.pane-desc {
  margin-top: 4px;
  font-size: 12px;
  color: var(--ds-on-surface-variant);
}

.pane-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.pane-action,
.pane-sub-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0;
  border-radius: 2px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.2s ease;
}

.pane-action {
  color: var(--ds-primary);

  &:hover {
    color: var(--ds-primary-hover);
  }
}

.pane-sub-action {
  background: var(--ds-surface-low);
  color: var(--ds-on-surface-variant);
  box-shadow: inset 0 0 0 1px var(--ds-outline-ghost);

  &:hover {
    color: var(--ds-primary);
    background: var(--ds-surface-card);
    box-shadow: 0 4px 12px rgba(243, 65, 0, 0.12);
    transform: translateY(-1px);
  }

  &.is-disabled {
    color: var(--prev-color-text-placeholder);
    background: var(--ds-surface-low);
    cursor: not-allowed;
    box-shadow: inset 0 0 0 1px var(--ds-outline-ghost);
    transform: none;
  }
}

.tree-wrap {
  min-height: 560px;
  overflow-x: hidden;
  overflow-y: auto;

  &::-webkit-scrollbar {
    display: none;
  }

  :deep(.el-tree) {
    background: transparent;
  }

  :deep(.el-tree-node__content) {
    height: 40px;
    padding-right: 10px;
    border-radius: 2px;
    background: transparent;
    margin-bottom: 4px;
    transition: background 0.2s ease, color 0.2s ease;
  }

  :deep(.el-tree-node:focus > .el-tree-node__content),
  :deep(.el-tree-node__content:hover),
  :deep(.el-tree--highlight-current .el-tree-node.is-current > .el-tree-node__content) {
    background: var(--ds-surface-low);
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

.category-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  width: 100%;
  height: 40px;
  padding: 0 10px;
  border-radius: 2px;
  background: transparent;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;

  &:hover {
    background: var(--ds-surface-low);
  }

  &.active {
    background: var(--ds-surface-low);
  }
}

.category-main {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.category-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  color: var(--ds-primary);
  flex-shrink: 0;

  i {
    font-size: 16px;
    line-height: 1;
  }
}

.category-item.active .category-icon {
  color: var(--ds-primary);
}

.category-content {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.category-name {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--ds-on-surface-variant);
  font-size: 14px;
  font-weight: 400;
  line-height: 20px;
}

.category-item.active .category-name {
  color: var(--ds-primary);
}

.category-more {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
}

.category-more-trigger {
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

.right-card-header {
  margin-bottom: 16px;
}

.card-title {
  color: var(--ds-on-surface);
  font-size: 16px;
  font-weight: 600;
}

.card-desc {
  margin-top: 4px;
  color: var(--ds-on-surface-variant);
  font-size: 13px;
}

.header-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}

.search-input {
  width: 220px;
}

.filter-select {
  width: 120px;
}

.ml10 {
  margin-left: 0;
}

.link-name-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.link-icon {
  width: 36px;
  height: 36px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid var(--prev-border-color-light);
}

.link-name-content {
  min-width: 0;
}

.link-name {
  font-weight: 500;
  color: var(--ds-on-surface);
}

.link-remark {
  margin-top: 2px;
  font-size: 12px;
  color: var(--ds-on-surface-variant);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.danger-btn {
  color: var(--dashboard-danger);
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

@media (max-width: 1200px) {
  .layout-left {
    width: 240px;
    min-width: 240px;
  }
}

@media (max-width: 960px) {
  .page-layout {
    display: block;
    min-height: auto;
  }

  .layout-left {
    width: 100%;
    min-width: 0;
    margin-bottom: 16px;
  }

  .layout-right {
    margin-left: 0;
  }

  .left-card {
    border-right: 0;
    border-bottom: 1px solid var(--prev-border-color-light);

    :deep(.el-card__header) {
      padding-right: 0;
    }

    :deep(.el-card__body) {
      padding-right: 0;
    }
  }

  .right-card {
    :deep(.el-card__body) {
      padding-right: 0;
    }
  }

  .tree-wrap {
    min-height: auto;
  }

  .right-card-header,
  .header-actions {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
