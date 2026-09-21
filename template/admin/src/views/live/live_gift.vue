<template>
  <div class="gift-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">礼物管理</h1>
        <p class="page-curator-subtitle">
          管理直播间礼物，设置礼物价格、图标及兑换金额。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        添加礼物
      </el-button>
    </header>

    <div class="gift-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form gift-search-form"
        @submit.prevent
      >
        <el-form-item label="礼物名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入礼物名称"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="giftList"
        class="gift-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="礼物图标" width="80" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.icon"
              :src="scope.row.icon"
              :preview-src-list="[scope.row.icon]"
              class="gift-icon"
              fit="contain"
            />
            <LegacyIcon v-else name="el-icon-present" class="gift-placeholder" />
          </template>
        </el-table-column>
        <el-table-column prop="name" label="礼物名称" min-width="100" />
        <el-table-column label="价格（金币）" width="120" align="center">
          <template #default="scope">
            <span class="price-text">{{ scope.row.price }}</span>
          </template>
        </el-table-column>
        <el-table-column label="兑换金额" width="110" align="center">
          <template #default="scope">
            <span>￥{{ scope.row.exchange_amount }}</span>
          </template>
        </el-table-column>
        <el-table-column label="礼物特效" width="100" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.effect_url" size="mini" type="success">已配置</el-tag>
            <span v-else class="tips-text">无</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="90" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
        <el-table-column label="操作" width="130" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row.id)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="gift-curator-pagination"
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 新增/编辑抽屉 -->
    <el-drawer
      v-model="drawerVisible"
      :title="drawerTitle"
      direction="rtl"
      size="560px"
      custom-class="gift-form-drawer"
      :wrapper-closable="false"
      @close="handleDrawerClose"
    >
      <div class="gift-form-drawer__body">
        <el-form
          ref="editFormRef"
          :model="editForm"
          :rules="editRules"
          label-width="100px"
        >
          <el-form-item label="礼物名称" prop="name">
            <el-input v-model="editForm.name" placeholder="请输入礼物名称" />
          </el-form-item>
          <el-form-item label="礼物图标">
            <div class="icon-upload-wrap">
              <div
                v-if="!editForm.icon"
                class="icon-upload-btn"
                @click="handleChooseIcon"
              >
                <LegacyIcon name="el-icon-plus" />
              </div>
              <div v-else class="icon-upload-preview" @click="handleChooseIcon">
                <el-image :src="editForm.icon" class="gift-icon-lg" fit="contain" />
                <LegacyIcon name="el-icon-circle-close" class=" icon-remove" @click.stop="editForm.icon = ''" />
              </div>
            </div>
            <div class="tips-text">点击选择/更换图标图片</div>
          </el-form-item>
          <el-form-item label="礼物特效">
            <BigFileUpload
              v-model="editForm.effect_url"
              accept="video/webm,.webm"
              :allow-extensions="['webm']"
              progress-type="circle"
              tip="仅支持 WebM 视频特效"
            />
          </el-form-item>
          <el-form-item label="价格（金币）" prop="price">
            <el-input-number v-model="editForm.price" :min="0" :precision="0" />
            <span class="tips-text">用户送礼需消耗的金币数量</span>
          </el-form-item>
          <el-form-item label="兑换金额" prop="exchange_amount">
            <el-input-number v-model="editForm.exchange_amount" :min="0" :precision="2" :step="0.01" />
            <span class="tips-text">主播收到礼物可兑换的金额（元）</span>
          </el-form-item>
          <el-form-item label="排序">
            <el-input-number v-model="editForm.sort" :min="0" :max="9999" />
          </el-form-item>
          <el-form-item label="状态">
            <el-radio-group v-model="editForm.status">
              <el-radio :label="1">启用</el-radio>
              <el-radio :label="0">禁用</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
      </div>
      <div class="gift-form-drawer__footer">
        <el-button @click="drawerVisible = false">取 消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getGiftList,
  getGiftDetail,
  createGift,
  updateGift,
  deleteGift,
  showGift
} from '@/api/live'
import BigFileUpload from '@/components/BigFileUpload/index'

defineOptions({
  name: 'LiveGiftManage'
})

const { proxy } = getCurrentInstance() || {}

const searchFormRef = ref(null)
const editFormRef = ref(null)
const loading = ref(false)
const giftList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const drawerVisible = ref(false)
const drawerTitle = ref('添加礼物')
const submitLoading = ref(false)
const editId = ref(0)
const searchForm = reactive({
  name: '',
  status: ''
})
const editForm = reactive({
  name: '',
  icon: '',
  effect_url: '',
  price: 0,
  exchange_amount: 0,
  sort: 0,
  status: 1
})
const editRules = {
  name: [{ required: true, message: '请输入礼物名称', trigger: 'blur' }],
  price: [{ required: true, message: '请输入价格', trigger: 'blur' }],
  exchange_amount: [{ required: true, message: '请输入兑换金额', trigger: 'blur' }]
}

