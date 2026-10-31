<template>
  <div class="kb-page">
    <!-- 顶部 -->
    <div class="kb-header">
      <div class="kb-header-left">
        <h2 class="kb-title">知识库管理</h2>
        <span class="kb-subtitle">共 {{ total }} 个知识库</span>
      </div>
      <el-button type="primary" icon="el-icon-plus" @click="openForm()">新建知识库</el-button>
    </div>

    <!-- 卡片网格 -->
    <div v-loading="loading" class="kb-grid">
      <div
        v-for="item in list"
        :key="item.id"
        class="kb-card"
        @click="goDetail(item)"
      >
        <!-- 封面 -->
        <div class="kb-cover">
          <img v-if="item.cover" :src="item.cover" class="kb-cover-img">
          <div v-else class="kb-cover-placeholder">
            <LegacyIcon name="el-icon-collection" />
          </div>
          <!-- 状态角标 -->
          <div :class="['kb-status-badge', item.status === 1 ? 'active' : 'inactive']">
            {{ item.status === 1 ? '启用' : '禁用' }}
          </div>
        </div>

        <!-- 内容区 -->
        <div class="kb-card-body">
          <div class="kb-card-name" :title="item.name">{{ item.name }}</div>
          <div class="kb-card-desc">{{ item.description || '暂无描述' }}</div>
          <div class="kb-card-footer">
            <span class="kb-doc-count">
              <LegacyIcon name="el-icon-document" />
              {{ item.doc_count || 0 }} 篇文档
            </span>
            <div class="kb-card-ops" @click.stop>
              <el-tooltip content="编辑">
                <LegacyIcon name="el-icon-edit" @click="openForm(item)" />
              </el-tooltip>
              <el-tooltip :content="item.status === 1 ? '禁用' : '启用'">
                <LegacyIcon
                  :name="item.status === 1 ? 'el-icon-turn-off' : 'el-icon-open'"
                  @click="handleToggleStatus(item)"
                />
              </el-tooltip>
              <el-tooltip content="删除">
                <LegacyIcon name="el-icon-delete" class=" danger-btn" @click="handleDelete(item)" />
              </el-tooltip>
            </div>
          </div>
        </div>
      </div>

      <!-- 空状态 -->
      <div v-if="!loading && list.length === 0" class="kb-empty">
        <el-empty description="暂无知识库，点击右上角新建">
          <el-button type="primary" @click="openForm()">立即新建</el-button>
        </el-empty>
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="total > query.limit" class="kb-pagination">
      <el-pagination
        :current-page="query.page"
        :page-size="query.limit"
        :page-sizes="[12, 24, 48]"
        layout="total, sizes, prev, pager, next"
        :total="total"
        @size-change="val => { query.limit = val; fetchList() }"
        @current-change="val => { query.page = val; fetchList() }"
      />
    </div>

    <!-- 新增/编辑弹框 -->
    <knowledge-base-form-dialog
      ref="formDialogRef"
      @saved="fetchList"
    />
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCsKnowledgeBaseList,
  deleteCsKnowledgeBase,
  toggleCsKnowledgeBaseStatus
} from '@/api/cs'
import KnowledgeBaseFormDialog from './KnowledgeBaseFormDialog.vue'

defineOptions({
  name: 'CsKnowledgeIndex'
})

const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const formDialogRef = ref(null)
const loading = ref(false)
const list = ref([])
const total = ref(0)
const query = reactive({
  page: 1,
  limit: 12
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getCsKnowledgeBaseList(query)
    const data = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

function openForm(item = null) {
  formDialogRef.value && formDialogRef.value.open(item)
}

function goDetail(item) {
  router.push({ name: 'CsKnowledgeDetail', params: { id: item.id }})
}

async function handleToggleStatus(item) {
  await toggleCsKnowledgeBaseStatus(item.id, { status: item.status === 1 ? 0 : 1 })
  message.success('状态更新成功')
  fetchList()
}

async function handleDelete(item) {
  try {
    await confirm(`确定删除知识库「${item.name}」吗？删除后不可恢复`, '确认删除', { type: 'warning' })
  } catch {
    return
  }
  await deleteCsKnowledgeBase(item.id)
  message.success('删除成功')
  fetchList()
}

onBeforeMount(() => {
  fetchList()
})
</script>

<style lang="scss" scoped>
.kb-page {
  padding: 0;
}

.kb-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.kb-header-left {
  display: flex;
  align-items: baseline;
  gap: 12px;
}

.kb-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--ds-on-surface);
  margin: 0;
}

.kb-subtitle {
  font-size: 13px;
  color: var(--ds-on-surface-variant);
}

/* 卡片网格 */
.kb-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;

  @media (max-width: 1400px) {
    grid-template-columns: repeat(4, 1fr);
  }

  @media (max-width: 1100px) {
    grid-template-columns: repeat(3, 1fr);
  }

  @media (max-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
}

.kb-card {
  background: var(--ds-surface-card);
  border-radius: 8px;
  border: 1px solid var(--ds-outline);
  overflow: hidden;
  cursor: pointer;
  transition: box-shadow 0.25s ease, transform 0.25s ease;

  &:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }
}

/* 封面 */
.kb-cover {
  height: 80px;
  position: relative;
  overflow: hidden;
}

.kb-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.kb-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--dashboard-accent-outline) 0%, var(--ds-surface-low) 100%);
  font-size: 32px;
  color: var(--ds-primary);
}

.kb-status-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  padding: 1px 6px;
  border-radius: 8px;
  font-size: 10px;
  font-weight: 500;

  &.active {
    background: rgba(103, 194, 58, 0.9);
    color: #fff;
  }

  &.inactive {
    background: rgba(144, 147, 153, 0.85);
    color: #fff;
  }
}

/* 卡片内容 */
.kb-card-body {
  padding: 10px 12px;
}

.kb-card-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ds-on-surface);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 4px;
}

.kb-card-desc {
  font-size: 11px;
  color: var(--ds-on-surface-variant);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 16px;
  margin-bottom: 8px;
}

.kb-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.kb-doc-count {
  font-size: 12px;
  color: var(--ds-on-surface-variant);
  display: flex;
  align-items: center;
  gap: 4px;
}

.kb-card-ops {
  display: flex;
  align-items: center;
  gap: 2px;

  i{
    padding: 4px 6px;
    font-size: 14px;
    color: var(--dashboard-text-muted);

    &:hover {
      color: var(--ds-primary);
    }

    &.danger-btn:hover {
      color: #f56c6c;
    }
  }
}

.kb-empty {
  grid-column: 1 / -1;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 60px 0;
}

.kb-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 24px;
}
</style>
