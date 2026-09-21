<template>
  <div class="reply-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">关键字回复</h1>
        <p class="page-curator-subtitle">
          管理微信公众号关键字自动回复规则，支持文字、图片、图文、声音等多种消息类型。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleCreate"
      >
        添加关键字
      </el-button>
    </header>

    <div class="reply-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchForm"
        :model="listQuery"
        inline
        class="search-form reply-search-form"
        @submit.prevent
      >
        <el-form-item label="回复类型">
          <el-select
            v-model="listQuery.type"
            placeholder="请选择"
            clearable
            class="search-select-sm"
            @change="handleFilter"
          >
            <el-option label="全部" value="" />
            <el-option label="文字消息" value="text" />
            <el-option label="图片消息" value="image" />
            <el-option label="图文消息" value="news" />
            <el-option label="声音消息" value="voice" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键字">
          <el-input
            v-model="listQuery.key"
            placeholder="请输入关键字"
            clearable
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleFilter"
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleFilter"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleRefresh"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="listLoading"
        :data="list"
        class="reply-curator-table"
        row-key="id"
      >
        <el-table-column label="关键字" prop="key" align="center" min-width="150" />
        <el-table-column label="回复类型" align="center" width="120">
          <template #default="{row}">
            <span>{{ typeFilter(row.type) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" align="center" width="100">
          <template #default="{row}">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="{row}">
            <el-button type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="reply-curator-pagination"
          :current-page="listQuery.page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="listQuery.limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getKeywordList, deleteKeyword, changeKeywordStatus } from '@/api/wechat'

defineOptions({
  name: 'WechatReplyKeyword'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const router = useRouter()

const list = ref([])
const total = ref(0)
const listLoading = ref(true)
const listQuery = reactive({
  page: 1,
  limit: 20,
  type: undefined,
  key: undefined
})

function typeFilter(type) {
  const typeMap = {
    text: '文字消息',
    image: '图片消息',
    news: '图文消息',
    voice: '声音消息'
  }
  return typeMap[type] || type
}

function handleStatusChange(row) {
  changeKeywordStatus(row.id, { status: row.status })
    .then(() => {
      message.success('状态更新成功')
    })
    .catch(() => {
      row.status = row.status === 1 ? 0 : 1
    })
}

function getList() {
  listLoading.value = true
  getKeywordList(listQuery).then(response => {
    list.value = response.data.list
    total.value = response.data.total
    listLoading.value = false
  }).catch(() => {
    listLoading.value = false
  })
}

function handleFilter() {
  listQuery.page = 1
  getList()
}

function handleRefresh() {
  Object.assign(listQuery, {
    page: 1,
    limit: 20,
    type: undefined,
    key: undefined
  })
  getList()
}

function handleCreate() {
  router.push('/channel/wechat/reply/keyword/save')
}

function handleEdit(row) {
  router.push('/channel/wechat/reply/keyword/save/' + row.id)
}

function handleDelete(row) {
  confirm('确定删除该关键字吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteKeyword(row.id).then(() => {
      message.success('删除成功')
      getList()
    })
  }).catch(() => {})
}

function handleSizeChange(val) {
  listQuery.limit = val
  listQuery.page = 1
  getList()
}

function handleCurrentChange(val) {
  listQuery.page = val
  getList()
}

onBeforeMount(() => {
  getList()
})
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.reply-curator-page {
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

.reply-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
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

.reply-search-form {
  margin-bottom: 8px;
}

.reply-curator-table {
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

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.reply-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--ds-primary);
    }
  }
}

.search-input {
  width: 200px;
}

.search-select-sm {
  width: 140px;
}
</style>
