<template>
  <div>
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
            <el-option label="商品" value="product" />
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
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 表格区域 -->
    <el-card shadow="never">
      <el-button type="primary" icon="el-icon-plus" @click="handleAdd">添加挂载商品</el-button>

      <el-table
        v-loading="loading"
        :data="goodsList"
        class="mt14"
      >
        <el-table-column prop="id" label="ID" width="60" align="center" />
        <el-table-column label="链接编号" width="90" align="center">
          <template #default="scope">
            <el-tag type="success">{{ scope.row.link_no }}号链接</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="封面" width="70" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.goods_cover"
              :src="scope.row.goods_cover"
              :preview-src-list="[scope.row.goods_cover]"
              class="goods-cover"
              fit="cover"
            />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="goods_title" label="商品标题" min-width="140" show-overflow-tooltip />
        <el-table-column label="商品类型" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getGoodsTypeTag(scope.row.goods_type)">
              {{ getGoodsTypeLabel(scope.row.goods_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="100" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ scope.row.goods_price }}</span>
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
        <el-table-column prop="create_time" label="添加时间" width="160" align="center" />
        <el-table-column label="操作" width="130" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleEdit(scope.row.id)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">移除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <!-- 内容选择弹窗（单选，复用公共组件） -->
    <content-picker
      v-model:visible="articlePickerVisible"
      type="article"
      :multiple="false"
      @select="confirmSelectArticle"
    />

    <!-- 专题选择弹窗（单选，复用公共组件） -->
    <content-picker
      v-model:visible="specialPickerVisible"
      type="special"
      :multiple="false"
      @select="confirmSelectSpecial"
    />

    <!-- 商品选择弹窗（单选，仅平台商品） -->
    <product-picker
      v-model:visible="productPickerVisible"
      @select="confirmSelectProduct"
    />

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
            <el-radio label="product">商品</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 选择专题（仅 special 类型显示） -->
        <el-form-item v-if="editForm.goods_type === 'special'" label="选择专题" prop="goods_id">
          <div class="article-picker">
            <el-input
              v-model="selectedSpecialTitle"
              placeholder="请点击右侧按钮选择专题"
              readonly
              class="article-picker-input"
            />
            <el-button type="primary" plain icon="el-icon-search" @click="openSpecialPicker">选择</el-button>
          </div>
        </el-form-item>

        <!-- 选择单篇内容（仅 article 类型显示） -->
        <el-form-item v-if="editForm.goods_type === 'article'" label="选择内容" prop="goods_id">
          <div class="article-picker">
            <el-input
              v-model="selectedArticleTitle"
              placeholder="请点击右侧按钮选择单篇内容"
              readonly
              class="article-picker-input"
            />
            <el-button type="primary" plain icon="el-icon-search" @click="openArticlePicker">选择</el-button>
          </div>
        </el-form-item>

        <!-- 选择商品（仅 product 类型显示，只能选平台商品） -->
        <el-form-item v-if="editForm.goods_type === 'product'" label="选择商品" prop="goods_id">
          <div class="article-picker">
            <el-input
              v-model="selectedProductTitle"
              placeholder="请点击右侧按钮选择商品"
              readonly
              class="article-picker-input"
            />
            <el-button type="primary" plain icon="el-icon-search" @click="openProductPicker">选择</el-button>
          </div>
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
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getLiveGoodsList,
  getLiveGoodsDetail,
  createLiveGoods,
  updateLiveGoods,
  deleteLiveGoods,
  showLiveGoods,
  getRoomList
} from '@/api/live'
import ContentPicker from '@/components/ContentPicker/index.vue'
import ProductPicker from '@/components/ProductPicker/index.vue'

defineOptions({ name: 'LiveGoodsManage' })

const { proxy } = getCurrentInstance()

const searchFormRef = ref(null)
const editFormRef = ref(null)
const loading = ref(false)
const goodsList = ref([])
const roomOptions = ref([])
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
const selectedArticleTitle = ref('')
const articlePickerVisible = ref(false)
const selectedSpecialTitle = ref('')
const specialPickerVisible = ref(false)
const selectedProductTitle = ref('')
const productPickerVisible = ref(false)

const editRules = {
  live_room_id: [{ required: true, message: '请选择直播间', trigger: 'change' }],
  goods_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
  goods_id: [{
    validator: (rule, value, callback) => {
      if (editForm.goods_type === 'special' && !value) {
        callback(new Error('请选择专题'))
      } else if (editForm.goods_type === 'article' && !value) {
        callback(new Error('请选择单篇内容'))
      } else if (editForm.goods_type === 'product' && !value) {
        callback(new Error('请选择商品'))
      } else {
        callback()
      }
    },
    trigger: 'change'
  }],
  goods_title: [{ required: true, message: '请输入商品标题', trigger: 'blur' }],
  goods_price: [{ required: true, message: '请输入展示价格', trigger: 'blur' }]
}

function createSearchForm() {
  return {
    live_room_id: '',
    goods_type: '',
    status: ''
  }
}

function createEditForm() {
  return {
    live_room_id: '',
    goods_type: 'special',
    goods_id: 0,
    goods_title: '',
    goods_cover: '',
    goods_price: 0,
    sort: 0,
    status: 1
  }
}

