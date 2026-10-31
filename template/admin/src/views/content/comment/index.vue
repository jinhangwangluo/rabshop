<template>
  <div class="comment-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">内容评论管理</h1>
        <p class="page-curator-subtitle">
          查看和管理平台内容的所有评论，支持按内容、用户、时间筛选，可删除违规评论。
        </p>
      </div>
    </header>

    <div class="comment-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form comment-search-form"
        @submit.prevent
      >
        <el-form-item label="内容关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="搜索评论内容"
            clearable
            class="search-input"
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="用户昵称/手机">
          <el-input
            v-model="searchForm.nickname"
            placeholder="搜索用户昵称或手机"
            clearable
            class="search-input"
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="发布时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"

            style="width: 240px;"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 数据表格 -->
      <el-table v-loading="loading" :data="commentList" class="comment-curator-table" row-key="id">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="评论用户" width="160" align="center">
          <template #default="scope">
            <div class="user-cell">
              <el-avatar
                :src="scope.row.user && scope.row.user.avatar"
                :size="30"
                icon="el-icon-user-solid"
                class="user-avatar"
              />
              <span class="user-nickname">{{ (scope.row.user && scope.row.user.nickname) || '-' }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="所属内容" min-width="160" show-overflow-tooltip>
          <template #default="scope">
            <span class="article-title">{{ (scope.row.article && scope.row.article.title) || ('内容ID:' + scope.row.article_id) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="评论内容" min-width="200">
          <template #default="scope">
            <div class="comment-content-cell">
              <div v-if="scope.row.pid > 0" class="reply-badge">
                <el-tag size="mini" type="info">回复</el-tag>
                <span v-if="scope.row.reply_to_nickname" class="reply-to">→ {{ scope.row.reply_to_nickname }}</span>
              </div>
              <p class="comment-text">{{ scope.row.content }}</p>
              <div v-if="scope.row.images && scope.row.images.length" class="comment-images">
                <el-image
                  v-for="(img, idx) in scope.row.images"
                  :key="idx"
                  :src="img"
                  :preview-src-list="scope.row.images"
                  class="comment-thumb"
                  fit="cover"
                />
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="层级" width="80" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.pid === 0" type="primary">一级</el-tag>
            <el-tag v-else type="info">二级</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="发布时间" width="160" align="center" />
        <el-table-column label="操作" width="130" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleDetail(scope.row)">详情</a>
            <el-divider direction="vertical" />
            <a class="btn-danger-text" @click="handleDelete(scope.row)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[15, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <el-drawer
      v-model="detailVisible"
      title="评论详情"
      direction="rtl"
      size="720px"
      :wrapper-closable="true"
      custom-class="comment-detail-drawer"
      @close="handleDetailClose"
    >
      <div v-if="detailData" class="comment-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="评论ID">{{ detailData.id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="内容ID">{{ detailData.article_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="所属内容" :span="2">
            {{ (detailData.article && detailData.article.title) || ('内容ID:' + detailData.article_id) }}
          </el-descriptions-item>
          <el-descriptions-item label="用户ID">{{ detailData.user_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="用户昵称">
            {{ (detailData.user && detailData.user.nickname) || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="手机号">
            {{ (detailData.user && detailData.user.phone) || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="评论层级">
            <el-tag v-if="Number(detailData.pid || 0) === 0" type="primary">一级评论</el-tag>
            <el-tag v-else type="info">回复评论</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="父评论ID">{{ detailData.pid || '-' }}</el-descriptions-item>
          <el-descriptions-item label="回复对象">
            {{ detailData.reply_to_nickname || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="发布时间">{{ detailData.create_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="评论内容" :span="2">
            <div class="comment-detail__content">{{ detailData.content || '-' }}</div>
          </el-descriptions-item>
          <el-descriptions-item label="图片" :span="2">
            <div v-if="detailData.images && detailData.images.length" class="comment-detail__images">
              <el-image
                v-for="(img, idx) in detailData.images"
                :key="idx"
                :src="img"
                :preview-src-list="detailData.images"
                class="comment-detail__image"
                fit="cover"
              />
            </div>
            <span v-else>-</span>
          </el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getCommentList, deleteComment } from '@/api/comment'

defineOptions({
  name: 'ContentCommentList'
})

const route = useRoute()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const searchFormRef = ref(null)
const loading = ref(false)
const commentList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const detailVisible = ref(false)
const detailData = ref(null)
const routeDetailId = ref('')

const searchForm = reactive({
  keyword: '',
  nickname: '',
  create_time: ''
})

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  if (routeDetailId.value) {
    params.id = routeDetailId.value
  }
  getCommentList(params).then(res => {
    const data = res.data || {}
    commentList.value = data.list || []
    total.value = data.total || 0
    tryOpenDetailFromRoute()
  }).finally(() => {
    loading.value = false
  })
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    const previousId = routeDetailId.value
    routeDetailId.value = ''
    if (previousId) {
      detailVisible.value = false
      detailData.value = null
      page.value = 1
      getList()
      return true
    }
    return false
  }
  if (value === routeDetailId.value) {
    return false
  }

  routeDetailId.value = value
  detailVisible.value = false
  detailData.value = null
  Object.assign(searchForm, { keyword: '', nickname: '', create_time: '' })
  dateRange.value = []
  page.value = 1
  getList()
  return true
}

function tryOpenDetailFromRoute() {
  if (!routeDetailId.value || detailVisible.value) {
    return
  }

  const target = commentList.value.find(item => String(item.id) === String(routeDetailId.value))
  if (!target) {
    return
  }

  handleDetail(target)
}

function handleDateChange(val) {
  if (val && val.length === 2) {
    searchForm.create_time = `${val[0]} 00:00:00-${val[1]} 23:59:59`
  } else {
    searchForm.create_time = ''
  }
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, { keyword: '', nickname: '', create_time: '' })
  dateRange.value = []
  routeDetailId.value = ''
  page.value = 1
  getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getList()
}

function handleCurrentChange(val) {
  page.value = val
  getList()
}

function handleDetail(row) {
  detailData.value = row
  detailVisible.value = true
}

function handleDetailClose() {
  detailData.value = null
}

function handleDelete(row) {
  confirm('确定删除该评论吗？此操作不可撤销。', '提示', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteComment(row.id).then(() => {
      message.success('评论已删除')
      getList()
    })
  })
}

onBeforeMount(() => {
  if (!applyRouteDetailQuery(route.query.detail_id)) {
    getList()
  }
})
</script>

<style lang="scss" scoped>
.comment-curator-page {
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

.comment-work-surface {
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

.comment-search-form {
  margin-bottom: 8px;
}

.search-input {
  width: 180px;
}

.comment-curator-table {
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

.user-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  min-width: 0;
}

.user-avatar {
  flex-shrink: 0;
  background: var(--ds-surface-low, #f5f7fa);
  border: 1px solid var(--ds-outline-variant, #ebeef5);
  box-sizing: border-box;

  :deep(.el-icon-user-solid) {
    color: var(--ds-on-surface-variant, #909399);
  }
}

.user-nickname {
  font-size: 13px;
  color: var(--ds-on-surface, #303133);
  max-width: 90px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.article-title {
  font-size: 13px;
  color: var(--ds-on-surface, #303133);
}

.comment-content-cell {
  padding: 4px 0;
}

.reply-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.reply-to {
  font-size: 12px;
  color: var(--text-secondary);
}

.comment-text {
  margin: 0 0 6px;
  font-size: 13px;
  color: var(--ds-on-surface, #303133);
  line-height: 1.5;
  word-break: break-all;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.comment-images {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.comment-thumb {
  width: 48px;
  height: 48px;
  border-radius: 4px;
  cursor: pointer;
  border: 1px solid var(--ds-outline-variant, #ebeef5);
}

.comment-detail {
  padding: 0 20px 24px;
}

.comment-detail__content {
  line-height: 1.7;
  color: var(--ds-on-surface, #303133);
  word-break: break-all;
  white-space: pre-wrap;
}

.comment-detail__images {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.comment-detail__image {
  width: 72px;
  height: 72px;
  border: 1px solid var(--ds-outline-variant, #ebeef5);
  border-radius: 6px;
}

.btn-danger-text {
  color: var(--text-danger) !important;

  &:hover {
    opacity: 0.8;
  }
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}
</style>
