<template>
  <div>
    <!-- 搜索区域 -->
    <el-card shadow="never" class="search-card ivu-mb-16">
      <el-form
        ref="searchForm"
        :model="searchForm"
        inline
        class="search-form"
        @submit.prevent
      >
        <el-form-item label="专题标题">
          <el-input
            v-model="searchForm.title"
            clearable
            placeholder="请输入专题标题"
            class="search-input"
            :prefix-icon="Search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="专题类型">
          <el-select
            v-model="searchForm.special_type"
            placeholder="全部类型"
            clearable
            class="search-select-sm"
          >
            <el-option label="免费" :value="0" />
            <el-option label="付费" :value="1" />
          </el-select>
        </el-form-item>
        <el-form-item label="热门状态">
          <el-select
            v-model="searchForm.is_hot"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="热门" :value="1" />
            <el-option label="普通" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="显示状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部状态"
            clearable
            class="search-select-sm"
          >
            <el-option label="显示" :value="1" />
            <el-option label="隐藏" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 表格区域 -->
    <el-card shadow="never" class="table-card">
      <el-button type="primary" :icon="Plus" @click="handleAdd">添加专题</el-button>

      <el-table
        v-loading="loading"
        :data="specialList"
        row-key="id"
        class="mt14"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.cover"
              :src="row.cover"
              :preview-src-list="[row.cover]"
              class="cover-img"
              fit="cover"
            />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="专题标题" min-width="180" show-overflow-tooltip />
        <el-table-column label="类型" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.special_type === 1 ? 'danger' : 'success'" >
              {{ row.special_type === 1 ? '付费' : '免费' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="100" align="center">
          <template #default="{ row }">
            <span v-if="row.special_type === 1 && row.price > 0" class="price-text">
              ¥{{ row.price }}
            </span>
            <span v-else class="free-text">-</span>
          </template>
        </el-table-column>
        <el-table-column label="内容数" width="90" align="center">
          <template #default="{ row }">
            <el-badge
              :value="row.content_count || 0"
              :max="999"
              class="content-badge"
            >
              <el-button
                size="mini"
                type="text"
                @click="handleViewContents(row.id)"
              >查看</el-button>
            </el-badge>
          </template>
        </el-table-column>
        <el-table-column label="热门" width="70" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.is_hot" type="warning" >热门</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="visit" label="浏览" width="80" align="center" />
        <el-table-column prop="pay_count" label="购买" width="80" align="center" />
        <el-table-column prop="sort" label="排序" width="70" align="center" />
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
            <el-button type="text" :icon="Edit" @click="handleEdit(row.id)">编辑</el-button>
            <el-divider direction="vertical" />
            <el-button type="text" class="delete-btn" :icon="Delete" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="acea-row row-right page">
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

    <!-- 查看内容弹窗 -->
    <el-dialog
      v-model="contentDialogVisible"
      title="专题内容列表"
      width="750px"
      :close-on-click-modal="false"
      @close="contentList = []"
    >
      <div v-loading="contentLoading">
        <el-empty v-if="!contentLoading && contentList.length === 0" description="暂无内容" />
        <el-table
          v-else
          :data="contentList"
          border

          :header-cell-style="{ background: '#fafafa' }"
        >
          <el-table-column prop="sort" label="排序" width="60" align="center" />
          <el-table-column label="封面" width="70" align="center">
            <template #default="{ row }">
              <el-image
                v-if="row.article && row.article.cover"
                :src="row.article.cover"
                class="mini-cover"
                fit="cover"
              />
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="内容标题" min-width="150" show-overflow-tooltip>
            <template #default="{ row }">
              {{ row.article ? row.article.title : '-' }}
            </template>
          </el-table-column>
          <el-table-column label="类型" width="80" align="center">
            <template #default="{ row }">
              <el-tag
                v-if="row.article"
                :type="getTypeTagColor(row.article.content_type)"
                size="mini"
              >
                {{ getTypeName(row.article.content_type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="价格" width="90" align="center">
            <template #default="{ row }">
              <span v-if="row.article && row.article.price > 0" class="price-text">
                ¥{{ row.article.price }}
              </span>
              <el-tag v-else type="success" size="mini">免费</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </div>
      <div slot="footer">
        <el-button @click="contentDialogVisible = false">关闭</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Edit, Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  getSpecialList,
  specialDelete,
  specialSetShow,
  getSpecialContentList
} from '@/api/special'

defineOptions({
  name: 'SpecialManage'
})

const router = useRouter()

const CONTENT_TYPE_MAP = {
  1: { label: '图文', color: 'primary' },
  2: { label: '文字', color: '' },
  3: { label: '小说', color: 'warning' },
  4: { label: '图片', color: 'success' },
  5: { label: '音频', color: 'danger' },
  6: { label: '视频', color: 'info' }
}

const searchFormRef = ref(null)
const loading = ref(false)
const specialList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const searchForm = reactive({
  title: '',
  special_type: '',
  is_hot: '',
  status: ''
})
// 查看内容弹窗
const contentDialogVisible = ref(false)
const contentLoading = ref(false)
const contentList = ref([])

// 获取专题列表
function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  // 清除空字符串
  Object.keys(params).forEach(key => {
    if (params[key] === '') delete params[key]
  })
  getSpecialList(params)
    .then(res => {
      specialList.value = res.data.list || []
      total.value = res.data.count || 0
    })
    .finally(() => {
      loading.value = false
    })
}

// 搜索
function handleSearch() {
  page.value = 1
  getList()
}

// 重置
function handleReset() {
  if (searchFormRef.value) {
    searchFormRef.value.resetFields()
  }
  Object.assign(searchForm, { title: '', special_type: '', is_hot: '', status: '' })
  page.value = 1
  getList()
}

// 分页
function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getList()
}

function handleCurrentChange(val) {
  page.value = val
  getList()
}

// 添加
function handleAdd() {
  router.push('/special/manage/create')
}

// 编辑
function handleEdit(id) {
  router.push(`/special/manage/create?id=${id}`)
}

// 状态切换
function handleStatusChange(row) {
  specialSetShow(row.id)
    .then(() => {
      ElMessage.success('状态更新成功')
    })
    .catch(() => {
      // 回滚状态
      row.status = row.status === 1 ? 0 : 1
    })
}

// 删除
function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该专题吗？删除后将同时移除关联内容，且无法恢复！', '删除确认', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    specialDelete(id).then(() => {
      ElMessage.success('删除成功')
      // 若当前页只有1条数据且非第1页，回到上一页
      if (specialList.value.length === 1 && page.value > 1) {
        page.value -= 1
      }
      getList()
    })
  }).catch(() => {})
}

