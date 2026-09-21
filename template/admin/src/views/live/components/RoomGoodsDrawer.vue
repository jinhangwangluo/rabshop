<template>
  <div>
    <!-- 商品抽屉 -->
    <el-drawer
      v-model="localVisible"
      :title="`直播商品 — ${props.roomTitle}`"
      size="900px"
      :wrapper-closable="true"
      @close="handleClose"
    >
      <div class="drawer-body">
        <!-- 工具栏 -->
        <div class="drawer-filter">
          <el-select
            v-model="goodsSearch.goods_type"
            placeholder="全部类型"
            clearable

            class="drawer-search-select"
            @change="handleSearch"
          >
            <el-option label="单篇内容" value="article" />
            <el-option label="付费专题" value="special" />
            <el-option label="商品" value="product" />
          </el-select>
          <el-select
            v-model="goodsSearch.status"
            placeholder="全部状态"
            clearable

            class="drawer-search-select"
            @change="handleSearch"
          >
            <el-option label="上架" :value="1" />
            <el-option label="下架" :value="0" />
          </el-select>
          <el-button icon="el-icon-refresh" @click="loadList">刷新</el-button>
          <el-button type="primary" icon="el-icon-plus" @click="handleAdd">添加商品</el-button>
        </div>

        <!-- 商品列表 -->
        <el-table
          v-loading="loading"
          :data="goodsList"

          class="drawer-table"
        >
          <el-table-column label="链接号" width="70" align="center">
            <template #default="scope">
              <el-tag type="success" size="mini">{{ scope.row.link_no }}号</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="封面" width="60" align="center">
            <template #default="scope">
              <el-image
                v-if="scope.row.goods_cover"
                :src="scope.row.goods_cover"
                class="goods-cover-sm"
                fit="cover"
                :preview-src-list="[scope.row.goods_cover]"
              />
              <span v-else class="text-gray">—</span>
            </template>
          </el-table-column>
          <el-table-column prop="goods_title" label="商品标题" min-width="130" show-overflow-tooltip />
          <el-table-column label="类型" width="90" align="center">
            <template #default="scope">
              <el-tag
                :type="GOODS_TYPE_TAG[scope.row.goods_type] || 'info'"
                size="mini"
              >{{ GOODS_TYPE_LABEL[scope.row.goods_type] || scope.row.goods_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="价格" width="90" align="center">
            <template #default="scope">
              <span class="price-text">¥{{ scope.row.goods_price }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="sort" label="排序" width="60" align="center" />
          <el-table-column label="状态" width="80" align="center">
            <template #default="scope">
              <el-switch
                v-model="scope.row.status"
                :active-value="1"
                :inactive-value="0"
                size="mini"
                @change="handleStatusChange(scope.row)"
              />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100" fixed="right" align="center">
            <template #default="scope">
              <a @click="handleEdit(scope.row.id)">编辑</a>
              <el-divider direction="vertical" />
              <a class="danger-link" @click="handleDelete(scope.row.id)">移除</a>
            </template>
          </el-table-column>
        </el-table>

        <!-- 分页 -->
        <div class="drawer-pagination">
          <el-pagination
            :current-page="page"
            :page-sizes="[10, 20, 50]"
            :page-size="limit"
            layout="total, sizes, prev, pager, next"
            :total="total"
            small
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </div>
    </el-drawer>

    <!-- 商品新增/编辑弹窗（使用 LiveGoodsFormDialog 组件） -->
    <live-goods-form-dialog
      v-model:visible="dialogVisible"
      :title="dialogTitle"
      :live-room-id="roomId"
      :edit-id="editId"
      @success="handleFormSuccess"
    />
  </div>
</template>

<script setup>
import { getCurrentInstance, reactive, ref, watch } from 'vue'
import {
  getLiveGoodsList,
  deleteLiveGoods,
  showLiveGoods
} from '@/api/live'
import LiveGoodsFormDialog from './LiveGoodsFormDialog.vue'

defineOptions({
  name: 'RoomGoodsDrawer'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  roomId: {
    type: Number,
    default: 0
  },
  roomTitle: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:visible'])
const { proxy } = getCurrentInstance() || {}

const localVisible = ref(false)
const loading = ref(false)
const goodsList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const goodsSearch = reactive({
  goods_type: '',
  status: ''
})
const dialogVisible = ref(false)
const dialogTitle = ref('添加挂载商品')
const editId = ref(0)

const DEFAULT_GOODS_SEARCH = {
  goods_type: '',
  status: ''
}

// 挂载商品类型文案与标签颜色：article=单篇内容、special=付费专题、product=平台商品
const GOODS_TYPE_LABEL = {
  article: '单篇内容',
  special: '付费专题',
  product: '商品'
}
const GOODS_TYPE_TAG = {
  article: 'primary',
  special: 'warning',
  product: 'success'
}

watch(() => props.visible, val => {
  localVisible.value = val
  if (val) {
    page.value = 1
    Object.assign(goodsSearch, DEFAULT_GOODS_SEARCH)
    loadList()
  }
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

async function loadList() {
  if (!props.roomId) return
  loading.value = true
  try {
    const res = await getLiveGoodsList({
      live_room_id: props.roomId,
      page: page.value,
      limit: limit.value,
      ...goodsSearch
    })
    goodsList.value = res.data.list || res.data || []
    total.value = res.data.total || 0
  } catch {
    goodsList.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  loadList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  loadList()
}

function handleCurrentChange(val) {
  page.value = val
  loadList()
}

function handleClose() {
  goodsList.value = []
  total.value = 0
  emit('update:visible', false)
}

function handleAdd() {
  editId.value = 0
  dialogTitle.value = '添加挂载商品'
  dialogVisible.value = true
}

function handleEdit(id) {
  editId.value = id
  dialogTitle.value = '编辑挂载商品'
  dialogVisible.value = true
}

function handleFormSuccess() {
  loadList()
}

async function confirmAction(message, title = '提示') {
  if (!proxy?.$confirm) return false
  return proxy.$confirm(message, title, { type: 'warning' }).then(() => true).catch(() => false)
}

async function handleDelete(id) {
  const confirmed = await confirmAction('确定要移除该挂载商品吗？')
  if (!confirmed) return

  await deleteLiveGoods(id)
  proxy?.$message?.success('移除成功')
  loadList()
}

function handleStatusChange(row) {
  showLiveGoods(row.id, row.status).then(() => {
    proxy?.$message?.success('状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}
</script>

<style lang="scss" scoped>
.drawer-body {
  padding: 0 20px 20px;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.drawer-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 0 12px;
  flex-shrink: 0;
}
.drawer-search-select {
  width: 130px;
}
.drawer-table {
  flex: 1;
}
.drawer-pagination {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}
.goods-cover-sm {
  width: 44px;
  height: 32px;
  border-radius: 3px;
}
.price-text {
  color: #e6a23c;
  font-weight: 600;
}
.danger-link {
  color: #f56c6c;
}
.text-gray {
  color: #c0c4cc;
  font-size: 12px;
}
a {
  color: #409eff;
  cursor: pointer;
  font-size: 13px;
  &:hover {
    opacity: 0.8;
  }
}
</style>
