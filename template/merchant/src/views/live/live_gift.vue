<template>
  <div class="live-gift-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播礼物</h1>
        <p class="page-curator-subtitle">
          管理直播礼物图标、金币价格与兑换金额，统一直播互动道具配置。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="handleAdd">
        添加礼物
      </el-button>
    </header>

    <div class="live-gift-work-surface">
      <!-- 搜索区域 -->
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
          :model="searchForm"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="礼物名称">
            <el-input
              v-model="searchForm.name"
              clearable
              placeholder="请输入礼物名称"
              class="search-input"
              :prefix-icon="Search"
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
            <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 表格区域 -->
      <el-card shadow="never" class="table-card">
        <el-table
          v-loading="loading"
          :data="giftList"
          class="mt14 live-gift-curator-table"
        >
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column label="礼物图标" width="80" align="center">
            <template #default="{ row }">
              <el-image
                v-if="row.icon"
                :src="row.icon"
                :preview-src-list="[row.icon]"
                class="gift-icon"
                fit="contain"
              />
              <LegacyIcon v-else name="el-icon-present" class="gift-placeholder" />
            </template>
          </el-table-column>
          <el-table-column prop="name" label="礼物名称" min-width="100" />
          <el-table-column label="价格（金币）" width="120" align="center">
            <template #default="{ row }">
              <span class="price-text">{{ row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="兑换金额" width="110" align="center">
            <template #default="{ row }">
              <span>￥{{ row.exchange_amount }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="80" align="center" />
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-switch
                v-model="row.status"
                :active-value="1"
                :inactive-value="0"
                @change="handleStatusChange(row)"
              />
            </template>
          </el-table-column>
          <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
          <el-table-column label="操作" width="130" fixed="right" align="center">
            <template #default="{ row }">
              <a @click="handleEdit(row.id)">编辑</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleDelete(row.id)">删除</a>
            </template>
          </el-table-column>
        </el-table>

        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            :total="total"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="520px"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
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
              <LegacyIcon name="el-icon-circle-close" class="icon-remove" @click.stop="editForm.icon = ''" />
            </div>
          </div>
          <div class="tips-text">点击选择/更换图标图片</div>
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
      <template #footer><span class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
      </span></template>
    </el-dialog>
  </div>
</template>

<script setup>
import {
  getGiftList as getGiftListApi,
  getGiftDetail,
  createGift,
  updateGift,
  deleteGift,
  showGift
} from '@/api/live'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'

defineOptions({
  name: 'LiveGiftManage'
})

const { proxy } = getCurrentInstance() || {}

const loading = ref(false)
const giftList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  name: '',
  status: ''
})
const dialogVisible = ref(false)
const dialogTitle = ref('添加礼物')
const submitLoading = ref(false)
const editId = ref(0)
const editForm = reactive({
  name: '',
  icon: '',
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

const searchFormRef = ref(null)
const editFormRef = ref(null)

onMounted(() => {
  getGiftList()
})

function getGiftList() {
  loading.value = true
  getGiftListApi({
    page: page.value,
    limit: limit.value,
    ...searchForm
  }).then(res => {
    giftList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getGiftList()
}

function handleReset() {
  searchFormRef.value.resetFields()
  Object.assign(searchForm, { name: '', status: '' })
  handleSearch()
}

function handleAdd() {
  dialogTitle.value = '添加礼物'
  editId.value = 0
  Object.assign(editForm, { name: '', icon: '', price: 0, exchange_amount: 0, sort: 0, status: 1 })
  dialogVisible.value = true
}

function handleChooseIcon() {
  if (!proxy || typeof proxy.$imgModal !== 'function') return
  proxy.$imgModal((image) => {
    editForm.icon = image.path
  }, 1)
}

function handleEdit(id) {
  getGiftDetail(id).then(res => {
    const d = res.data
    editId.value = d.id
    Object.assign(editForm, {
      name: d.name,
      icon: d.icon || '',
      price: d.price,
      exchange_amount: d.exchange_amount || 0,
      sort: d.sort || 0,
      status: d.status
    })
    dialogTitle.value = '编辑礼物'
    dialogVisible.value = true
  })
}

function handleSubmit() {
  editFormRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const api = editId.value ? updateGift(editId.value, editForm) : createGift(editForm)
    api.then(() => {
      ElMessage.success(editId.value ? '编辑成功' : '添加成功')
      dialogVisible.value = false
      getGiftList()
    }).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleDialogClose() {
  editFormRef.value && editFormRef.value.resetFields()
}

function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该礼物吗？', '提示', { type: 'warning' }).then(() => {
    deleteGift(id).then(() => {
      ElMessage.success('删除成功')
      getGiftList()
    })
  })
}

function handleStatusChange(row) {
  showGift(row.id, row.status).then(() => {
    ElMessage.success('状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getGiftList()
}

function handleCurrentChange(val) {
  page.value = val
  getGiftList()
}
</script>

<style lang="scss" scoped>
.live-gift-curator-page {
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
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.live-gift-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: var(--brand-primary-pale);
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

.live-gift-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.live-gift-work-surface {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next:hover),
  :deep(.btn-prev:hover),
  :deep(.el-pager li:hover) {
    color: var(--ds-primary);
  }
}

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.mt14 {
  margin-top: 14px;
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
  color: var(--text-placeholder);
}

.icon-upload-wrap {
  display: inline-block;
}

.icon-upload-btn {
  width: 64px;
  height: 64px;
  border: 1px dashed var(--ds-outline);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 22px;
  color: var(--text-secondary);

  &:hover {
    border-color: var(--ds-primary);
    color: var(--ds-primary);
  }
}

.icon-upload-preview {
  position: relative;
  width: 64px;
  height: 64px;
  cursor: pointer;
  border: 1px solid var(--ds-outline);
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
      color: var(--text-danger);
    }
  }
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
}

.tips-text {
  margin-left: 8px;
  color: var(--text-secondary);
  font-size: 12px;
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.danger-link {
  color: var(--text-danger);
}

a {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--ds-primary-hover);
  }
}
</style>
