<template>
  <div class="product-list-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商品列表</h1>
        <p class="page-curator-subtitle">
          店铺商品：从商品库选品上架，1 个库商品=单规格，多选自动组合多规格；展示销量/浏览量为真实+虚拟展示基数。
        </p>
      </div>
      <div class="page-curator-header__actions">
        <el-button v-if="collectorEnabled" @click="batchUploadVisible = true">批量采集</el-button>
        <el-button v-if="collectorEnabled" @click="openTaskDrawer">采集列表</el-button>
        <el-button
          type="primary"
          class="btn-curator-primary"
          icon="el-icon-plus"
          @click="handleCreate"
        >
          上架商品
        </el-button>
      </div>
    </header>

    <div class="category-work-surface">
      <div class="product-source-tabs-wrap">
        <el-tabs v-model="activeTab" class="product-source-tabs" @tab-change="handleTabChange">
          <el-tab-pane label="全部" name="all" />
          <el-tab-pane label="平台" name="platform" />
          <el-tab-pane label="商户" name="merchant" />
          <el-tab-pane label="待审核" name="pending" />
        </el-tabs>
      </div>

      <!-- 搜索区域 -->
      <el-form :model="searchForm" inline class="category-search-form" @submit.prevent>
        <el-form-item label="商品名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入商品名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="商品编码">
          <el-input
            v-model="searchForm.product_code"
            clearable
            placeholder="请输入商品编码"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="分类">
          <el-cascader
            v-model="categoryPath"
            :options="categoryOptions"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
            placeholder="全部"
            clearable
            filterable
            class="search-select-lg"
            @change="handleCategorySearch"
          />
        </el-form-item>
        <el-form-item label="品牌">
          <el-select v-model="searchForm.brand_id" placeholder="全部" clearable class="search-select-sm">
            <el-option v-for="item in brandOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="规格类型">
          <el-select v-model="searchForm.spec_type" placeholder="全部" clearable class="search-select-sm">
            <el-option label="单规格" :value="0" />
            <el-option label="多规格" :value="1" />
          </el-select>
        </el-form-item>
        <el-form-item label="商品类型">
          <el-select v-model="searchForm.goods_type" placeholder="全部" clearable class="search-select-sm">
            <el-option label="实物" :value="1" />
            <el-option label="虚拟" :value="2" />
            <el-option label="内容" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="上架" :value="1" />
            <el-option label="下架" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="isMerchantTab" label="审核状态">
          <el-select v-model="searchForm.audit_status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="待审核" :value="0" />
            <el-option label="审核通过" :value="1" />
            <el-option label="审核驳回" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="createTimeRange"
            type="daterange"
            value-format="YYYY-MM-DD"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            class="search-date"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="list"
        row-key="id"
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="70" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              :preview-src-list="[scope.row.cover]"
              preview-teleported
              fit="cover"
              class="cover-img"
            />
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="商品名称" min-width="180" show-overflow-tooltip />
        <el-table-column v-if="showMerchantColumns" label="归属商户" min-width="120" show-overflow-tooltip>
          <template #default="scope">
            <span>{{ scope.row.merchant_name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column v-if="showMerchantColumns" label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="auditStatusTagType(scope.row.audit_status)">
              {{ scope.row.audit_status_text || auditStatusText(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="规格类型" width="90" align="center">
          <template #default="scope">
            <el-tag :type="Number(scope.row.spec_type) === 1 ? 'warning' : 'info'">
              {{ specTypeText(scope.row.spec_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="商品类型" width="90" align="center">
          <template #default="scope">
            <el-tag :type="goodsTypeTagType(scope.row.goods_type)">
              {{ goodsTypeText(scope.row.goods_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sku_count" label="SKU数" width="80" align="center" />
        <el-table-column label="价格" width="110" align="center">
          <template #default="scope">
            <span v-if="Number(scope.row.spec_type) === 1">¥{{ scope.row.price }} 起</span>
            <span v-else>¥{{ scope.row.price }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="80" align="center" />
        <el-table-column prop="display_sales" label="展示销量" width="90" align="center">
          <template #default="scope">
            <el-tooltip :content="`真实销量 ${scope.row.sales} + 虚拟 ${scope.row.virtual_sales || 0}`" placement="top" :show-after="300">
              <span>{{ scope.row.display_sales }}</span>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column prop="display_view_count" label="展示浏览量" width="100" align="center">
          <template #default="scope">
            <el-tooltip :content="`真实浏览量 ${scope.row.view_count} + 虚拟 ${scope.row.virtual_view_count || 0}`" placement="top" :show-after="300">
              <span>{{ scope.row.display_view_count }}</span>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
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
        <el-table-column label="操作" width="220" fixed="right" align="center">
          <template #default="scope">
            <a v-if="canAudit(scope.row)" @click="handleAudit(scope.row)">审核</a>
            <el-divider v-if="canAudit(scope.row)" direction="vertical" />
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="openStatsDialog(scope.row)">虚拟统计</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
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
    </div>

    <!-- 虚拟统计弹窗 -->
    <el-dialog
      v-model="statsDialogVisible"
      title="虚拟统计维护"
      width="420px"
      destroy-on-close
    >
      <el-form label-width="100px" class="stats-form">
        <el-form-item label="虚拟销量">
          <el-input-number v-model="statsForm.virtual_sales" :min="0" :precision="0" class="form-width" />
          <div class="form-tip">展示基数=真实销量 + 虚拟销量</div>
        </el-form-item>
        <el-form-item label="虚拟浏览量">
          <el-input-number v-model="statsForm.virtual_view_count" :min="0" :precision="0" class="form-width" />
          <div class="form-tip">展示基数=真实浏览量 + 虚拟浏览量</div>
        </el-form-item>
        <div class="stats-tip">仅前台展示用，不参与库存、结算与佣金统计</div>
      </el-form>
      <template #footer>
        <span class="stats-footer">
          <el-button @click="statsDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="statsSaving" @click="submitStats">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 商户商品审核弹窗 -->
    <el-dialog
      v-model="auditDialogVisible"
      title="审核商户商品"
      width="520px"
      destroy-on-close
    >
      <el-form :model="auditForm" label-width="90px" class="audit-form">
        <el-form-item label="商品名称">
          <span class="audit-product-name">{{ auditTarget.name || '-' }}</span>
        </el-form-item>
        <el-form-item label="所属商户">
          <span>{{ auditTarget.merchant_name || '-' }}</span>
        </el-form-item>
        <el-form-item label="审核结果">
          <el-radio-group v-model="auditForm.audit_status">
            <el-radio :label="1">审核通过</el-radio>
            <el-radio :label="2">审核驳回</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="stats-footer">
          <el-button @click="auditDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="auditSaving" @click="submitAudit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 批量采集上传弹窗 -->
    <el-dialog
      v-model="batchUploadVisible"
      title="批量采集"
      width="560px"
      destroy-on-close
      @closed="handleBatchUploadClosed"
    >
      <el-upload
        ref="batchUploadRef"
        drag
        accept=".xlsx,.xls,.csv"
        :limit="1"
        :auto-upload="false"
        :on-change="handleBatchFileChange"
        :on-remove="handleBatchFileRemove"
        :on-exceed="handleBatchExceed"
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">将表格拖到此处，或<em>点击选择文件</em></div>
      </el-upload>
      <div v-if="!batchUploadFile" class="batch-template-tip">
        还没有模板？<a class="template-link" @click="handleDownloadTemplate">下载导入模板</a>
      </div>
      <div class="batch-tip">
        表格首列填写商品链接（支持淘宝/京东链接或淘宝商品ID），单次最多 1000 条，重复链接自动跳过
      </div>
      <template #footer>
        <span class="stats-footer">
          <el-button @click="batchUploadVisible = false">取 消</el-button>
          <el-button type="primary" :loading="batchImporting" :disabled="!batchUploadFile" @click="submitBatchImport">开始采集</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 批量采集任务抽屉 -->
    <el-drawer
      v-model="taskDrawerVisible"
      title="批量采集任务"
      size="72%"
      destroy-on-close
    >
      <div class="task-toolbar">
        <el-select v-model="taskFilter.status" placeholder="采集状态" clearable class="task-filter-select" @change="handleTaskFilterChange">
          <el-option label="待采集" :value="0" />
          <el-option label="采集中" :value="1" />
          <el-option label="采集成功" :value="2" />
          <el-option label="采集失败" :value="3" />
        </el-select>
        <el-select v-model="taskFilter.publish_status" placeholder="上架状态" clearable class="task-filter-select" @change="handleTaskFilterChange">
          <el-option label="未上架" :value="0" />
          <el-option label="上架中" :value="1" />
          <el-option label="已上架" :value="2" />
          <el-option label="上架失败" :value="3" />
        </el-select>
        <div class="task-toolbar__spacer" />
        <el-button @click="fetchTasks()">刷新</el-button>
        <el-button :loading="retryFailedLoading" @click="handleRetryFailed">失败重试</el-button>
        <el-button type="primary" :disabled="!taskSelection.length" @click="openCompleteDialog(taskSelection.map(item => item.id))">补全并上架</el-button>
        <el-button type="danger" plain :disabled="!taskSelection.length" :loading="batchDeleting" @click="handleBatchDelete">批量删除</el-button>
      </div>
      <el-table
        v-loading="taskLoading"
        :data="taskList"
        row-key="id"
        class="task-table"
        @selection-change="handleTaskSelectionChange"
      >
        <el-table-column type="selection" width="46" :selectable="canSelectTask" />
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="商品标题" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ row.title || row.source_url }}</template>
        </el-table-column>
        <el-table-column label="采集状态" width="96">
          <template #default="{ row }">
            <el-tag :type="collectStatusTagType(row.status)" size="small">{{ collectStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="数据完整度" width="150" show-overflow-tooltip>
          <template #default="{ row }">
            <template v-if="row.status === 2">
              <el-tag :type="row.is_complete === 1 ? 'success' : 'warning'" size="small">
                {{ row.is_complete === 1 ? '数据齐全' : `缺少${row.missing_fields || '数据'}` }}
              </el-tag>
            </template>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="库商品" width="72" align="center">
          <template #default="{ row }">{{ row.library_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="上架状态" width="96">
          <template #default="{ row }">
            <el-tag :type="publishStatusTagType(row.publish_status)" size="small">{{ publishStatusText(row.publish_status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="失败原因" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ publishFailText(row) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="196" fixed="right" align="center">
          <template #default="{ row }">
            <a v-if="canSelectTask(row)" @click="openCompleteDialog([row.id])">补全并上架</a>
            <el-divider v-if="canSelectTask(row)" direction="vertical" />
            <a v-if="Number(row.status) === 3" @click="handleRetryTask(row)">重试</a>
            <el-divider v-if="Number(row.status) === 3" direction="vertical" />
            <a class="danger-link" @click="handleDeleteTask(row)">删除</a>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap task-pagination">
        <el-pagination
          v-if="taskTotal > 0"
          v-model:current-page="taskFilter.page"
          v-model:page-size="taskFilter.limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="taskTotal"
          @size-change="handleTaskSizeChange"
          @current-change="handleTaskPageChange"
        />
      </div>
    </el-drawer>

    <!-- 批量补全弹窗 -->
    <el-dialog
      v-model="completeDialogVisible"
      title="补全并上架"
      width="500px"
      destroy-on-close
    >
      <el-alert
        :title="`将为选中的 ${completeTaskIds.length} 个采集任务补全分类、品牌和运费，保存后立即加入上架队列`"
        type="info"
        :closable="false"
        class="complete-alert"
      />
      <el-form label-width="90px" class="complete-form">
        <el-form-item label="商品分类" required>
          <el-cascader
            v-model="completeCategoryPath"
            :options="categoryOptions"
            placeholder="请选择平台分类"
            class="complete-width"
            @change="handleCompleteCategory"
          />
        </el-form-item>
        <el-form-item label="商品品牌" required>
          <el-select v-model="completeForm.brand_id" placeholder="请选择商品品牌" class="complete-width">
            <el-option v-for="item in brandOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="运费模板">
          <el-select v-model="completeForm.freight_template_id" placeholder="不包邮实物商品售卖前必须配置" clearable class="complete-width">
            <el-option v-for="item in freightOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="stats-footer">
          <el-button @click="completeDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="completeSaving" @click="submitComplete">补全并上架</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { UploadFilled } from '@element-plus/icons-vue'
import {
  getStoreProductList,
  deleteStoreProduct,
  setStoreProductStatus,
  setStoreProductVirtualStats,
  auditStoreProduct,
  getProductCategoryTree,
  getBrandOptions,
  getFreightOptions,
  getCollectorConfig,
  importBatchCollect,
  getBatchCollectTasks,
  downloadBatchCollectTemplate,
  publishBatchCollect,
  completeBatchCollect,
  retryBatchCollectTask,
  retryFailedBatchCollectTasks,
  deleteBatchCollectTask,
  deleteBatchCollectTasks
} from '@/api/product'

defineOptions({
  name: 'ProductList'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const router = useRouter()

const loading = ref(false)
const list = ref([])
const total = ref(0)

const categoryOptions = ref([])
const brandOptions = ref([])

const categoryPath = ref([])
const createTimeRange = ref([])
const activeTab = ref('all')
const isMerchantTab = computed(() => activeTab.value === 'merchant')
const showMerchantColumns = computed(() => ['merchant', 'pending'].includes(activeTab.value))

const statsDialogVisible = ref(false)
const statsSaving = ref(false)
const activeStatsRow = ref(0)
const statsForm = reactive({ virtual_sales: 0, virtual_view_count: 0 })
const auditDialogVisible = ref(false)
const auditSaving = ref(false)
const auditTarget = ref({})
const auditForm = reactive({ audit_status: 1 })

// ==================== 批量采集 ====================
const collectorEnabled = ref(false)
const batchUploadVisible = ref(false)
const batchUploadRef = ref(null)
const batchUploadFile = ref(null)
const batchImporting = ref(false)
const retryFailedLoading = ref(false)
const batchDeleting = ref(false)
const taskDrawerVisible = ref(false)
const taskLoading = ref(false)
const taskList = ref([])
const taskTotal = ref(0)
const taskSelection = ref([])
const taskFilter = reactive({ status: '', publish_status: '', page: 1, limit: 10 })
const templateDownloading = ref(false)
const completeDialogVisible = ref(false)
const completeSaving = ref(false)
const completeTaskIds = ref([])
const completeCategoryPath = ref([])
const freightOptions = ref([])
const completeForm = reactive({ category_id: 0, brand_id: '', freight_template_id: '' })

const searchForm = reactive({
  name: '',
  product_code: '',
  category_id: 0,
  brand_id: '',
  spec_type: '',
  goods_type: 0,
  status: '',
  audit_status: '',
  source_type: 'all',
  create_time: '',
  page: 1,
  limit: 20
})

function specTypeText(type) {
  const map = { 0: '单规格', 1: '多规格' }
  return map[Number(type)] || '-'
}

function goodsTypeText(type) {
  const map = { 1: '实物', 2: '虚拟', 3: '内容' }
  return map[Number(type)] || '-'
}

function goodsTypeTagType(type) {
  const map = { 1: 'info', 2: 'success', 3: 'warning' }
  return map[Number(type)] || 'info'
}

function auditStatusText(status) {
  const map = { 0: '待审核', 1: '审核通过', 2: '审核驳回' }
  return map[Number(status)] || '-'
}

function auditStatusTagType(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'danger' }
  return map[Number(status)] || 'info'
}

function getList() {
  loading.value = true
  const params = { ...searchForm }
  params.source_type = activeTab.value
  if (params.category_id === 0) delete params.category_id
  if (params.brand_id === '') delete params.brand_id
  if (params.spec_type === '') delete params.spec_type
  if (params.goods_type === 0) delete params.goods_type
  if (params.status === '') delete params.status
  if (!isMerchantTab.value || params.audit_status === '') delete params.audit_status
  if (params.create_time === '') delete params.create_time
  getStoreProductList(params).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function loadOptions() {
  getProductCategoryTree({ owner_id: 0 }).then(res => {
    categoryOptions.value = res.data || []
  }).catch(() => {})
  getBrandOptions().then(res => { brandOptions.value = res.data || [] }).catch(() => {})
}

// ==================== 批量采集 ====================

function loadCollectorEnabled() {
  getCollectorConfig().then(res => {
    collectorEnabled.value = Number((res.data || {}).enabled) === 1
  }).catch(() => {
    collectorEnabled.value = false
  })
}

function handleBatchFileChange(file) {
  batchUploadFile.value = file.raw || null
}

function handleBatchFileRemove() {
  batchUploadFile.value = null
}

function handleBatchExceed() {
  message.warning('一次只能上传一个表格文件，请先移除已选文件')
}

function handleBatchUploadClosed() {
  batchUploadFile.value = null
  batchUploadRef.value?.clearFiles()
}

/**
 * 下载导入模板（接口返回 blob，拦截器对 blob 返回原始 axios 响应）。
 */
function handleDownloadTemplate() {
  templateDownloading.value = true
  downloadBatchCollectTemplate().then(res => {
    const blob = res.data instanceof Blob ? res.data : new Blob([res.data])
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = '批量采集导入模板.xlsx'
    link.click()
    URL.revokeObjectURL(url)
  }).catch(() => {}).finally(() => {
    templateDownloading.value = false
  })
}

function submitBatchImport() {
  if (!batchUploadFile.value) {
    message.warning('请先选择表格文件')
    return
  }
  batchImporting.value = true
  const formData = new FormData()
  formData.append('file', batchUploadFile.value)
  importBatchCollect(formData).then(res => {
    const data = res.data || {}
    const parts = [`已加入采集队列 ${data.queued_count || 0} 条`]
    if (data.duplicate_count > 0) parts.push(`跳过重复 ${data.duplicate_count} 条`)
    if (data.invalid_count > 0) parts.push(`无效 ${data.invalid_count} 行`)
    message.success(parts.join('，'))
    batchUploadVisible.value = false
    openTaskDrawer()
  }).catch(() => {}).finally(() => {
    batchImporting.value = false
  })
}

/**
 * 一键重试全部采集失败任务：后端按序号逐秒延迟入队，完成后刷新当前任务列表。
 */
function handleRetryFailed() {
  retryFailedLoading.value = true
  retryFailedBatchCollectTasks().then(res => {
    const data = res.data || {}
    if (!data.queued_count) {
      message.warning('暂无采集失败的任务')
      return
    }
    message.success(`已将 ${data.queued_count} 条失败任务重新加入采集队列，将逐秒错峰执行`)
    fetchTasks()
  }).catch(() => {}).finally(() => {
    retryFailedLoading.value = false
  })
}

function openTaskDrawer() {
  taskFilter.status = ''
  taskFilter.publish_status = ''
  taskFilter.page = 1
  taskDrawerVisible.value = true
  fetchTasks()
}

function fetchTasks() {
  taskLoading.value = true
  getBatchCollectTasks({
    page: taskFilter.page,
    limit: taskFilter.limit,
    status: taskFilter.status,
    publish_status: taskFilter.publish_status
  }).then(res => {
    const data = res.data || {}
    taskList.value = data.list || []
    taskTotal.value = data.total || 0
  }).catch(() => {}).finally(() => {
    taskLoading.value = false
  })
}

function handleTaskFilterChange() {
  taskFilter.page = 1
  fetchTasks()
}

function handleTaskSizeChange(size) {
  taskFilter.limit = size
  taskFilter.page = 1
  fetchTasks()
}

function handleTaskPageChange(page) {
  taskFilter.page = page
  fetchTasks()
}

function handleTaskSelectionChange(rows) {
  taskSelection.value = rows || []
}

function collectStatusText(status) {
  const map = { 0: '待采集', 1: '采集中', 2: '采集成功', 3: '采集失败' }
  return map[Number(status)] || '-'
}

function collectStatusTagType(status) {
  const map = { 0: 'info', 1: 'warning', 2: 'success', 3: 'danger' }
  return map[Number(status)] || 'info'
}

function publishStatusText(status) {
  const map = { 0: '未上架', 1: '上架中', 2: '已上架', 3: '上架失败' }
  return map[Number(status)] || '-'
}

function publishStatusTagType(status) {
  const map = { 0: 'info', 1: 'warning', 2: 'success', 3: 'danger' }
  return map[Number(status)] || 'info'
}

function publishFailText(row) {
  if (Number(row.publish_status) === 3) return row.publish_error || '-'
  if (Number(row.status) === 3) return row.error_msg || '-'
  return '-'
}

/**
 * 可勾选并补全上架：采集完成，且不在上架中或已上架。
 */
function canSelectTask(row) {
  return Number(row.status) === 2 && ![1, 2].includes(Number(row.publish_status))
}

function openCompleteDialog(taskIds) {
  const ids = (taskIds || []).filter(id => Number(id) > 0)
  if (!ids.length) {
    message.warning('请先勾选采集成功的任务')
    return
  }
  completeTaskIds.value = ids
  completeCategoryPath.value = []
  completeForm.category_id = 0
  completeForm.brand_id = ''
  completeForm.freight_template_id = ''
  completeDialogVisible.value = true
  if (!freightOptions.value.length) {
    getFreightOptions().then(res => {
      freightOptions.value = res.data || []
    }).catch(() => {})
  }
}

function handleCompleteCategory(val) {
  completeForm.category_id = val && val.length ? val[val.length - 1] : 0
}

function submitComplete() {
  if (completeForm.category_id <= 0) {
    message.warning('请选择平台分类')
    return
  }
  if (completeForm.brand_id === '') {
    message.warning('请选择商品品牌')
    return
  }
  const ids = [...completeTaskIds.value]
  completeSaving.value = true
  completeBatchCollect(ids, {
    category_id: completeForm.category_id,
    brand_id: completeForm.brand_id,
    freight_template_id: completeForm.freight_template_id || 0
  }).then(() => publishBatchCollect(ids)).then(res => {
    const data = res.data || {}
    const queued = data.queued_count || 0
    const skipped = data.skipped_count || 0
    if (!queued && skipped) {
      message.warning(`已补全，但 ${skipped} 条未能上架`)
    } else if (skipped) {
      message.success(`已补全并加入上架队列 ${queued} 条，${skipped} 条未上架`)
    } else {
      message.success(`已补全并加入上架队列 ${queued} 条`)
    }
    completeDialogVisible.value = false
    fetchTasks()
  }).catch(() => {}).finally(() => {
    completeSaving.value = false
  })
}

function handleRetryTask(row) {
  retryBatchCollectTask(row.id).then(res => {
    message.success(res.message || '已重新加入采集队列')
    fetchTasks()
  }).catch(() => {})
}

function handleDeleteTask(row) {
  confirm('确定删除该采集任务吗？已生成的库商品不受影响。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteBatchCollectTask(row.id).then(res => {
      message.success(res.message || '删除成功')
      fetchTasks()
    }).catch(() => {})
  }).catch(() => {})
}

/**
 * 批量删除勾选的采集任务：后端自动跳过上架中的任务，完成后刷新列表（勾选随数据刷新自动清空）。
 */
function handleBatchDelete() {
  const ids = taskSelection.value.map(item => item.id)
  if (!ids.length) {
    message.warning('请先勾选要删除的任务')
    return
  }
  confirm(`确定删除选中的 ${ids.length} 个采集任务吗？上架中的任务会自动跳过，已生成的库商品不受影响。`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    batchDeleting.value = true
    deleteBatchCollectTasks(ids).then(res => {
      const data = res.data || {}
      const skipped = data.skipped_ids || []
      const tip = skipped.length ? `已删除 ${data.deleted_count || 0} 条，${skipped.length} 条上架中已跳过` : (res.message || '删除成功')
      message.success(tip)
      fetchTasks()
    }).catch(() => {}).finally(() => {
      batchDeleting.value = false
    })
  }).catch(() => {})
}

function handleCategorySearch(val) {
  searchForm.category_id = val && val.length ? val[val.length - 1] : 0
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleTabChange(name) {
  activeTab.value = name
  searchForm.page = 1
  searchForm.source_type = activeTab.value
  searchForm.audit_status = ''
  getList()
}

function handleReset() {
  Object.assign(searchForm, {
    name: '',
    product_code: '',
    category_id: 0,
    brand_id: '',
    spec_type: '',
    goods_type: 0,
    status: '',
    audit_status: '',
    source_type: activeTab.value,
    create_time: '',
    page: 1,
    limit: 20
  })
  categoryPath.value = []
  createTimeRange.value = []
  getList()
}

function handleSizeChange(size) {
  searchForm.limit = size
  searchForm.page = 1
  getList()
}

function handlePageChange(page) {
  searchForm.page = page
  getList()
}

function handleCreate() {
  router.push('/product/list/create')
}

function handleEdit(row) {
  router.push(`/product/list/create?id=${row.id}`)
}

function handleDelete(id) {
  confirm('确定要删除该商品吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteStoreProduct(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setStoreProductStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function canAudit(row) {
  return Number(row.merchant_id) > 0 && Number(row.audit_status) === 0
}

function handleAudit(row) {
  auditTarget.value = row
  auditForm.audit_status = 1
  auditDialogVisible.value = true
}

function submitAudit() {
  auditSaving.value = true
  auditStoreProduct(auditTarget.value.id, { audit_status: auditForm.audit_status }).then(res => {
    message.success(res.message || '审核操作成功')
    auditDialogVisible.value = false
    getList()
  }).catch(() => {}).finally(() => {
    auditSaving.value = false
  })
}

function openStatsDialog(row) {
  activeStatsRow.value = row.id
  statsForm.virtual_sales = Number(row.virtual_sales) || 0
  statsForm.virtual_view_count = Number(row.virtual_view_count) || 0
  statsDialogVisible.value = true
}

function submitStats() {
  statsSaving.value = true
  setStoreProductVirtualStats(activeStatsRow.value, { ...statsForm }).then(res => {
    message.success(res.message || '修改成功')
    statsDialogVisible.value = false
    getList()
  }).catch(() => {}).finally(() => {
    statsSaving.value = false
  })
}

onBeforeMount(() => {
  getList()
  loadOptions()
  loadCollectorEnabled()
})
</script>

<style lang="scss" scoped>
.product-list-page {
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

.page-curator-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.batch-template-tip {
  margin-top: 10px;
  font-size: 13px;
  color: var(--ds-on-surface-variant);

  .template-link {
    color: var(--ds-primary);
    cursor: pointer;
    margin: 0 4px;
  }
}

.batch-tip {
  margin-top: 10px;
  font-size: 12px;
  line-height: 1.6;
  color: var(--ds-on-surface-variant);
}

.task-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;

  .task-filter-select {
    width: 160px;
  }

  .task-toolbar__spacer {
    flex: 1;
  }
}

.task-table {
  .task-error {
    font-size: 12px;
    color: #f56c6c;
    margin-top: 2px;
  }
}

.complete-alert {
  margin-bottom: 14px;
}

.complete-form {
  .complete-width {
    width: 100%;
  }
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
  max-width: 560px;
}

.category-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.product-source-tabs-wrap {
  margin-bottom: 16px;
}

.product-source-tabs {
  :deep(.el-tabs__header) {
    margin: 0;
  }
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--ds-primary-container);
    border-color: var(--ds-primary-container);
  }
}

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
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

.search-input { width: 160px; }
.search-select-sm { width: 100px; }
.search-select-lg { width: 180px; }
.search-date { width: 240px; }

.cover-img {
  width: 40px;
  height: 40px;
  border-radius: 6px;
}

.danger-link {
  color: #f56c6c;

  &:hover {
    color: #f56c6c;
    opacity: 0.8;
  }
}

.empty-text {
  color: #909399;
  font-size: 12px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

.task-pagination {
  flex-wrap: wrap;
  row-gap: 8px;
}

.stats-form {
  padding: 8px 0;
}

.audit-form {
  padding: 8px 0;
}

.audit-product-name {
  display: inline-block;
  max-width: 360px;
  color: var(--ds-on-surface);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.form-width {
  width: 100%;

  :deep(.el-input-number) {
    width: 100%;

    .el-input__inner {
      text-align: left;
    }
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
}

.stats-tip {
  padding: 8px 12px;
  margin-top: 8px;
  border-radius: 6px;
  background: rgba(243, 65, 0, 0.06);
  color: #f34100;
  font-size: 12px;
}

.stats-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