// 查看专题内容
function handleViewContents(id) {
  contentDialogVisible.value = true
  contentLoading.value = true
  contentList.value = []
  getSpecialContentList(id)
    .then(res => {
      contentList.value = res.data.list || []
    })
    .finally(() => {
      contentLoading.value = false
    })
}

// 获取类型标签颜色
function getTypeTagColor(type) {
  return (CONTENT_TYPE_MAP[type] || {}).color || ''
}

// 获取类型名称
function getTypeName(type) {
  return (CONTENT_TYPE_MAP[type] || {}).label || '未知'
}

onMounted(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.special-container {
  padding: 16px;
  min-height: 100%;

  .search-card {
    margin-bottom: 16px;
    border-radius: 8px;

    .search-form {
      .search-input {
        width: 220px;
      }
      .search-select-sm {
        width: 120px;
      }
    }
  }

  .table-card {
    border-radius: 8px;

    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;

      .header-title {
        .title-text {
          font-size: 16px;
          font-weight: 600;
          color: #303133;
        }
        .title-desc {
          margin-left: 8px;
          font-size: 13px;
          color: #909399;
        }
      }
    }

    .special-table {
      width: 100%;

      .cover-img {
        width: 50px;
        height: 50px;
        border-radius: 4px;
        display: block;
        margin: 0 auto;
      }
      .no-cover {
        color: #c0c4cc;
        font-size: 12px;
      }
      .price-text {
        color: var(--text-price);
        font-weight: 600;
      }
      .free-text {
        color: #909399;
      }
      .content-badge {
        ::v-deep .el-badge__content {
          background-color: #409eff;
        }
      }
      .delete-btn {
        color: #f56c6c;
        &:hover {
          color: #f56c6c;
          opacity: 0.8;
        }
      }
    }

    .pagination-wrap {
      margin-top: 20px;
      display: flex;
      justify-content: flex-end;
    }
  }

  .mini-cover {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    display: block;
    margin: 0 auto;
  }
  .price-text {
    color: var(--text-price);
    font-weight: 600;
  }
}
</style>