function loadRoomOptions() {
  getRoomList({ page: 1, limit: 200 }).then(res => {
    roomOptions.value = res.data.list || res.data || []
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
  }).catch(() => {
    goodsList.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getGoodsList()
}

function handleReset() {
  searchFormRef.value?.resetFields()
  Object.assign(searchForm, createSearchForm())
  handleSearch()
}

function handleAdd() {
  dialogTitle.value = '添加挂载商品'
  editId.value = 0
  Object.assign(editForm, createEditForm())
  selectedArticleTitle.value = ''
  selectedSpecialTitle.value = ''
  selectedProductTitle.value = ''
  dialogVisible.value = true
}

function handleEdit(id) {
  getLiveGoodsDetail(id).then(res => {
    const d = res.data || {}
    editId.value = d.id
    Object.assign(editForm, createEditForm(), {
      live_room_id: d.live_room_id,
      goods_type: d.goods_type || 'special',
      goods_id: d.goods_id,
      goods_title: d.goods_title,
      goods_cover: d.goods_cover || '',
      goods_price: d.goods_price,
      sort: d.sort || 0,
      status: d.status
    })
    selectedArticleTitle.value = d.goods_type === 'article' ? d.goods_title : ''
    selectedSpecialTitle.value = d.goods_type === 'special' ? d.goods_title : ''
    selectedProductTitle.value = d.goods_type === 'product' ? d.goods_title : ''
    dialogTitle.value = '编辑挂载商品'
    dialogVisible.value = true
  })
}

function handleGoodsTypeChange() {
  editForm.goods_id = 0
  editForm.goods_title = ''
  editForm.goods_cover = ''
  editForm.goods_price = 0
  selectedArticleTitle.value = ''
  selectedSpecialTitle.value = ''
  selectedProductTitle.value = ''
}

function openSpecialPicker() {
  specialPickerVisible.value = true
}

function confirmSelectSpecial(row) {
  editForm.goods_id = row.id
  editForm.goods_title = row.title
  editForm.goods_cover = row.cover || ''
  editForm.goods_price = row.price || 0
  selectedSpecialTitle.value = row.title
  specialPickerVisible.value = false
  editFormRef.value?.validateField('goods_id')
}

function openArticlePicker() {
  articlePickerVisible.value = true
}

function confirmSelectArticle(row) {
  editForm.goods_id = row.id
  editForm.goods_title = row.title
  editForm.goods_cover = row.cover || ''
  editForm.goods_price = row.price || 0
  selectedArticleTitle.value = row.title
  articlePickerVisible.value = false
  editFormRef.value?.validateField('goods_id')
}

function openProductPicker() {
  productPickerVisible.value = true
}

/**
 * 回填选中的商品。
 *
 * 商品来源由 ProductPicker 限定为平台商品（platform_only=1）；
 * 标题、封面、价格默认取商品自身数据，标题仍允许在表单内自定义修改。
 *
 * @param {Object} row 商品选项行数据。
 * @returns {void}
 */
function confirmSelectProduct(row) {
  editForm.goods_id = row.id
  editForm.goods_title = row.name
  editForm.goods_cover = row.cover || ''
  editForm.goods_price = row.price || 0
  selectedProductTitle.value = row.name
  productPickerVisible.value = false
  editFormRef.value?.validateField('goods_id')
}

/**
 * 挂载商品类型文案。
 *
 * @param {string} goodsType 商品类型：article=单篇内容、special=付费专题、product=商品
 * @returns {string}
 */
function getGoodsTypeLabel(goodsType) {
  const labelMap = { article: '单篇内容', special: '付费专题', product: '商品' }
  return labelMap[goodsType] || goodsType || '-'
}

/**
 * 挂载商品类型标签颜色。
 *
 * @param {string} goodsType 商品类型：article/special/product
 * @returns {string}
 */
function getGoodsTypeTag(goodsType) {
  const tagMap = { article: 'primary', special: 'warning', product: 'success' }
  return tagMap[goodsType] || 'info'
}

async function handleSubmit() {
  if (!editFormRef.value) return
  const valid = await editFormRef.value.validate().catch(() => false)
  if (!valid) return
  submitLoading.value = true
  const api = editId.value
    ? updateLiveGoods(editId.value, editForm)
    : createLiveGoods(editForm)
  api.then(() => {
    proxy?.$message?.success(editId.value ? '编辑成功' : '添加成功')
    dialogVisible.value = false
    getGoodsList()
  }).finally(() => {
    submitLoading.value = false
  })
}

function handleDialogClose() {
  editFormRef.value?.resetFields()
}

function handleDelete(id) {
  proxy.$confirm('确定要移除该挂载商品吗？', '提示', { type: 'warning' }).then(() => {
    deleteLiveGoods(id).then(() => {
      proxy?.$message?.success('移除成功')
      getGoodsList()
    })
  })
}

function handleStatusChange(row) {
  showLiveGoods(row.id, row.status).then(() => {
    proxy?.$message?.success('状态更新成功')
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

onBeforeMount(() => {
  loadRoomOptions()
  getGoodsList()
})
</script>

<style lang="scss" scoped>
.search-card {
  margin-bottom: 14px;
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
  color: #c0c4cc;
  font-size: 12px;
}
.price-text {
  color: #e6a23c;
  font-weight: 600;
}
.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.danger-link {
  color: #f56c6c;
}
a {
  color: #409eff;
  cursor: pointer;
  font-size: 13px;
  &:hover {
    opacity: 0.8;
  }
}
.tips-text {
  margin-left: 8px;
  color: #909399;
  font-size: 12px;
}
.article-picker {
  display: flex;
  align-items: center;
  gap: 8px;
  .article-picker-input {
    width: 260px;
  }
}
.article-picker-dialog {
  // 已由 ContentPicker 公共组件替代，此处保留空规则以免影响其他引用
}
</style>
