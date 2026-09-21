<template>
  <div class="live-goods-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">直播商品</h1>
        <p class="page-curator-subtitle">
          管理直播间挂载的内容商品，支持状态切换、排序与专题挂载配置。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="handleAdd">
        添加挂载商品
      </el-button>
    </header>

    <div class="live-goods-work-surface">
      <!-- 搜索区域 -->
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
          :model="searchForm"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="直播间">
            <el-select
              v-model="searchForm.live_room_id"
              placeholder="请选择直播间"
              filterable
              clearable
              class="search-select"
            >
              <el-option
                v-for="item in roomOptions"
                :key="item.id"
                :label="item.title"
                :value="item.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="商品类型">
            <el-select
              v-model="searchForm.goods_type"
              placeholder="全部类型"
              clearable
              class="search-select-sm"
            >
              <el-option label="单篇内容" value="article" />
              <el-option label="付费专题" value="special" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select
              v-model="searchForm.status"
              placeholder="全部"
              clearable
              class="search-select-sm"
            >
              <el-option label="上架" :value="1" />
              <el-option label="下架" :value="0" />
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
          :data="goodsList"
          class="mt14 live-goods-curator-table"
        >
          <el-table-column prop="id" label="ID" width="60" align="center" />
          <el-table-column label="链接编号" width="90" align="center">
            <template #default="{ row }">
              <el-tag type="success" >{{ row.link_no }}号链接</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="封面" width="70" align="center">
            <template #default="{ row }">
              <el-image
                v-if="row.goods_cover"
                :src="row.goods_cover"
                :preview-src-list="[row.goods_cover]"
                class="goods-cover"
                fit="cover"
              />
              <span v-else class="no-cover">暂无</span>
            </template>
          </el-table-column>
          <el-table-column prop="goods_title" label="商品标题" min-width="140" show-overflow-tooltip />
          <el-table-column label="商品类型" width="100" align="center">
            <template #default="{ row }">
              <el-tag
                :type="row.goods_type === 'special' ? 'warning' : 'primary'"

              >
                {{ row.goods_type === 'special' ? '付费专题' : '单篇内容' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="价格" width="100" align="center">
            <template #default="{ row }">
              <span class="price-text">¥{{ row.goods_price }}</span>
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
          <el-table-column prop="create_time" label="添加时间" width="160" align="center" />
          <el-table-column label="操作" width="130" fixed="right" align="center">
            <template #default="{ row }">
              <a @click="handleEdit(row.id)">编辑</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleDelete(row.id)">移除</a>
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
      width="580px"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
      <el-form
        ref="editFormRef"
        :model="editForm"
        :rules="editRules"
        label-width="110px"
      >
        <el-form-item label="直播间" prop="live_room_id">
          <el-select
            v-model="editForm.live_room_id"
            placeholder="请选择直播间"
            filterable
            class="dialog-select"
          >
            <el-option
              v-for="item in roomOptions"
              :key="item.id"
              :label="item.title"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="商品类型" prop="goods_type">
          <el-radio-group v-model="editForm.goods_type" @change="handleGoodsTypeChange">
            <el-radio label="special">付费专题</el-radio>
            <el-radio label="article">单篇内容</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 选择专题（仅 special 类型显示） -->
        <el-form-item v-if="editForm.goods_type === 'special'" label="选择专题" prop="goods_id">
          <el-select
            v-model="editForm.goods_id"
            placeholder="请选择专题，自动填入标题/封面/价格"
            filterable
            class="dialog-select"
            @change="handleSpecialChange"
          >
            <el-option
              v-for="item in specialOptions"
              :key="item.id"
              :label="item.title"
              :value="item.id"
            >
              <div class="special-option">
                <span>{{ item.title }}</span>
                <span class="special-price">¥{{ item.price }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="商品标题" prop="goods_title">
          <el-input
            v-model="editForm.goods_title"
            placeholder="展示名称，可自定义修改"
            class="dialog-select"
          />
        </el-form-item>

        <el-form-item label="商品封面">
          <Upload v-model="editForm.goods_cover" :limit="1" />
        </el-form-item>

        <el-form-item label="展示价格" prop="goods_price">
          <el-input-number v-model="editForm.goods_price" :min="0" :precision="2" :step="0.01" />
          <span class="tips-text">可在原价基础上修改展示价格</span>
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort" :min="0" :max="9999" />
        </el-form-item>

        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio :label="1">上架</el-radio>
            <el-radio :label="0">下架</el-radio>
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
  getLiveGoodsList,
  getLiveGoodsDetail,
  createLiveGoods,
  updateLiveGoods,
  deleteLiveGoods,
  showLiveGoods,
  getRoomList,
  getSpecialOptions
} from '@/api/live'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { onMounted, reactive, ref } from 'vue'

defineOptions({
  name: 'LiveGoodsManage'
})

const loading = ref(false)
const goodsList = ref([])
const roomOptions = ref([])
const specialOptions = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  live_room_id: '',
  goods_type: '',
  status: ''
})
const dialogVisible = ref(false)
const dialogTitle = ref('添加挂载商品')
const submitLoading = ref(false)
const editId = ref(0)
const editForm = reactive({
  live_room_id: '',
  goods_type: 'special',
  goods_id: 0,
  goods_title: '',
  goods_cover: '',
  goods_price: 0,
  sort: 0,
  status: 1
})
const editRules = {
  live_room_id: [{ required: true, message: '请选择直播间', trigger: 'change' }],
  goods_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
  goods_id: [{
    validator: (rule, value, callback) => {
      if (editForm.goods_type === 'special' && !value) {
        callback(new Error('请选择专题'))
      } else {
        callback()
      }
    },
    trigger: 'change'
  }],
  goods_title: [{ required: true, message: '请输入商品标题', trigger: 'blur' }],
  goods_price: [{ required: true, message: '请输入展示价格', trigger: 'blur' }]
}

