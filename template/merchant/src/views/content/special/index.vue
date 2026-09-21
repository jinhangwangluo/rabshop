<template>
  <div class="special-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">专题管理</h1>
        <p class="page-curator-subtitle">
          管理付费专题，支持内容组合、价格设置及热门推荐配置。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        :icon="Plus"
        @click="handleAdd"
      >
        添加专题
      </el-button>
    </header>

    <div class="special-work-surface">
      <el-card shadow="never" class="search-card">
        <el-form
          ref="searchFormRef"
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
            <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
            <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <el-card shadow="never" class="table-card">
        <el-table
          v-loading="loading"
          :data="specialList"
          row-key="id"
          class="special-curator-table"
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
              <el-button type="text" @click="handleDetail(row.id)">
                {{ row.content_count || 0 }} 个
              </el-button>
            </template>
          </el-table-column>
          <el-table-column label="热门" width="70" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.is_hot" type="warning" >热门</el-tag>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="审核状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="getAuditTagType(row.audit_status)" >
                {{ row.audit_status_text || getAuditLabel(row.audit_status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column
            label="不通过原因"
            min-width="160"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              {{ row.audit_remark || '-' }}
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
      title="专题详情"
      direction="rtl"
      size="880px"
      :wrapper-closable="true"
      @close="handleDrawerClose"
    >
      <div v-loading="detailLoading" class="special-detail-drawer">
        <template v-if="detailData && detailData.id">
          <div class="special-detail-hero">
            <el-image
              v-if="detailData.cover"
              :src="detailData.cover"
              :preview-src-list="[detailData.cover]"
              class="special-detail-hero__cover"
              fit="cover"
            />
            <div v-else class="special-detail-hero__cover special-detail-hero__cover--empty">
              <LegacyIcon name="el-icon-picture-outline" />
            </div>
            <div class="special-detail-hero__main">
              <div class="special-detail-hero__title">{{ detailData.title || '-' }}</div>
              <div class="special-detail-hero__tags">
                <el-tag :type="Number(detailData.special_type) === 1 ? 'danger' : 'success'" >
                  {{ Number(detailData.special_type) === 1 ? '付费专题' : '免费专题' }}
                </el-tag>
                <el-tag v-if="detailData.is_hot" type="warning" >热门推荐</el-tag>
                <el-tag :type="getAuditTagType(detailData.audit_status)" >
                  {{ detailData.audit_status_text || getAuditLabel(detailData.audit_status) }}
                </el-tag>
                <el-tag :type="Number(detailData.status) === 1 ? 'success' : 'info'" >
                  {{ Number(detailData.status) === 1 ? '显示中' : '已隐藏' }}
                </el-tag>
              </div>
              <div class="special-detail-hero__desc">{{ detailData.description || '暂无专题简介' }}</div>
            </div>
          </div>

          <el-tabs v-model="detailActiveTab" class="special-detail-tabs">
            <el-tab-pane label="基本信息" name="base">
              <div class="special-detail-sections">
                <section class="special-detail-section">
                  <div class="special-detail-section__title">基础信息</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题ID</span>
                      <span class="special-detail-item__value">{{ detailData.id || '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题标题</span>
                      <span class="special-detail-item__value">{{ detailData.title || '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题类型</span>
                      <span class="special-detail-item__value">{{ Number(detailData.special_type) === 1 ? '付费' : '免费' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">所属分类</span>
                      <span class="special-detail-item__value">{{ formatCategoryName(detailData) }}</span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">专题标签</span>
                      <span class="special-detail-item__value special-detail-tags">
                        <TagPreview
                          v-for="tag in detailData.tag_list || []"
                          :key="tag.id"
                          :tag="tag"
                        />
                        <span v-if="!detailData.tag_list || !detailData.tag_list.length">-</span>
                      </span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">关键词</span>
                      <span class="special-detail-item__value">{{ detailData.keywords || '-' }}</span>
                    </div>
                  </div>
                </section>

                <section class="special-detail-section">
                  <div class="special-detail-section__title">售卖与数据</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">售价</span>
                      <span class="special-detail-item__value price-text">{{ Number(detailData.special_type) === 1 ? `¥${detailData.price || 0}` : '免费' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">原价</span>
                      <span class="special-detail-item__value">{{ Number(detailData.original_price) > 0 ? `¥${detailData.original_price}` : '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">内容数</span>
                      <span class="special-detail-item__value">{{ detailData.content_count || (detailData.contents || []).length || 0 }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">排序</span>
                      <span class="special-detail-item__value">{{ detailData.sort || 0 }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">浏览量</span>
                      <span class="special-detail-item__value">{{ detailData.view_count || detailData.visit || 0 }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">购买量</span>
                      <span class="special-detail-item__value">{{ detailData.buy_count || detailData.pay_count || 0 }}</span>
                    </div>
                  </div>
                </section>

                <section class="special-detail-section">
                  <div class="special-detail-section__title">审核与状态</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">审核状态</span>
                      <span class="special-detail-item__value">{{ detailData.audit_status_text || getAuditLabel(detailData.audit_status) }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">热门状态</span>
                      <span class="special-detail-item__value">{{ detailData.is_hot ? '热门推荐' : '普通专题' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">显示状态</span>
                      <span class="special-detail-item__value">{{ Number(detailData.status) === 1 ? '显示' : '隐藏' }}</span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">审核备注</span>
                      <span class="special-detail-item__value">{{ detailData.audit_remark || '-' }}</span>
                    </div>
                  </div>
                </section>

                <section class="special-detail-section">
                  <div class="special-detail-section__title">时间与简介</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">创建时间</span>
                      <span class="special-detail-item__value">{{ detailData.create_time || '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">更新时间</span>
                      <span class="special-detail-item__value">{{ detailData.update_time || '-' }}</span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">专题简介</span>
                      <span class="special-detail-item__value special-detail-item__value--desc">{{ detailData.description || '-' }}</span>
                    </div>
                  </div>
                </section>
              </div>
            </el-tab-pane>
            <el-tab-pane :label="`内容列表（${(detailData.contents || []).length}）`" name="contents">
              <div class="special-detail-content-toolbar">
                <div class="special-detail-content-toolbar__text">
                  已关联 {{ (detailData.contents || []).length }} 个内容
                </div>
                <el-button
                  type="primary"
                  :icon="Plus"
                  class="btn-curator-primary"
                  @click="openContentSelector"
                >添加内容</el-button>
              </div>
              <el-empty v-if="!(detailData.contents || []).length" description="暂无内容" />
              <el-table
                v-else
                :data="detailData.contents"
                class="special-detail-content-table"

                row-key="article_id"
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
                <el-table-column label="内容标题" min-width="130" show-overflow-tooltip>
                  <template #default="{ row }">
                    {{ row.article ? row.article.title : (row.title || '-') }}
                  </template>
                </el-table-column>
                <el-table-column label="类型" width="80" align="center">
                  <template #default="{ row }">
                    <el-tag
                      :type="getTypeTagColor(row.article ? row.article.content_type : row.type)"
                      size="mini"
                    >
                      {{ getTypeName(row.article ? row.article.content_type : row.type) }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="价格" width="90" align="center">
                  <template #default="{ row }">
                    <span v-if="row.article && Number(row.article.price) > 0" class="price-text">
                      ¥{{ row.article.price }}
                    </span>
                    <el-tag v-else type="success" size="mini">免费</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="80" fixed="right" align="center">
                  <template #default="{ row, $index }">
                    <a class="remove-btn" @click="removeDetailContent($index)">移除</a>
                  </template>
                </el-table-column>
              </el-table>
            </el-tab-pane>
          </el-tabs>
        </template>
        <el-empty v-else-if="!detailLoading" description="暂无详情数据" />
      </div>
    </el-drawer>

    <el-dialog
      v-model="selectorVisible"
      title="选择内容"
      width="900px"
      :close-on-click-modal="false"
      append-to-body
      @open="onSelectorOpen"
      @close="onSelectorClose"
    >
      <div class="selector-search">
        <el-form
          ref="selectorSearchForm"
          :model="selectorSearch"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="内容标题">
            <el-input
              v-model="selectorSearch.title"
              placeholder="搜索内容标题"
              clearable
              :prefix-icon="Search"
              class="selector-input"
              @keyup.enter="loadSelectorList"
            />
          </el-form-item>
          <el-form-item label="内容类型">
            <el-select
              v-model="selectorSearch.content_type"
              placeholder="全部类型"
              clearable
              class="selector-select"
            >
              <el-option
                v-for="item in contentTypeOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadSelectorList">搜索</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table
        ref="selectorTable"
        v-loading="selectorLoading"
        :data="selectorList"

        row-key="id"
        @selection-change="handleSelectorSelection"
      >
        <el-table-column type="selection" width="45" align="center" :selectable="isSelectable" />
        <el-table-column prop="id" label="ID" width="60" align="center" />
        <el-table-column label="封面" width="65" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.cover"
              :src="row.cover"
              class="mini-cover"
              fit="cover"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
        <el-table-column label="类型" width="75" align="center">
          <template #default="{ row }">
            <el-tag :type="getTypeTagColor(row.content_type)" size="mini">
              {{ getTypeName(row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="90" align="center">
          <template #default="{ row }">
            <span v-if="row.price > 0" class="price-text">¥{{ row.price }}</span>
            <el-tag v-else type="success" size="mini">免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag v-if="isAlreadySelected(row.id)" type="info" size="mini">已选</el-tag>
            <el-tag v-else type="success" size="mini">可选</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <div class="selector-page">
        <el-pagination
          v-model:current-page="selectorPage"
          v-model:page-size="selectorLimit"
          :page-sizes="[10, 20]"
          layout="total, sizes, prev, pager, next"
          :total="selectorTotal"
          @size-change="handleSelectorSizeChange"
          @current-change="handleSelectorPageChange"
        />
      </div>

      <template #footer>
        <div>
          <span class="selector-tip">已选 {{ tempSelected.length }} 项</span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" :loading="contentSaving" @click="confirmContentSelection">确认添加</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import {
  getSpecialList,
  specialRead,
  specialDelete,
  specialSetShow,
  specialSave,
  searchSpecialContent
} from '@/api/special'
import { saveTagRelation } from '@/api/tag'
import TagSelector from '@/components/TagSelector/index'
import TagPreview from '@/components/TagPreview/index'

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

const contentTypeOptions = Object.entries(CONTENT_TYPE_MAP).map(([value, info]) => ({
  value: Number(value),
  label: info.label
}))

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
const detailDrawerVisible = ref(false)
const detailLoading = ref(false)
const detailActiveTab = ref('base')
const detailData = ref({ contents: [] })
const contentSaving = ref(false)
const selectorVisible = ref(false)
const selectorLoading = ref(false)
const selectorList = ref([])
const selectorTotal = ref(0)
const selectorPage = ref(1)
const selectorLimit = ref(10)
const selectorSearch = reactive({
  title: '',
  content_type: ''
})
const tempSelected = ref([])
const tagEditLoading = ref(false)
const tagEditTarget = ref({})
const tagEditIds = ref([])
const searchFormRef = ref(null)
const tagQuickSelectorRef = ref(null)

// 获取专题列表
function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  Object.keys(params).forEach(key => {
    if (params[key] === '') delete params[key]
  })
  getSpecialList(params)
    .then(res => {
      specialList.value = res.data.list || []
      total.value = res.data.total || res.data.count || 0
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
  searchFormRef.value.resetFields()
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

// 添加专题
function handleAdd() {
  router.push('/merchant/content/special/manage/create')
}

// 编辑专题
function handleEdit(id) {
  router.push(`/merchant/content/special/manage/create?id=${id}`)
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
    relation_type: 'special',
    relation_id: tagEditTarget.value.id,
    tag_type: 1,
    tag_ids: Array.isArray(tagIds) ? tagIds : tagEditIds.value
  }).then(res => {
    ElMessage.success(res.message || '保存成功')
    getList()
  }).finally(() => {
    tagEditLoading.value = false
  })
}

// 查看详情
function handleDetail(id, activeTab = 'base') {
  detailDrawerVisible.value = true
  detailLoading.value = true
  detailActiveTab.value = activeTab
  detailData.value = getDefaultDetailData()
  return specialRead(id)
    .then(res => {
      const data = res.data || {}
      detailData.value = {
        ...data,
        contents: Array.isArray(data.contents) ? data.contents : []
      }
    })
    .finally(() => {
      detailLoading.value = false
    })
}

// 默认详情数据
function getDefaultDetailData() {
  return {
    contents: []
  }
}

// 抽屉关闭
function handleDrawerClose() {
  detailActiveTab.value = 'base'
  detailData.value = getDefaultDetailData()
  selectorVisible.value = false
  tempSelected.value = []
}

// 获取内容 article_id
function getContentArticleId(item = {}) {
  return Number(item.article_id || (item.article && item.article.id) || item.id || 0)
}

// 规范化详情内容列表
function normalizeDetailContents(contents = detailData.value.contents) {
  const normalized = (contents || []).map((item, index) => ({
    ...item,
    article_id: getContentArticleId(item),
    type: item.type || (item.article && item.article.content_type) || 1,
    sort: index + 1
  }))
  detailData.value = {
    ...detailData.value,
    contents: normalized,
    content_count: normalized.length
  }
  return normalized
}

// 构建详情提交数据
function buildDetailSubmitData(contents) {
  return {
    title: detailData.value.title || '',
    category_id: Number(detailData.value.category_id || 0),
    keywords: detailData.value.keywords || '',
    description: detailData.value.description || '',
    cover: detailData.value.cover || '',
    special_type: Number(detailData.value.special_type || 0),
    price: detailData.value.price || 0,
    original_price: detailData.value.original_price || 0,
    sort: Number(detailData.value.sort || 0),
    is_hot: Number(detailData.value.is_hot || 0),
    status: Number(detailData.value.status || 0),
    contents: contents.map((item, index) => ({
      article_id: getContentArticleId(item),
      type: item.type || (item.article && item.article.content_type) || 1,
      sort: index + 1
    }))
  }
}

// 保存详情内容
function saveDetailContents(contents, successMessage) {
  if (!detailData.value.id) return Promise.resolve()
  const normalized = normalizeDetailContents(contents)
  contentSaving.value = true
  return specialSave(detailData.value.id, buildDetailSubmitData(normalized))
    .then(() => {
      ElMessage.success(successMessage)
      return Promise.all([
        handleDetail(detailData.value.id, 'contents'),
        getList()
      ])
    })
    .finally(() => {
      contentSaving.value = false
    })
}

// 移除详情内容
function removeDetailContent(index) {
  ElMessageBox.confirm('确定要从该专题中移除这个内容吗？', '移除确认', {
    confirmButtonText: '确定移除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    const contents = [...(detailData.value.contents || [])]
    contents.splice(index, 1)
    saveDetailContents(contents, '移除成功')
  }).catch(() => {})
}

// 打开内容选择器
function openContentSelector() {
  selectorVisible.value = true
}

// 选择器打开
function onSelectorOpen() {
  Object.assign(selectorSearch, { title: '', content_type: '' })
  selectorPage.value = 1
  tempSelected.value = []
  loadSelectorList()
}

// 选择器关闭
function onSelectorClose() {
  tempSelected.value = []
  selectorList.value = []
}

// 加载可选择内容列表
function loadSelectorList() {
  selectorLoading.value = true
  const params = {
    page: selectorPage.value,
    limit: selectorLimit.value
  }
  if (selectorSearch.title) params.title = selectorSearch.title
  if (selectorSearch.content_type !== '') params.content_type = selectorSearch.content_type

  searchSpecialContent(params)
    .then(res => {
      const data = res.data || {}
      selectorList.value = data.list || []
      selectorTotal.value = data.total || data.count || 0
    })
    .finally(() => {
      selectorLoading.value = false
    })
}

// 选择器分页
function handleSelectorSizeChange(val) {
  selectorLimit.value = val
  selectorPage.value = 1
  loadSelectorList()
}

function handleSelectorPageChange(val) {
  selectorPage.value = val
  loadSelectorList()
}

// 选择变化
function handleSelectorSelection(selection) {
  tempSelected.value = selection
}

// 是否已选择
function isAlreadySelected(articleId) {
  const targetId = Number(articleId)
  return (detailData.value.contents || []).some(item => getContentArticleId(item) === targetId)
}

// 是否可选
function isSelectable(row) {
  return !isAlreadySelected(row.id)
}

// 确认添加内容
function confirmContentSelection() {
  if (tempSelected.value.length === 0) {
    ElMessage.warning('请先选择内容')
    return
  }
  const currentIds = new Set((detailData.value.contents || []).map(item => getContentArticleId(item)))
  const contents = [...(detailData.value.contents || [])]
  let addCount = 0
  tempSelected.value.forEach(article => {
    if (!currentIds.has(Number(article.id))) {
      contents.push({
        article_id: article.id,
        article: {
          id: article.id,
          title: article.title || '',
          cover: article.cover || '',
          content_type: article.content_type || 1,
          price: article.price || 0
        },
        type: article.content_type || 1,
        sort: contents.length + 1
      })
      currentIds.add(Number(article.id))
      addCount++
    }
  })
  if (addCount === 0) {
    ElMessage.info('所选内容均已在专题中')
    return
  }
  saveDetailContents(contents, `已添加 ${addCount} 个内容`).then(() => {
    selectorVisible.value = false
  })
}

// 状态切换
function handleStatusChange(row) {
  specialSetShow(row.id, row.status)
    .then(() => {
      ElMessage.success('状态更新成功')
    })
    .catch(() => {
      row.status = row.status === 1 ? 0 : 1
      ElMessage.error('状态更新失败')
    })
}

// 删除专题
function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该专题吗？删除后将同时移除关联内容，且无法恢复！', '删除确认', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    specialDelete(id).then(() => {
      ElMessage.success('删除成功')
      if (specialList.value.length === 1 && page.value > 1) {
        page.value -= 1
      }
      getList()
    })
  }).catch(() => {})
}

// 类型标签颜色
function getTypeTagColor(type) {
  return (CONTENT_TYPE_MAP[type] || {}).color || ''
}

// 类型名称
function getTypeName(type) {
  return (CONTENT_TYPE_MAP[type] || {}).label || '未知'
}

// 分类名称
function formatCategoryName(row = {}) {
  const categoryId = Number(row.category_id || 0)
  const categoryName = row.category_name || (row.category && row.category.name) || ''
  if (!categoryId) {
    return '-'
  }
  return categoryName ? `${categoryName}（ID：${categoryId}）` : `ID：${categoryId}`
}

// 审核状态文案
function getAuditLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已驳回', 3: '已下架' }
  return map[Number(status)] || '未知'
}

// 审核状态标签颜色
function getAuditTagType(status) {
  const map = { 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }
  return map[Number(status)] || 'info'
}

onMounted(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.special-curator-page {
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

.special-work-surface {
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
  .search-input {
    width: 220px;
  }

  .search-select-sm {
    width: 120px;
  }
}

.special-curator-table {
  :deep .el-table__header th {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep .el-table__row:hover > td {
    background: var(--ds-surface-low) !important;
  }
}

.cover-img {
  width: 50px;
  height: 50px;
  border-radius: 4px;
  display: block;
  margin: 0 auto;
}

.no-cover {
  color: var(--text-placeholder);
  font-size: 12px;
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
}

.free-text {
  color: var(--text-secondary);
}

.pagination-wrap {
  padding-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.mini-cover {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  display: block;
  margin: 0 auto;
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

:deep .el-drawer__header {
  margin-bottom: 0;
  padding: 20px 24px 12px;
  color: var(--ds-on-surface, #303133);
  font-size: 16px;
  font-weight: 600;
}

.special-detail-drawer {
  height: 100%;
  padding: 0 24px 24px;
  box-sizing: border-box;
  overflow-y: auto;
  background: var(--ds-surface-card, #fff);
}

.special-detail-hero {
  display: flex;
  gap: 16px;
  padding: 4px 0 20px;
}

.special-detail-hero__cover {
  width: 120px;
  height: 120px;
  border-radius: 10px;
  flex-shrink: 0;
  overflow: hidden;
}

.special-detail-hero__cover--empty {
  background: var(--ds-surface-low, #f5f7fa);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-placeholder, #c0c4cc);
  font-size: 34px;
}

.special-detail-hero__main {
  flex: 1;
  min-width: 0;
}

.special-detail-hero__title {
  margin-bottom: 12px;
  color: var(--ds-on-surface, #303133);
  font-size: 22px;
  font-weight: 600;
  line-height: 1.4;
}

.special-detail-hero__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.special-detail-hero__desc {
  color: var(--ds-on-surface-variant, #606266);
  font-size: 14px;
  line-height: 1.8;
}

.special-detail-tabs {
  margin-top: 4px;

  :deep .el-tabs__header {
    margin-bottom: 18px;
  }
}

.special-detail-sections {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.special-detail-section {
  padding: 18px 20px;
  border-radius: 8px;
  background: var(--ds-surface-low, #f7f8fa);
}

.special-detail-section__title {
  display: flex;
  align-items: center;
  margin-bottom: 14px;
  color: var(--ds-on-surface, #303133);
  font-size: 15px;
  font-weight: 600;
  line-height: 1;

  &::before {
    content: '';
    width: 3px;
    height: 14px;
    margin-right: 8px;
    border-radius: 999px;
    background: var(--ds-primary, #1456f0);
  }
}

.special-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 24px;
}

.special-detail-item {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  line-height: 1.6;

  &--full {
    grid-column: 1 / -1;
  }
}

.special-detail-item__label {
  width: 86px;
  flex-shrink: 0;
  color: var(--ds-on-surface-variant, #909399);
  font-size: 13px;
}

.special-detail-item__value {
  flex: 1;
  min-width: 0;
  color: var(--ds-on-surface, #303133);
  font-size: 13px;
  word-break: break-all;
}

.special-detail-item__value--desc {
  white-space: pre-wrap;
}

.special-detail-tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.special-detail-content-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 12px;
}

.special-detail-content-toolbar__text {
  color: var(--ds-on-surface-variant, #606266);
  font-size: 13px;
}

.special-detail-content-table {
  background: transparent;
  color: var(--ds-on-surface, #303133);

  &::before,
  :deep .el-table__fixed-right::before,
  :deep .el-table__fixed::before {
    background: var(--ds-outline-variant, #ebeef5);
  }

  :deep th.el-table__cell {
    color: var(--ds-on-surface-variant, #606266);
    font-weight: 600;
    background: var(--ds-surface-low, #f7f8fa);
    border-bottom-color: var(--ds-outline-variant, #ebeef5);
  }

  :deep tr,
  :deep td.el-table__cell {
    background: var(--ds-surface-card, #fff);
    border-bottom-color: var(--ds-outline-variant, #ebeef5);
  }

  :deep .el-table__row:hover > td.el-table__cell,
  :deep .el-table__body tr.hover-row > td.el-table__cell {
    background: var(--ds-surface-low, #f7f8fa) !important;
  }
}

.remove-btn {
  color: var(--text-danger, #f56c6c);
  font-size: 12px;
  cursor: pointer;
}

.selector-search {
  margin-bottom: 12px;
}

.selector-input {
  width: 220px;
}

.selector-select {
  width: 140px;
}

.selector-page {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.selector-tip {
  margin-right: 16px;
  color: var(--ds-on-surface-variant, #606266);
  font-size: 13px;
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