const DEFAULT_SEARCH_FORM = {
  name: '',
  status: ''
}
const DEFAULT_EDIT_FORM = {
  name: '',
  icon: '',
  effect_url: '',
  price: 0,
  exchange_amount: 0,
  sort: 0,
  status: 1
}

onBeforeMount(() => {
  fetchGiftList()
})

function fetchGiftList() {
  loading.value = true
  getGiftList({
    page: page.value,
    limit: limit.value,
    ...searchForm
  }).then(res => {
    giftList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
  }).catch(() => {
    giftList.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  fetchGiftList()
}

function handleReset() {
  searchFormRef.value?.resetFields?.()
  Object.assign(searchForm, DEFAULT_SEARCH_FORM)
  handleSearch()
}

function handleAdd() {
  drawerTitle.value = '添加礼物'
  editId.value = 0
  Object.assign(editForm, DEFAULT_EDIT_FORM)
  drawerVisible.value = true
}

function handleChooseIcon() {
  proxy?.$imgModal?.((image) => {
    editForm.icon = image.path
  }, 1)
}

function handleEdit(id) {
  getGiftDetail(id).then(res => {
    const d = res.data || {}
    editId.value = d.id
    Object.assign(editForm, {
      name: d.name,
      icon: d.icon || '',
      effect_url: d.effect_url || '',
      price: d.price,
      exchange_amount: Number(d.exchange_amount || 0),
      sort: d.sort || 0,
      status: d.status
    })
    drawerTitle.value = '编辑礼物'
    drawerVisible.value = true
  })
}

async function handleSubmit() {
  const valid = await Promise.resolve(editFormRef.value?.validate?.()).catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const api = editId.value ? updateGift(editId.value, { ...editForm }) : createGift({ ...editForm })
    await api
    proxy?.$message?.success(editId.value ? '编辑成功' : '添加成功')
    drawerVisible.value = false
    fetchGiftList()
  } finally {
    submitLoading.value = false
  }
}

function handleDrawerClose() {
  editFormRef.value?.resetFields?.()
}

async function handleDelete(id) {
  if (!proxy?.$confirm) return
  const confirmed = await proxy.$confirm('确定要删除该礼物吗？', '提示', { type: 'warning' }).then(() => true).catch(() => false)
  if (!confirmed) return

  await deleteGift(id)
  proxy?.$message?.success('删除成功')
  fetchGiftList()
}

function handleStatusChange(row) {
  showGift(row.id, row.status).then(() => {
    proxy?.$message?.success('状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchGiftList()
}

function handleCurrentChange(val) {
  page.value = val
  fetchGiftList()
}
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.gift-curator-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.gift-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.gift-form-drawer) {
  max-width: 100vw;

  .el-drawer__header {
    margin-bottom: 0;
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--curator-outline);
    color: var(--curator-on-surface);
    font-size: 18px;
    font-weight: 600;
  }

  .el-drawer__body {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: var(--curator-surface-card);
  }
}

.gift-form-drawer__body {
  flex: 1;
  min-height: 0;
  padding: 24px 24px 8px;
  overflow-y: auto;
}

.gift-form-drawer__footer {
  display: flex;
  justify-content: flex-end;
  padding: 16px 24px;
  border-top: 1px solid var(--curator-outline);
  background: var(--curator-surface-card);
}

.gift-form-drawer__footer .el-button + .el-button {
  margin-left: 12px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--curator-surface-card);
  color: var(--curator-primary);
  border: 1px solid var(--curator-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--curator-primary-container);
    border-color: var(--curator-primary-container);
  }
}

.gift-search-form {
  margin-bottom: 8px;
}

.gift-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: var(--curator-surface-low);
    border-color: var(--curator-outline);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.gift-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--curator-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--curator-primary);
    }
  }
}

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.gift-icon {
  width: 40px;
  height: 40px;
}

.gift-icon-lg {
  width: 64px;
  height: 64px;
  display: block;
}

.gift-placeholder {
  font-size: 28px;
  color: #c0c4cc;
}

.icon-upload-wrap {
  display: inline-block;
}

.icon-upload-btn {
  width: 64px;
  height: 64px;
  border: 1px dashed #d9d9d9;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 22px;
  color: #8c939d;
  &:hover {
    border-color: #409eff;
    color: #409eff;
  }
}

.icon-upload-preview {
  position: relative;
  width: 64px;
  height: 64px;
  cursor: pointer;
  border: 1px solid #e8e8e8;
  border-radius: 4px;
  overflow: visible;
  .icon-remove {
    position: absolute;
    right: -8px;
    top: -8px;
    font-size: 18px;
    color: #999;
    background: #fff;
    border-radius: 50%;
    z-index: 1;
    &:hover {
      color: #f56c6c;
    }
  }
}

.price-text {
  color: #e6a23c;
  font-weight: 600;
}

.tips-text {
  margin-left: 8px;
  color: #909399;
  font-size: 12px;
}

.danger-link {
  color: #f56c6c;
}

a {
  color: var(--curator-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--curator-primary-container);
  }
}
</style>