const searchFormRef = ref(null)
const editFormRef = ref(null)

onMounted(() => {
  loadRoomOptions()
  loadSpecialOptions()
  getGoodsList()
})

function loadRoomOptions() {
  getRoomList({ page: 1, limit: 200 }).then(res => {
    roomOptions.value = res.data.list || res.data || []
  })
}

function loadSpecialOptions() {
  getSpecialOptions().then(res => {
    specialOptions.value = res.data || []
  })
}

function getGoodsList() {
  loading.value = true
  getLiveGoodsList({
    page: page.value,
    limit: limit.value,
    ...searchForm
  }).then(res => {
    goodsList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getGoodsList()
}

function handleReset() {
  searchFormRef.value.resetFields()
  Object.assign(searchForm, { live_room_id: '', goods_type: '', status: '' })
  handleSearch()
}

function handleAdd() {
  dialogTitle.value = '添加挂载商品'
  editId.value = 0
  Object.assign(editForm, {
    live_room_id: '',
    goods_type: 'special',
    goods_id: 0,
    goods_title: '',
    goods_cover: '',
    goods_price: 0,
    sort: 0,
    status: 1
  })
  dialogVisible.value = true
}

function handleEdit(id) {
  getLiveGoodsDetail(id).then(res => {
    const d = res.data
    editId.value = d.id
    Object.assign(editForm, {
      live_room_id: d.live_room_id,
      goods_type: d.goods_type || 'special',
      goods_id: d.goods_id,
      goods_title: d.goods_title,
      goods_cover: d.goods_cover || '',
      goods_price: d.goods_price,
      sort: d.sort || 0,
      status: d.status
    })
    dialogTitle.value = '编辑挂载商品'
    dialogVisible.value = true
  })
}

// 切换商品类型时清空关联选择
function handleGoodsTypeChange() {
  editForm.goods_id = 0
  editForm.goods_title = ''
  editForm.goods_cover = ''
  editForm.goods_price = 0
}

// 选中专题后自动回填标题、封面、价格
function handleSpecialChange(id) {
  const found = specialOptions.value.find(item => item.id === id)
  if (found) {
    editForm.goods_title = found.title
    editForm.goods_cover = found.cover || ''
    editForm.goods_price = found.price || 0
  }
}

function handleSubmit() {
  editFormRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const api = editId.value
      ? updateLiveGoods(editId.value, editForm)
      : createLiveGoods(editForm)
    api.then(() => {
      ElMessage.success(editId.value ? '编辑成功' : '添加成功')
      dialogVisible.value = false
      getGoodsList()
    }).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleDialogClose() {
  editFormRef.value && editFormRef.value.resetFields()
}

function handleDelete(id) {
  ElMessageBox.confirm('确定要移除该挂载商品吗？', '提示', { type: 'warning' }).then(() => {
    deleteLiveGoods(id).then(() => {
      ElMessage.success('移除成功')
      getGoodsList()
    })
  })
}

function handleStatusChange(row) {
  showLiveGoods(row.id, row.status).then(() => {
    ElMessage.success('状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getGoodsList()
}

function handleCurrentChange(val) {
  page.value = val
  getGoodsList()
}
</script>

<style lang="scss" scoped>
.live-goods-curator-page {
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

.live-goods-work-surface {
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

.live-goods-curator-table {
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

.live-goods-work-surface {
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

.search-select {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}

.dialog-select {
  width: 320px;
}

.mt14 {
  margin-top: 14px;
}

.goods-cover {
  width: 50px;
  height: 36px;
  border-radius: 4px;
}

.no-cover {
  color: var(--text-placeholder);
  font-size: 12px;
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
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

.tips-text {
  margin-left: 8px;
  color: var(--text-secondary);
  font-size: 12px;
}

.special-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.special-price {
  color: var(--text-warning);
  font-size: 13px;
  font-weight: 600;
  margin-left: 12px;
}
</style>
