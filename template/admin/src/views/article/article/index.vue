<template>
  <div class="article-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">内容管理</h1>
        <p class="page-curator-subtitle">
          支持通过顶部 Tab 快速切换全部、商户和平台内容，并可直接处理商户投稿审核。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        添加内容
      </el-button>
    </header>

    <div class="article-work-surface">
      <div class="article-source-tabs-wrap">
        <el-tabs
          v-model="activeTab"
          class="article-source-tabs"
          @tab-click="handleTabClick"
        >
          <el-tab-pane name="all">
            <template #label>
              <span>全部</span>
            </template>
          </el-tab-pane>
          <el-tab-pane name="merchant">
            <template #label>
              <span class="article-source-tab-label">
                <span>商户</span>
                <el-badge
                  v-if="pendingTabs.merchant > 0"
                  :value="pendingTabs.merchant"
                  :max="99"
                  class="article-source-tab-badge"
                />
              </span>
            </template>
          </el-tab-pane>
          <el-tab-pane name="platform">
            <template #label>
              <span>平台</span>
            </template>
          </el-tab-pane>
        </el-tabs>
      </div>

      <el-form
        ref="searchForm"
        :model="searchForm"
        inline
        class="search-form article-search-form"
        @submit.prevent
      >
        <el-form-item v-if="isMerchantSource" label="审核状态">
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
        <el-form-item label="内容标题">
          <el-input
            v-model="searchForm.title"
            clearable
            placeholder="请输入内容标题"
            class="search-input"
            prefix-icon="el-icon-search"
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
            v-model="searchForm.category_path"
            :options="categoryList"
            :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
            placeholder="请选择分类"
            class="form-width"
            clearable
            filterable
            @change="handleCategoryChange"
          />
        </el-form-item>
        <el-form-item v-if="isMerchantSource" label="所属商户">
          <el-select
            v-model="searchForm.mer_id"
            filterable
            remote
            reserve-keyword
            clearable
            placeholder="输入商户名称搜索"
            :remote-method="remoteMerchant"
            :loading="merchantLoading"
            class="search-select-wide"
          >
            <el-option
              v-for="item in merchantOptions"
              :key="item.id"
              :label="`${item.mer_name}（ID：${item.id}）`"
              :value="item.id"
            />
          </el-select>
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
          <el-button
            type="primary"
            class="btn-curator-primary"
            icon="el-icon-search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            icon="el-icon-refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <el-table
        v-loading="loading"
        :data="articleList"
        row-key="id"
        class="article-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="80" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              :preview-src-list="[scope.row.cover]"
              class="cover-img"
              fit="cover"
            />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="140" show-overflow-tooltip />
        <el-table-column label="类型" width="90" align="center">
          <template #default="scope">
            <el-tag :type="getTypeTagColor(scope.row.content_type)">
              {{ scope.row.content_type_text || getTypeName(scope.row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="来源" width="100" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.mer_id > 0 ? 'warning' : 'info'">
              {{ scope.row.source_text || getSourceLabel(scope.row.mer_id) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          v-if="isMerchantSource"
          label="所属商户"
          min-width="180"
          show-overflow-tooltip
        >
          <template #default="scope">
            <span class="merchant-name">{{ formatMerchantName(scope.row) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="articleCategory.name" label="分类" width="120" align="center" />
        <el-table-column label="标签" min-width="170">
          <template #default="scope">
            <div class="tag-cell">
              <TagPreview
                v-for="tag in scope.row.tag_list || []"
                :key="tag.id"
                :tag="tag"
              />
              <span v-if="!scope.row.tag_list || !scope.row.tag_list.length" class="empty-text">-</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="120" align="center">
          <template #default="scope">
            <div v-if="isQuickEditingPrice(scope.row)" class="quick-price-editor">
              <el-input-number
                :ref="el => setQuickPriceInputRef(el, scope.row.id)"
                v-model="quickEdit.value"
                :min="0"
                :max="99999999.99"
                :precision="2"
                :step="1"
                :disabled="quickEdit.loading"
                size="mini"
                controls-position="right"
                class="quick-price-input"
                @keyup.enter="submitQuickPrice(scope.row)"
                @blur="submitQuickPrice(scope.row)"
              />
            </div>
            <div v-else class="quick-price-display" @click="startQuickPriceEdit(scope.row)">
              <span v-if="Number(scope.row.price || 0) > 0" class="price-text">¥{{ formatPrice(scope.row.price) }}</span>
              <el-tag v-else type="success">免费</el-tag>
              <LegacyIcon name="el-icon-edit-outline" class=" quick-price-icon" />
            </div>
          </template>
        </el-table-column>
        <el-table-column label="试读" width="70" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.is_trial" type="warning">试读</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="view_count" label="浏览量" width="90" align="center" />
        <el-table-column v-if="isMerchantSource" label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getAuditTagType(scope.row.audit_status)">
              {{ scope.row.audit_status_text || getAuditLabel(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          v-if="isMerchantSource"
          label="审核备注"
          min-width="160"
          show-overflow-tooltip
        >
          <template #default="scope">
            <span class="audit-remark">{{ scope.row.audit_remark || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
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
        <el-table-column label="操作" width="230" fixed="right" align="center">
          <template #default="scope">
            <div class="table-action-group">
              <a @click="handleDetail(scope.row)">详情</a>
              <el-divider direction="vertical" />
              <a
                v-if="scope.row.mer_id > 0 && scope.row.audit_status === 0"
                @click="handleAudit(scope.row)"
              >审核</a>
              <el-divider
                v-if="scope.row.mer_id > 0 && scope.row.audit_status === 0"
                direction="vertical"
              />
              <a @click="handleEdit(scope.row.id)">编辑</a>
              <el-divider direction="vertical" />
              <el-dropdown
                trigger="click"
                class="table-action-more"
                @command="command => handleMoreCommand(command, scope.row)"
              >
                <a href="javascript:;" @click.prevent>
                  更多<LegacyIcon name="el-icon-arrow-down" class=" el-icon--right" />
                </a>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item
                      v-if="Number(scope.row.mer_id || 0) === 0"
                      command="tag"
                    >编辑标签</el-dropdown-item>
                    <el-dropdown-item
                      v-if="showForceOfflineAction(scope.row)"
                      command="force_offline"
                      class="dropdown-danger"
                    >强制下架</el-dropdown-item>
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
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <el-dialog
      v-model="auditVisible"
      title="审核商户内容"
      width="520px"
      :close-on-click-modal="false"
    >
      <el-form :model="auditForm" label-width="90px">
        <el-form-item label="内容标题">
          <span>{{ auditTarget.title || '-' }}</span>
        </el-form-item>
        <el-form-item label="所属商户">
          <span>{{ formatMerchantName(auditTarget) }}</span>
        </el-form-item>
        <el-form-item label="审核结果">
          <el-radio-group v-model="auditForm.audit_status">
            <el-radio :label="1">审核通过</el-radio>
            <el-radio :label="2">审核驳回</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="auditForm.audit_status === 2" label="驳回原因">
          <el-input
            v-model="auditForm.audit_remark"
            type="textarea"
            :rows="3"
            placeholder="请填写驳回原因（必填）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="auditVisible = false">取 消</el-button>
          <el-button type="primary" :loading="auditLoading" @click="submitAudit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <TagSelector
      ref="tagQuickSelector"
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
                          <el-tag :type="getTypeTagColor(detailData.content_type)">
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
                          <el-tag :type="detailData.mer_id > 0 ? 'warning' : 'info'">
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
                          <el-tag :type="detailData.status === 1 ? 'success' : 'info'">
                            {{ detailData.status === 1 ? '显示' : '隐藏' }}
                          </el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">审核状态</span>
                        <span class="detail-info-item__value">
                          <el-tag :type="getAuditTagType(detailData.audit_status)">
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
                          <el-tag v-else type="success">免费</el-tag>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">原价</span>
                        <span class="detail-info-item__value">{{ Number(detailData.original_price || 0) > 0 ? `¥${detailData.original_price}` : '-' }}</span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">试读试看</span>
                        <span class="detail-info-item__value">
                          <el-tag v-if="detailData.is_trial" type="warning">支持</el-tag>
                          <span v-else>不支持</span>
                        </span>
                      </div>
                      <div class="detail-info-item">
                        <span class="detail-info-item__label">浏览量</span>
                        <span class="detail-info-item__value">{{ detailData.view_count || 0 }}</span>
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
import { computed, getCurrentInstance, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getArticleList as getArticleListApi,
  getContentTypes,
  articleDelete,
  articleSetShow,
  getArticleCategoryTree,
  articleRead,
  auditArticle,
  getArticlePendingCount,
  articleQuickUpdate
} from '@/api/article'
import { getMerchantList } from '@/api/merchant'
import { saveTagRelation } from '@/api/tag'
import TagSelector from '@/components/TagSelector/index'
import TagPreview from '@/components/TagPreview/index'
import { normalizeResourceUrl } from '@/utils'

defineOptions({
  name: 'ContentManage'
})

const CONTENT_TYPE_MAP = {
  1: { label: '图文', color: 'primary' },
  2: { label: '文字', color: '' },
  3: { label: '小说', color: 'warning' },
  4: { label: '图片', color: 'success' },
  5: { label: '音频', color: 'danger' },
  6: { label: '视频', color: 'info' }
}

const TAB_STORAGE_KEY = 'admin.article.list.activeTab'
const VALID_TABS = ['all', 'merchant', 'platform']

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const prompt = proxy.$prompt

const searchForm = reactive(getDefaultSearchForm())
const pendingTabs = reactive({
  all: 0,
  merchant: 0,
  platform: 0
})
const auditForm = reactive({
  audit_status: 1,
  audit_remark: ''
})
const quickEdit = reactive({
  id: 0,
  field: '',
  value: 0,
  loading: false
})

const loading = ref(false)
const activeTab = ref('all')
const articleList = ref([])
const categoryList = ref([])
const contentTypes = ref([])
const merchantOptions = ref([])
const merchantLoading = ref(false)
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const auditVisible = ref(false)
const auditLoading = ref(false)
const auditTarget = ref({})
const detailDrawerVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const routeDetailId = ref('')
const tagEditLoading = ref(false)
const tagEditTarget = ref({})
const tagEditIds = ref([])
const tagQuickSelector = ref(null)
const quickPriceInputRefs = ref({})

function getDefaultSearchForm(contentSource = 'all') {
  return {
    title: '',
    content_type: '',
    category_path: [],
    article_category_id: '',
    content_source: contentSource,
    mer_id: '',
    audit_status: '',
    is_trial: '',
    status: ''
  }
}

const isMerchantSource = computed(() => activeTab.value === 'merchant')
const detailContentType = computed(() => Number(detailData.value && detailData.value.content_type ? detailData.value.content_type : 0))
const isDetailPaid = computed(() => Number(detailData.value && detailData.value.price ? detailData.value.price : 0) > 0)
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

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

function resolveInitialTab() {
  const routeTab = route.query.tab || route.query.content_source
  if (VALID_TABS.includes(routeTab)) {
    return routeTab
  }
  if (route.query.audit_status !== undefined && route.query.audit_status !== '') {
    return 'merchant'
  }
  try {
    const cachedTab = window.localStorage.getItem(TAB_STORAGE_KEY)
    if (VALID_TABS.includes(cachedTab)) {
      return cachedTab
    }
  } catch (error) {
    // ignore localStorage error
  }
  return 'all'
}

function persistActiveTab(tab) {
  try {
    window.localStorage.setItem(TAB_STORAGE_KEY, tab)
  } catch (error) {
    // ignore localStorage error
  }
}

function parseQueryNumber(value) {
  if (value === '' || value === null || value === undefined) {
    return ''
  }
  const parsedValue = Number(value)
  return Number.isNaN(parsedValue) ? '' : parsedValue
}

function applyRouteFilters() {
  const { title, audit_status: auditStatus, mer_id: merId, status, content_type: contentType } = route.query
  if (title) {
    searchForm.title = title
  }
  const parsedContentType = parseQueryNumber(contentType)
  if (parsedContentType !== '') {
    searchForm.content_type = parsedContentType
  }
  const parsedStatus = parseQueryNumber(status)
  if (parsedStatus !== '') {
    searchForm.status = parsedStatus
  }
  if (activeTab.value === 'merchant') {
    const parsedAuditStatus = parseQueryNumber(auditStatus)
    if (parsedAuditStatus !== '') {
      searchForm.audit_status = parsedAuditStatus
    }
    const parsedMerId = parseQueryNumber(merId)
    if (parsedMerId !== '') {
      searchForm.mer_id = parsedMerId
    }
  }
}

async function initData() {
  await Promise.all([
    loadContentTypes(),
    loadCategoryList(),
    fetchPendingCount()
  ])
  getArticleList()
}

function handleTabClick(tab) {
  handleTabChange(tab.name)
}

function handleTabChange(tab) {
  activeTab.value = VALID_TABS.includes(tab) ? tab : 'all'
  searchForm.content_source = activeTab.value
  persistActiveTab(activeTab.value)
  if (!isMerchantSource.value) {
    searchForm.mer_id = ''
    searchForm.audit_status = ''
    merchantOptions.value = []
  }
  page.value = 1
  getArticleList()
}

function handleCategoryChange(val) {
  if (val && val.length) {
    searchForm.article_category_id = val[val.length - 1]
    return
  }
  searchForm.article_category_id = ''
}

function remoteMerchant(query) {
  if (!query) {
    merchantOptions.value = []
    return
  }
  merchantLoading.value = true
  getMerchantList({ page: 1, limit: 50, mer_name: query })
    .then(res => {
      merchantOptions.value = res.data.list || []
    })
    .finally(() => {
      merchantLoading.value = false
    })
}

function loadContentTypes() {
  return getContentTypes().then(res => {
    contentTypes.value = res.data.list || []
  })
}

function loadCategoryList() {
  return getArticleCategoryTree().then(res => {
    categoryList.value = res.data || []
  })
}

function fetchPendingCount() {
  return getArticlePendingCount()
    .then(res => {
      const data = res.data || {}
      const articlePending = data.article_pending || data.tabs || {}
      pendingTabs.all = Number(articlePending.all || 0)
      pendingTabs.merchant = Number(articlePending.merchant || data.article || 0)
      pendingTabs.platform = Number(articlePending.platform || 0)
    })
    .catch(() => {
      pendingTabs.all = 0
      pendingTabs.merchant = 0
      pendingTabs.platform = 0
    })
}

function getArticleList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  delete params.category_path
  return getArticleListApi(params)
    .then(res => {
      articleList.value = res.data.list || []
      total.value = res.data.total || 0
    })
    .finally(() => {
      loading.value = false
    })
}

function getTypeName(type) {
  return CONTENT_TYPE_MAP[type] ? CONTENT_TYPE_MAP[type].label : '未知'
}

function getTypeTagColor(type) {
  return CONTENT_TYPE_MAP[type] ? CONTENT_TYPE_MAP[type].color : ''
}

function getSourceLabel(merId) {
  return Number(merId) > 0 ? '商户发布' : '平台发布'
}

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

function getAuditLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已驳回', 3: '已下架' }
  return map[status] || '未知'
}

function getAuditTagType(status) {
  const map = { 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }
  return map[status] || 'info'
}

function handleSearch() {
  page.value = 1
  getArticleList()
}

function handleReset() {
  Object.assign(searchForm, getDefaultSearchForm(activeTab.value))
  merchantOptions.value = []
  page.value = 1
  getArticleList()
}

function handleAdd() {
  router.push('/article/article/create')
}

function handleEdit(id) {
  router.push(`/article/article/create?id=${id}`)
}

function isQuickEditingPrice(row) {
  return Number(quickEdit.id) === Number(row && row.id) && quickEdit.field === 'price'
}

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
    const input = quickPriceInputRefs.value[row.id]
    if (input && typeof input.focus === 'function') {
      input.focus()
      return
    }
    if (input && input.$el) {
      const innerInput = input.$el.querySelector('input')
      if (innerInput && typeof innerInput.focus === 'function') {
        innerInput.focus()
      }
    }
  })
}

function setQuickPriceInputRef(el, id) {
  if (!id) {
    return
  }
  if (el) {
    quickPriceInputRefs.value[id] = el
    return
  }
  delete quickPriceInputRefs.value[id]
}

function resetQuickEdit() {
  Object.assign(quickEdit, {
    id: 0,
    field: '',
    value: 0,
    loading: false
  })
}

function cancelQuickEdit() {
  if (quickEdit.loading) {
    return
  }
  resetQuickEdit()
}

function submitQuickPrice(row) {
  if (!row || !row.id || quickEdit.loading) {
    return
  }
  const price = Number(quickEdit.value || 0)
  if (Number.isNaN(price) || price < 0) {
    message.warning('请输入正确的金额')
    return
  }
  if (Number(price.toFixed(2)) === Number(Number(row.price || 0).toFixed(2))) {
    resetQuickEdit()
    return
  }
  quickEdit.loading = true
  articleQuickUpdate(row.id, { price: price.toFixed(2) })
    .then(res => {
      message.success(res.message || '修改成功')
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

function handleMoreCommand(command, row) {
  if (command === 'tag') {
    openTagEdit(row)
    return
  }
  if (command === 'delete') {
    handleDelete(row.id)
  }
  if (command === 'force_offline') {
    handleForceOffline(row)
  }
}

function openTagEdit(row) {
  tagEditTarget.value = row || {}
  tagEditIds.value = Array.isArray(row.tag_ids) ? row.tag_ids.slice() : []
  nextTick(() => {
    if (tagQuickSelector.value) {
      tagQuickSelector.value.openSelectDialog()
    }
  })
}

function submitTagEdit(tagIds = tagEditIds.value) {
  if (!tagEditTarget.value.id) return
  tagEditLoading.value = true
  saveTagRelation({
    relation_type: 'article',
    relation_id: tagEditTarget.value.id,
    tag_type: 1,
    tag_ids: Array.isArray(tagIds) ? tagIds : tagEditIds.value
  }).then(res => {
    message.success(res.message || '保存成功')
    getArticleList()
  }).finally(() => {
    tagEditLoading.value = false
  })
}

function handleDetail(row) {
  openDetailDrawer(row.id, row)
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    routeDetailId.value = ''
    return
  }
  if (value === routeDetailId.value) {
    return
  }
  routeDetailId.value = value
  openDetailDrawer(value)
}

function openDetailDrawer(id, row = {}) {
  detailData.value = { ...row, id }
  detailDrawerVisible.value = true
  detailLoading.value = true
  articleRead(id)
    .then(res => {
      const detail = res.data || {}
      detailData.value = {
        ...row,
        ...detail,
        articleCategory: detail.articleCategory || row.articleCategory || null,
        merchant: detail.merchant || row.merchant || null
      }
    })
    .catch(() => {
      message.error('获取内容详情失败')
    })
    .finally(() => {
      detailLoading.value = false
    })
}

function resolvePreviewHtml(section) {
  if (!detailData.value) {
    return ''
  }
  if (section.mode === 'trial') {
    return detailData.value.trial_content || ''
  }
  return detailData.value.content || ''
}

function resolvePreviewImages(section) {
  if (!detailData.value) {
    return []
  }
  const images = section.mode === 'trial' ? detailData.value.trial_images : detailData.value.images
  return Array.isArray(images) ? images.filter(Boolean) : []
}

function resolvePreviewMediaUrl(section) {
  if (!detailData.value) {
    return ''
  }
  return normalizeResourceUrl(section.mode === 'trial'
    ? detailData.value.trial_media_url
    : detailData.value.media_url)
}

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

function handleDrawerClose(done) {
  detailData.value = null
  done()
}

function handleDelete(id) {
  confirm('确定要删除该内容吗？删除后不可恢复。', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    articleDelete(id).then(res => {
      message.success(res.message)
      getArticleList()
      fetchPendingCount()
    })
  }).catch(error => {
    if (error && error.message) {
      message.error(error.message)
    }
  })
}

function handleStatusChange(row) {
  articleSetShow(row.id).then(res => {
    message.success(res.message)
  }).catch(error => {
    row.status = row.status === 1 ? 0 : 1
    message.error(error.message)
  })
}

function showForceOfflineAction(row) {
  return isMerchantSource.value && Number(row.mer_id) > 0 && Number(row.audit_status) === 1
}

function handleForceOffline(row) {
  prompt('强制下架后，该内容将从用户端隐藏。请填写下架原因。', '强制下架确认', {
    confirmButtonText: '确定下架',
    cancelButtonText: '取消',
    inputType: 'textarea',
    inputPlaceholder: '请输入下架原因',
    inputValidator: value => {
      if (!value || !value.trim()) {
        return '请填写下架原因'
      }
      return true
    }
  }).then(({ value }) => {
    return auditArticle(row.id, {
      audit_status: 3,
      audit_remark: value.trim()
    }).then(() => {
      message.success('强制下架成功')
      return Promise.all([
        getArticleList(),
        fetchPendingCount()
      ])
    })
  }).catch(() => {})
}

function handleAudit(row) {
  auditTarget.value = row
  Object.assign(auditForm, {
    audit_status: 1,
    audit_remark: ''
  })
  auditVisible.value = true
}

function submitAudit() {
  if (auditForm.audit_status === 2 && !auditForm.audit_remark.trim()) {
    message.warning('驳回时请填写驳回原因')
    return
  }
  auditLoading.value = true
  auditArticle(auditTarget.value.id, auditForm)
    .then(() => {
      message.success('审核操作成功')
      auditVisible.value = false
      return Promise.all([
        getArticleList(),
        fetchPendingCount()
      ])
    })
    .finally(() => {
      auditLoading.value = false
    })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getArticleList()
}

function handleCurrentChange(val) {
  page.value = val
  getArticleList()
}

function formatDuration(sec) {
  if (!sec) {
    return ''
  }
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  if (h > 0) {
    return `${h}时${m}分${s}秒`
  }
  if (m > 0) {
    return `${m}分${s}秒`
  }
  return `${s}秒`
}

function formatPrice(price) {
  const value = Number(price || 0)
  return value.toFixed(2)
}

onBeforeMount(() => {
  const initialTab = resolveInitialTab()
  activeTab.value = initialTab
  Object.assign(searchForm, getDefaultSearchForm(initialTab))
  applyRouteFilters()
  persistActiveTab(initialTab)
  applyRouteDetailQuery(route.query.detail_id)
  initData()
})
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
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
  max-width: 560px;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
}

.article-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 15px;
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

.article-source-tabs-wrap {
  margin-bottom: 12px;
}

.article-source-tab-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.article-source-tab-badge {
  display: inline-flex;
}

:deep(.article-source-tabs .el-tabs__header) {
  margin-bottom: 12px;
}

:deep(.article-source-tabs .el-tabs__item) {
  height: 40px;
  line-height: 40px;
  font-weight: 500;
}

:deep(.article-source-tabs .el-badge__content.is-fixed) {
  top: 10px;
  right: -12px;
}

.article-search-form {
  margin-bottom: 8px;
}

.article-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }

  a {
    color: var(--ds-primary);
    cursor: pointer;
  }
}

.search-input {
  width: 200px;
}

.search-select {
  width: 140px;
}

.search-select-sm {
  width: 120px;
}

.search-select-wide {
  width: 220px;
}

.form-width {
  width: 200px;
}

.cover-img {
  width: 44px;
  height: 44px;
  border-radius: 4px;
  cursor: pointer;
}

.no-cover {
  font-size: 12px;
  color: var(--text-disabled, #c0c4cc);
}

.price-text {
  color: var(--text-danger, #f56c6c);
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
  color: var(--ds-on-surface-variant);
  font-size: 13px;
  opacity: 0;
  transition: opacity .2s;
}

.quick-price-display:hover .quick-price-icon {
  opacity: 1;
}

.quick-price-editor {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.quick-price-input {
  width: 96px;
}

:deep(.quick-price-input .el-input__inner) {
  padding-left: 6px;
  padding-right: 34px;
  font-size: 12px;
}

.merchant-name,
.audit-remark {
  color: var(--ds-on-surface);
}

.pagination-wrap {
  padding: 16px 24px;
  display: flex;
  justify-content: flex-end;
}

.drawer-content {
  height: 100%;
  padding: 0 20px 20px;
  overflow-y: auto;
  box-sizing: border-box;
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

.rich-content {
  min-height: 120px;
  padding: 16px;
  border: 1px solid rgba(var(--ds-surface-low-rgb), 0.9);
  border-radius: 8px;
  background: rgba(var(--ds-surface-low-rgb), 0.45);
  color: var(--ds-on-surface);
  line-height: 1.8;
  word-break: break-word;

  :deep(img),
  :deep(video),
  :deep(audio) {
    max-width: 100%;
  }

  :deep(p) {
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

  :deep(.el-divider--vertical) {
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
  color: var(--text-secondary, #909399);
  font-size: 12px;
}

:deep(.dropdown-danger) {
  color: var(--text-danger, #f56c6c);
}
</style>
