<template>
  <div>
    <!-- 搜索区域 -->
    <el-card shadow="never" class="search-card">
      <el-form
        ref="searchForm"
        :model="searchForm"
        inline
        class="search-form"
        @submit.prevent
      >
        <el-form-item label="内容标题">
          <el-input
            v-model="searchForm.title"
            clearable
            placeholder="请输入内容标题"
            class="search-input"
            :prefix-icon="Search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="内容类型">
          <el-select
            v-model="searchForm.content_type"
            placeholder="全部类型"
            clearable
            class="search-select"
          >
            <el-option
              v-for="item in contentTypes"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="内容分类">
          <el-cascader
            v-model="searchForm.article_category_id"
            :options="categoryList"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
            placeholder="请选择分类"
            class="form-width"
            clearable
            filterable
            @change="handleCategoryChange"
          />
        </el-form-item>
        <el-form-item label="付费状态">
          <el-select
            v-model="searchForm.is_trial"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="全部" value="" />
            <el-option label="免费" :value="0" />
            <el-option label="付费" :value="1" />
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
    <el-card shadow="never">
      <el-button type="primary" :icon="Plus" @click="handleAdd">添加内容</el-button>

      <el-table
        v-loading="loading"
        :data="articleList"
        row-key="id"
        class="content-table mt14"
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
        <el-table-column prop="title" label="标题" min-width="100" show-overflow-tooltip />
        <el-table-column label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="getTypeTagColor(row.content_type)" >
              {{ row.content_type_text || getTypeName(row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="articleCategory.name" label="分类" width="120" align="center" />
        <el-table-column label="价格" width="100" align="center">
          <template #default="{ row }">
            <span v-if="row.price > 0" class="price-text">¥{{ row.price }}</span>
            <el-tag v-else type="success" >免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="试读" width="70" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.is_trial" type="warning" >试读</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="visit" label="浏览量" width="90" align="center" />
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
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
            <a @click="handleDelete(row.id)">删除</a>
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
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  getArticleList,
  getContentTypes,
  articleDelete,
  articleSetShow,
  getArticleCategoryList, getArticleCategoryTree
} from '@/api/article'

defineOptions({
  name: 'ContentManage'
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

const loading = ref(false)
const articleList = ref([])
const categoryList = ref([])
const contentTypes = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  title: '',
  content_type: '',
  article_category_id: '',
  is_trial: '',
  status: ''
})

// 初始化数据
async function initData() {
  await Promise.all([
    loadContentTypes(),
    loadCategoryList()
  ])
  fetchArticleList()
}

// 级联选择器变更
function handleCategoryChange(val) {
  // val 是数组，取最后一个元素作为实际 id
  if (val && val.length) {
    searchForm.article_category_id = val[val.length - 1]
  } else {
    searchForm.article_category_id = ''
  }
}

// 加载内容类型
function loadContentTypes() {
  return getContentTypes().then(res => {
    contentTypes.value = res.data.list || []
  })
}

// 加载分类列表
function loadCategoryList() {
  return getArticleCategoryTree().then(res => {
    categoryList.value = res.data || []
  })
}

// 获取内容列表
function fetchArticleList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  getArticleList(params).then(res => {
    articleList.value = res.data.list || []
    total.value = res.data.total || 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

// 获取类型名称
function getTypeName(type) {
  return CONTENT_TYPE_MAP[type] ? CONTENT_TYPE_MAP[type].label : '未知'
}

// 获取类型标签颜色
function getTypeTagColor(type) {
  return CONTENT_TYPE_MAP[type] ? CONTENT_TYPE_MAP[type].color : ''
}

// 搜索
function handleSearch() {
  page.value = 1
  fetchArticleList()
}

// 重置
function handleReset() {
  Object.assign(searchForm, {
    title: '',
    content_type: '',
    cid: '',
    is_trial: '',
    status: ''
  })
  page.value = 1
  fetchArticleList()
}

// 添加内容
function handleAdd() {
  router.push('/article/article/create')
}

// 编辑内容
function handleEdit(id) {
  router.push(`/article/article/create?id=${id}`)
}

// 删除内容
function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该内容吗？删除后不可恢复。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    articleDelete(id).then(res => {
      ElMessage.success(res.message)
      fetchArticleList()
    })
  }).catch(res => {
    ElMessage.error(res.message)
  })
}

// 状态切换
function handleStatusChange(row) {
  articleSetShow(row.id).then(res => {
    ElMessage.success(res.message)
  }).catch(res => {
    row.status = row.status === 1 ? 0 : 1
    ElMessage.error(res.message)
  })
}

// 分页
function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  fetchArticleList()
}

function handleCurrentChange(val) {
  page.value = val
  fetchArticleList()
}

onMounted(() => {
  initData()
})
</script>

<style lang="scss" scoped>
.content-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 100px);
}

.search-card {
  margin-bottom: 16px;
  border-radius: 8px;

  :deep .el-card__body {
    padding: 20px 20px 4px;
  }
}

.search-form {
  :deep .el-form-item {
    margin-bottom: 16px;
    margin-right: 16px;
  }

  :deep .el-form-item__label {
    font-size: 14px;
    color: #606266;
    font-weight: 500;
  }

  .search-input {
    width: 200px;
  }

  .search-select {
    width: 140px;
  }

  .search-select-sm {
    width: 110px;
  }
}

.table-card {
  border-radius: 8px;

  :deep .el-card__body {
    padding: 0;
  }
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #ebeef5;

  .header-title {
    display: flex;
    align-items: center;
    gap: 12px;

    .title-text {
      font-size: 16px;
      font-weight: 600;
      color: #303133;
    }

    .title-desc {
      font-size: 13px;
      color: #909399;
    }
  }

  .header-actions {
    display: flex;
    gap: 12px;
  }
}

.content-table {
  .cover-img {
    width: 44px;
    height: 44px;
    border-radius: 4px;
    cursor: pointer;
  }

  .no-cover {
    font-size: 12px;
    color: #c0c4cc;
  }

  .price-text {
    color: var(--text-price);
    font-weight: 600;
    font-size: 14px;
  }

  :deep .el-button--text {
    padding: 0;
    margin: 0 4px;
    font-size: 13px;

    &.delete-btn {
      color: #f56c6c;

      &:hover {
        color: #f78989;
      }
    }
  }
}

.pagination-wrap {
  padding: 16px 24px;
  display: flex;
  justify-content: flex-end;
}
</style>
