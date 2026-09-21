<template>
  <div class="article-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">内容管理</h1>
        <p class="page-curator-subtitle">
          管理图文、音频、视频等多类型内容，支持分类筛选与付费设置。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        :icon="Plus"
        @click="handleAdd"
      >
        添加内容
      </el-button>
    </header>

    <div class="article-work-surface">
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
              v-model="searchForm.article_category_path"
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
              <el-option label="免费" :value="0" />
              <el-option label="付费" :value="1" />
            </el-select>
          </el-form-item>
          <el-form-item label="审核状态">
            <el-select
              v-model="searchForm.audit_status"
              placeholder="全部"
              clearable
              class="search-select-sm"
            >
              <el-option label="待审核" :value="0" />
              <el-option label="审核通过" :value="1" />
              <el-option label="审核驳回" :value="2" />
              <el-option label="已下架" :value="3" />
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
            <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <el-card shadow="never" class="table-card">
        <el-table
          v-loading="loading"
          :data="articleList"
          row-key="id"
          class="content-table article-curator-table"
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
                {{ getTypeName(row.content_type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="分类" width="120" align="center">
            <template #default="{ row }">
              {{ row.articleCategory ? row.articleCategory.name : '-' }}
            </template>
          </el-table-column>
          <el-table-column label="标签" min-width="170">
            <template #default="{ row }">
              <div class="tag-cell">
                <TagPreview
                  v-for="tag in row.tag_list || []"
                  :key="tag.id"
                  :tag="tag"
                />
                <span v-if="!row.tag_list || !row.tag_list.length" class="empty-text">-</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="价格" width="150" align="center">
            <template #default="{ row }">
              <div v-if="isQuickEditingPrice(row)" class="quick-price-editor">
                <el-input-number
                  :ref="`quickPriceInput${row.id}`"
                  v-model="quickEdit.value"
                  :min="0"
                  :max="99999999.99"
                  :precision="2"
                  :step="1"
                  :disabled="quickEdit.loading"
                  size="mini"
                  controls-position="right"
                  class="quick-price-input"
                  @keyup.enter="submitQuickPrice(row)"
                />
                <el-button
                  type="text"
                  size="mini"
                  :icon="Check"
                  title="保存"
                  :loading="quickEdit.loading"
                  @click="submitQuickPrice(row)"
                />
                <el-button
                  type="text"
                  size="mini"
                  :icon="Close"
                  title="取消"
                  :disabled="quickEdit.loading"
                  @click="cancelQuickEdit"
                />
              </div>
              <div v-else class="quick-price-display" @click="startQuickPriceEdit(row)">
                <span v-if="Number(row.price || 0) > 0" class="price-text">¥{{ formatPrice(row.price) }}</span>
                <el-tag v-else type="success" >免费</el-tag>
                <LegacyIcon name="el-icon-edit-outline" class="quick-price-icon" />
              </div>
            </template>
          </el-table-column>
          <el-table-column label="试读" width="70" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.is_trial" type="warning" >试读</el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column prop="visit" label="浏览量" width="90" align="center" />
          <el-table-column label="审核状态" width="110" align="center">
            <template #default="{ row }">
              <el-tag :type="getAuditTagType(row.audit_status)" >
                {{ row.audit_status_text || getAuditLabel(row.audit_status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="不通过原因" min-width="150">
            <template #default="{ row }">
              <el-tooltip
                v-if="isAuditRejected(row) && row.audit_remark"
                :content="row.audit_remark"
                placement="top"
                effect="dark"
              >
                <span class="audit-remark-trigger">{{ getAuditRemarkBrief(row.audit_remark) }}</span>
              </el-tooltip>
              <span v-else-if="isAuditRejected(row)" class="empty-text">未填写</span>
              <span v-else class="empty-text">-</span>
            </template>
          </el-table-column>
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
          <el-table-column label="操作" width="190" fixed="right" align="center">
            <template #default="{ row }">
              <div class="table-action-group">
                <a @click="handleDetail(row.id)">详情</a>
                <el-divider direction="vertical" />
                <a @click="handleEdit(row.id)">编辑</a>
                <el-divider direction="vertical" />
                <el-dropdown
                  trigger="click"
                  class="table-action-more"
                  @command="command => handleMoreCommand(command, row)"
                >
                  <a href="javascript:;" @click.prevent>
                    更多<LegacyIcon name="el-icon-arrow-down" class="el-icon--right" />
                  </a>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="tag">编辑标签</el-dropdown-item>
                      <el-dropdown-item command="delete" class="dropdown-danger">删除</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </div>
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

    <TagSelector
      ref="tagQuickSelectorRef"
      v-model="tagEditIds"
      class="hidden-tag-selector"
      :tag-type="1"
      :show-quick-add="false"
      @confirm="submitTagEdit"
    />

    <el-drawer
      v-model="detailDrawerVisible"
      title="内容详情"
      direction="rtl"
      size="60%"
      :before-close="handleDrawerClose"
    >
      <div v-loading="detailLoading" class="drawer-content">
        <template v-if="detailData">
          <div class="detail-section detail-section--plain">
            <div class="detail-summary">
              <el-image
                v-if="detailData.cover"
                :src="detailData.cover"
                :preview-src-list="[detailData.cover]"
                class="detail-cover"
                fit="cover"
              />
              <div v-else class="detail-cover-empty">
                <LegacyIcon name="el-icon-picture-outline" />
              </div>
              <div class="detail-meta">
                <div class="detail-info-sections">
                  <section class="detail-info-section">
                    <div class="detail-section-title detail-section-title--inner">内容信息</div>
                    <div class="detail-info-grid">
                      <div class="detail-info-item detail-info-item--full">
                        <span class="detail-info-item__label">内容标题</span>
                        <span class="detail-info-item__value">{{ detailData.title || '-' }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">内容类型</span>
                        <span class="detail-info-item__value">
                          <el-tag :type="getTypeTagColor(detailData.content_type)" >
                            {{ detailData.content_type_text || getTypeName(detailData.content_type) }}
                          </el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">内容分类</span>
                        <span class="detail-info-item__value">{{ detailData.articleCategory && detailData.articleCategory.name ? detailData.articleCategory.name : '-' }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">作者</span>
                        <span class="detail-info-item__value">{{ detailData.author || '-' }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">关键词</span>
                        <span class="detail-info-item__value">{{ detailData.keywords || '-' }}</span>
                      </div>
                      <div class="detail-info-item detail-info-item--full">
                        <span class="detail-info-item__label">内容标签</span>
                        <span class="detail-info-item__value detail-tags">
                          <TagPreview
                            v-for="tag in detailData.tag_list || []"
                            :key="tag.id"
                            :tag="tag"
                          />
                          <span v-if="!detailData.tag_list || !detailData.tag_list.length">-</span>
                        </span>
                      </div>
                      <div class="detail-info-item detail-info-item--full">
                        <span class="detail-info-item__label">简介</span>
                        <span class="detail-info-item__value detail-info-item__value--desc">{{ detailData.description || '-' }}</span>
                      </div>
                    </div>
                  </section>

                  <section class="detail-info-section">
                    <div class="detail-section-title detail-section-title--inner">来源与状态</div>
                    <div class="detail-info-grid">
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">内容来源</span>
                        <span class="detail-info-item__value">
                          <el-tag :type="detailData.mer_id > 0 ? 'warning' : 'info'" >
                            {{ detailData.source_text || getSourceLabel(detailData.mer_id) }}
                          </el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">所属商户</span>
                        <span class="detail-info-item__value">{{ formatMerchantName(detailData) }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">显示状态</span>
                        <span class="detail-info-item__value">
                          <el-tag :type="Number(detailData.status) === 1 ? 'success' : 'info'" >
                            {{ Number(detailData.status) === 1 ? '显示' : '隐藏' }}
                          </el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">审核状态</span>
                        <span class="detail-info-item__value">
                          <el-tag :type="getAuditTagType(detailData.audit_status)" >
                            {{ detailData.audit_status_text || getAuditLabel(detailData.audit_status) }}
                          </el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item detail-info-item--full">
                        <span class="detail-info-item__label">来源说明</span>
                        <span class="detail-info-item__value">{{ detailData.source || '-' }}</span>
                      </div>
                      <div v-if="detailData.audit_remark" class="detail-info-item detail-info-item--full">
                        <span class="detail-info-item__label">审核备注</span>
                        <span class="detail-info-item__value">{{ detailData.audit_remark }}</span>
                      </div>
                    </div>
                  </section>

                  <section class="detail-info-section">
                    <div class="detail-section-title detail-section-title--inner">售卖与数据</div>
                    <div class="detail-info-grid">
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">售价</span>
                        <span class="detail-info-item__value">
                          <span v-if="Number(detailData.price || 0) > 0" class="price-text">¥{{ detailData.price }}</span>
                          <el-tag v-else type="success" >免费</el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">原价</span>
                        <span class="detail-info-item__value">{{ Number(detailData.original_price || 0) > 0 ? `¥${detailData.original_price}` : '-' }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">试读试看</span>
                        <span class="detail-info-item__value">
                          <el-tag v-if="detailData.is_trial" type="warning" >支持</el-tag>
                          <span v-else>不支持</span>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">浏览量</span>
                        <span class="detail-info-item__value">{{ detailData.view_count || detailData.visit || 0 }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">排序</span>
                        <span class="detail-info-item__value">{{ detailData.sort || 0 }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">媒体时长</span>
                        <span class="detail-info-item__value">{{ detailData.media_duration ? formatDuration(detailData.media_duration) : '-' }}</span>
                      </div>
                    </div>
                  </section>

                  <section class="detail-info-section">
                    <div class="detail-section-title detail-section-title--inner">时间信息</div>
                    <div class="detail-info-grid">
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">创建时间</span>
                        <span class="detail-info-item__value">{{ detailData.create_time || '-' }}</span>
                      </div>
                    </div>
                  </section>
                </div>
              </div>
            </div>
          </div>

          <div
            v-for="section in detailPreviewSections"
            :key="section.key"
            class="detail-section"
          >
            <div class="detail-section-title">{{ section.title }}</div>

            <template v-if="[1, 2, 3].includes(detailContentType)">
              <div v-if="resolvePreviewHtml(section)" class="rich-content" v-html="resolvePreviewHtml(section)" />
              <el-empty v-else :description="resolvePreviewEmptyText(section)" />
            </template>

            <template v-else-if="detailContentType === 4">
              <div v-if="resolvePreviewImages(section).length" class="image-list">
                <el-image
                  v-for="(image, index) in resolvePreviewImages(section)"
                  :key="`${section.key}-${index}`"
                  :src="image"
                  :preview-src-list="resolvePreviewImages(section)"
                  class="image-item"
                  fit="cover"
                />
              </div>
              <el-empty v-else :description="resolvePreviewEmptyText(section)" />
            </template>

            <template v-else-if="detailContentType === 5">
              <div v-if="resolvePreviewMediaUrl(section)" class="media-preview">
                <audio :src="resolvePreviewMediaUrl(section)" controls class="media-player" />
              </div>
              <el-empty v-else :description="resolvePreviewEmptyText(section)" />
            </template>

            <template v-else-if="detailContentType === 6">
              <div v-if="resolvePreviewMediaUrl(section)" class="media-preview">
                <video :src="resolvePreviewMediaUrl(section)" controls class="media-player video-player" />
              </div>
              <el-empty v-else :description="resolvePreviewEmptyText(section)" />
            </template>
          </div>
        </template>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Close, Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  getArticleList,
  getContentTypes,
  articleDelete,
  articleSetShow,
  getArticleCategoryTree,
  articleRead,
  articleQuickUpdate
} from '@/api/article'
import { saveTagRelation } from '@/api/tag'
import TagSelector from '@/components/TagSelector/index'
import TagPreview from '@/components/TagPreview/index'
import { normalizeResourceUrl } from '@/utils'

defineOptions({
  name: 'ContentManage'
})

const router = useRouter()
const { proxy } = getCurrentInstance() || {}

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
  article_category_path: [],
  is_trial: '',
  audit_status: '',
  status: ''
})
const detailDrawerVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const tagEditLoading = ref(false)
const tagEditTarget = ref({})
const tagEditIds = ref([])
const quickEdit = reactive({
  id: 0,
  field: '',
  value: 0,
  loading: false
})
const tagQuickSelectorRef = ref(null)

const detailContentType = computed(() => {
  return Number(detailData.value && detailData.value.content_type ? detailData.value.content_type : 0)
})

const isDetailPaid = computed(() => {
  return Number(detailData.value && detailData.value.price ? detailData.value.price : 0) > 0
})

const detailPreviewSections = computed(() => {
  if (!detailData.value) {
    return []
  }
  if (!isDetailPaid.value) {
    return [{ key: 'free', title: '免费内容', mode: 'full' }]
  }

  return [
    { key: 'free', title: '免费内容', mode: 'trial' },
    { key: 'paid', title: '付费内容', mode: 'full' }
  ]
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
    title: searchForm.title,
    content_type: searchForm.content_type,
    article_category_id: searchForm.article_category_id,
    is_trial: searchForm.is_trial,
    audit_status: searchForm.audit_status,
    status: searchForm.status
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

// 内容来源文案
function getSourceLabel(merId) {
  return Number(merId) > 0 ? '商户发布' : '平台发布'
}

// 商户名称
function formatMerchantName(row = {}) {
  const merId = Number(row.mer_id || 0)
  if (!merId) {
    return '平台'
  }
  const merchant = row.merchant || {}
  if (merchant.mer_name) {
    return `${merchant.mer_name}（ID：${merId}）`
  }
  return `商户ID：${merId}`
}

// 审核状态文案
function getAuditLabel(status) {
  if (status === '' || status === null || status === undefined) return '-'
  const value = Number(status)
  const map = { 0: '待审核', 1: '已通过', 2: '已驳回', 3: '已下架' }
  return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : '-'
}

// 审核状态标签颜色
function getAuditTagType(status) {
  if (status === '' || status === null || status === undefined) return 'info'
  const value = Number(status)
  const map = { 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }
  return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : 'info'
}

// 是否审核驳回
function isAuditRejected(row = {}) {
  return Number(row.audit_status) === 2
}

// 审核备注摘要
function getAuditRemarkBrief(remark) {
  const text = String(remark || '').trim()
  if (!text) return '未填写'
  return text.length > 16 ? `${text.slice(0, 16)}...` : text
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
    article_category_id: '',
    article_category_path: [],
    is_trial: '',
    audit_status: '',
    status: ''
  })
  page.value = 1
  fetchArticleList()
}

// 添加内容
function handleAdd() {
  router.push('/merchant/content/article/article/create')
}

// 编辑内容
function handleEdit(id) {
  router.push(`/merchant/content/article/article/create?id=${id}`)
}

// 是否正在快速编辑价格
function isQuickEditingPrice(row) {
  return Number(quickEdit.id) === Number(row && row.id) && quickEdit.field === 'price'
}

// 开始快速编辑价格
function startQuickPriceEdit(row) {
  if (!row || !row.id || quickEdit.loading) {
    return
  }
  Object.assign(quickEdit, {
    id: row.id,
    field: 'price',
    value: Number(row.price || 0),
    loading: false
  })
  nextTick(() => {
    const ref = proxy.$refs[`quickPriceInput${row.id}`]
    const input = Array.isArray(ref) ? ref[0] : ref
    if (input && input.focus) {
      input.focus()
    }
  })
}

// 取消快速编辑
function cancelQuickEdit() {
  if (quickEdit.loading) {
    return
  }
  resetQuickEdit()
}

// 重置快速编辑状态
function resetQuickEdit() {
  Object.assign(quickEdit, {
    id: 0,
    field: '',
    value: 0,
    loading: false
  })
}

// 提交快速编辑价格
function submitQuickPrice(row) {
  if (!row || !row.id || quickEdit.loading) {
    return
  }
  const price = Number(quickEdit.value || 0)
  if (Number.isNaN(price) || price < 0) {
    ElMessage.warning('请输入正确的金额')
    return
  }
  quickEdit.loading = true
  articleQuickUpdate(row.id, { price: price.toFixed(2) })
    .then(res => {
      ElMessage.success(res.message || '修改成功')
      row.price = price
      resetQuickEdit()
    })
    .catch(() => {})
    .finally(() => {
      if (isQuickEditingPrice(row)) {
        quickEdit.loading = false
      }
    })
}

// 更多操作
function handleMoreCommand(command, row) {
  if (command === 'tag') {
    openTagEdit(row)
    return
  }
  if (command === 'delete') {
    handleDelete(row.id)
  }
}

// 打开标签编辑
function openTagEdit(row) {
  tagEditTarget.value = row || {}
  tagEditIds.value = Array.isArray(row.tag_ids) ? row.tag_ids.slice() : []
  nextTick(() => {
    if (tagQuickSelectorRef.value) {
      tagQuickSelectorRef.value.openSelectDialog()
    }
  })
}

// 提交标签编辑
function submitTagEdit(tagIds = tagEditIds.value) {
  if (!tagEditTarget.value.id) return
  tagEditLoading.value = true
  saveTagRelation({
    relation_type: 'article',
    relation_id: tagEditTarget.value.id,
    tag_type: 1,
    tag_ids: Array.isArray(tagIds) ? tagIds : tagEditIds.value
  }).then(res => {
    ElMessage.success(res.message || '保存成功')
    fetchArticleList()
  }).finally(() => {
    tagEditLoading.value = false
  })
}

// 查看详情
function handleDetail(id) {
  detailData.value = null
  detailDrawerVisible.value = true
  detailLoading.value = true
  articleRead(id).then(res => {
    detailData.value = res.data || {}
  }).catch(() => {
    ElMessage.error('获取详情失败')
  }).finally(() => {
    detailLoading.value = false
  })
}

// 预览 HTML 内容
function resolvePreviewHtml(section) {
  if (!detailData.value) {
    return ''
  }
  if (section.mode === 'trial') {
    return detailData.value.trial_content || ''
  }

  return detailData.value.content || ''
}

// 预览图片列表
function resolvePreviewImages(section) {
  if (!detailData.value) {
    return []
  }
  const images = section.mode === 'trial' ? detailData.value.trial_images : detailData.value.images
  return Array.isArray(images) ? images.filter(Boolean) : []
}

// 预览媒体地址
function resolvePreviewMediaUrl(section) {
  if (!detailData.value) {
    return ''
  }
  return normalizeResourceUrl(section.mode === 'trial'
    ? detailData.value.trial_media_url
    : detailData.value.media_url)
}

// 预览空状态文案
function resolvePreviewEmptyText(section) {
  if (isDetailPaid.value && section.mode === 'trial' && Number(detailData.value && detailData.value.is_trial) !== 1) {
    return '当前未配置免费试看内容'
  }
  const prefix = section.mode === 'trial' ? '免费内容' : '付费内容'
  if ([1, 2, 3].includes(detailContentType.value)) {
    return `${prefix}暂无正文`
  }
  if (detailContentType.value === 4) {
    return `${prefix}暂无图片`
  }
  if (detailContentType.value === 5) {
    return `${prefix}暂无音频`
  }
  if (detailContentType.value === 6) {
    return `${prefix}暂无视频`
  }
  return '暂无内容'
}

// 抽屉关闭
function handleDrawerClose(done) {
  detailData.value = null
  done()
}

// 删除内容
function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该内容吗？删除后不可恢复。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    articleDelete(id).then(res => {
      ElMessage.success(res.message || '删除成功')
      fetchArticleList()
    })
  }).catch(() => {})
}

// 状态切换
function handleStatusChange(row) {
  articleSetShow(row.id, row.status).then(res => {
    ElMessage.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
    ElMessage.error('状态更新失败')
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

// 时长格式化
function formatDuration(sec) {
  if (!sec) return ''
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  if (h > 0) return `${h}时${m}分${s}秒`
  if (m > 0) return `${m}分${s}秒`
  return `${s}秒`
}

// 价格格式化
function formatPrice(price) {
  const value = Number(price || 0)
  return value.toFixed(2)
}

onMounted(() => {
  initData()
})
</script>

<style lang="scss" scoped>
.article-curator-page {
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

.article-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep .el-card__body {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
}

:deep .btn-curator-primary.el-button--primary {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep .btn-curator-reset {
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

.search-form {
  :deep .el-form-item {
    margin-bottom: 16px;
    margin-right: 16px;
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

  .form-width {
    width: 200px;
  }
}

.article-curator-table {
  :deep .el-table__header th {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep .el-table__row:hover > td {
    background: var(--ds-surface-low) !important;
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
    color: var(--text-placeholder);
  }

  .price-text {
    color: var(--text-price);
    font-weight: 600;
    font-size: 14px;
  }

  .quick-price-display {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 28px;
    cursor: pointer;
  }

  .quick-price-icon {
    color: var(--text-secondary);
    font-size: 13px;
    opacity: 0;
    transition: opacity .2s;
  }

  .quick-price-display:hover .quick-price-icon {
    opacity: 1;
  }

  .quick-price-editor {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
  }

  .quick-price-input {
    width: 92px;
  }

  :deep .quick-price-input .el-input__inner {
    padding-left: 6px;
    padding-right: 34px;
    font-size: 12px;
  }

}

.pagination-wrap {
  padding-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.drawer-content {
  height: 100%;
  padding: 0 20px 20px;
  box-sizing: border-box;
  overflow-y: auto;
}

.detail-section {
  margin-bottom: 24px;
}

.detail-section--plain {
  margin-bottom: 28px;
}

.detail-section-title {
  margin-bottom: 16px;
  padding-left: 10px;
  border-left: 3px solid var(--ds-primary);
  font-size: 15px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.detail-section-title--inner {
  margin-bottom: 14px;
}

.detail-summary {
  display: flex;
  align-items: flex-start;
  gap: 18px;
}

.detail-meta {
  flex: 1;
  min-width: 0;
}

.detail-info-sections {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-info-section {
  padding: 18px 20px;
  border-radius: 12px;
  background: rgba(var(--ds-surface-low-rgb), 0.62);
}

.detail-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 24px;
}

.detail-info-item {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  line-height: 1.6;

  &--full {
    grid-column: 1 / -1;
  }
}

.detail-info-item__label {
  width: 86px;
  flex-shrink: 0;
  color: var(--ds-on-surface-variant);
  font-size: 13px;
}

.detail-info-item__value {
  flex: 1;
  min-width: 0;
  color: var(--ds-on-surface);
  font-size: 13px;
  word-break: break-all;
}

.detail-info-item__value--desc {
  line-height: 1.8;
}

.detail-cover,
.detail-cover-empty {
  width: 104px;
  height: 104px;
  border-radius: 10px;
  flex-shrink: 0;
}

.detail-cover-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(var(--ds-surface-low-rgb), 0.7);
  color: var(--text-disabled, #c0c4cc);
  font-size: 30px;
}

.audit-remark-trigger {
  display: inline-block;
  max-width: 132px;
  overflow: hidden;
  color: var(--ds-on-surface);
  line-height: 1.4;
  text-overflow: ellipsis;
  vertical-align: middle;
  white-space: nowrap;
  cursor: help;
}

.rich-content {
  min-height: 120px;
  padding: 16px;
  border: 1px solid rgba(var(--ds-surface-low-rgb), 0.9);
  border-radius: 8px;
  background: rgba(var(--ds-surface-low-rgb), 0.45);
  color: var(--ds-on-surface);
  line-height: 1.8;
  word-break: break-word;

  :deep img,
  :deep video,
  :deep audio {
    max-width: 100%;
  }

  :deep p {
    margin-top: 0;
  }
}

.image-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.image-item {
  width: 132px;
  height: 132px;
  border-radius: 8px;
  cursor: pointer;
}

.media-preview {
  padding: 16px;
  border: 1px solid rgba(var(--ds-surface-low-rgb), 0.9);
  border-radius: 8px;
  background: rgba(var(--ds-surface-low-rgb), 0.45);
}

.media-player {
  width: 100%;
  outline: none;
}

.video-player {
  max-height: 360px;
  background: #000;
}

.table-action-group {
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;

  :deep .el-divider--vertical {
    margin: 0 8px;
  }
}

.table-action-more {
  display: inline-flex;
  align-items: center;
}

.hidden-tag-selector {
  display: none;
}

.tag-cell {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.empty-text {
  color: var(--text-secondary);
  font-size: 12px;
}

:deep .dropdown-danger {
  color: var(--text-danger);
}
</style>
