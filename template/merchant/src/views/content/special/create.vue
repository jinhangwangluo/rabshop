<template>
  <div class="special-create-container">
    <div class="sticky ivu-mb-16">
      <el-card
        shadow="never"
        class="box-card"
      >
        <div class="acea-row space-between">
          <div class="acea-row row-middle">
            <div
              class="font-sm after-line"
              @click="$router.push('/merchant/content/special/manage')"
            >
              <LegacyIcon name="el-icon-arrow-left" />
              <span class="pl10">返回</span>
            </div>
            <span class="ht_title ml10">{{ isEdit ? '编辑专题' : '添加专题' }}</span>
          </div>
        </div>
      </el-card>
    </div>

    <el-card shadow="never" class="form-card">
      <el-form
        ref="formRef"
        v-loading="formLoading"
        :model="form"
        :rules="rules"
        label-width="100px"
      >
        <!-- 基础信息 -->
        <div class="section-title">基础信息</div>

        <el-form-item label="专题标题" prop="title">
          <div class="title-ai-row">
            <el-input
              v-model="form.title"
              placeholder="请输入专题标题"
              maxlength="100"
              show-word-limit
              class="form-input-lg"
            />
            <button
              type="button"
              class="ai-writing-btn"
              :class="{ 'is-loading': aiGenerateLoading }"
              :disabled="aiGenerateLoading"
              @click="handleAiGenerate"
            >
              <LegacyIcon v-if="aiGenerateLoading" name="el-icon-loading" />
              <span>{{ aiGenerateLoading ? '生成中' : 'AI润色写作' }}</span>
            </button>
          </div>
        </el-form-item>

        <el-form-item label="所属分类" prop="category_id">
          <el-cascader
            v-model="form.category_id"
            :options="categoryTree"
            :props="{ checkStrictly: true, emitPath: false, value: 'value', label: 'label', children: 'children' }"
            placeholder="请选择专题分类"
            class="form-input-lg"
            clearable
            filterable
          />
        </el-form-item>

        <el-form-item label="专题标签">
          <TagSelector ref="tagSelector" v-model="form.tag_ids" :tag-type="1" />
        </el-form-item>

        <el-form-item label="专题封面" prop="cover">
          <Upload
            :value="form.cover"
            :limit="1"
            @input="handleCoverChange"
          />
          <div class="upload-tip">建议尺寸：750×400px，支持JPG/PNG格式</div>
        </el-form-item>

        <el-form-item label="专题简介" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入专题简介"
            maxlength="500"
            show-word-limit
            class="form-input-lg"
          />
        </el-form-item>

        <el-form-item label="关键词" prop="keywords">
          <el-input
            v-model="form.keywords"
            placeholder="请输入关键词，多个关键词用逗号分隔"
            class="form-input-lg"
          />
        </el-form-item>

        <el-form-item label="热门推荐">
          <el-switch
            v-model="form.is_hot"
            :active-value="1"
            :inactive-value="0"
            active-text="是"
            inactive-text="否"
          />
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number
            v-model="form.sort"
            :min="0"
            :max="9999"
            controls-position="right"
          />
          <span class="form-tip">数值越大排序越靠前</span>
        </el-form-item>

        <el-form-item label="显示状态">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">显示</el-radio>
            <el-radio :label="0">隐藏</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 价格设置 -->
        <div class="section-title">价格设置</div>

        <el-form-item label="专题类型" prop="special_type">
          <el-radio-group v-model="form.special_type">
            <el-radio :label="0">免费</el-radio>
            <el-radio :label="1">付费</el-radio>
          </el-radio-group>
        </el-form-item>

        <template v-if="form.special_type === 1">
          <el-form-item label="售价" prop="price">
            <el-input-number
              v-model="form.price"
              :min="0.01"
              :max="99999"
              :precision="2"
              controls-position="right"
              placeholder="请设置售价"
            />
            <span class="form-tip">元</span>
          </el-form-item>

          <el-form-item label="原价">
            <el-input-number
              v-model="form.original_price"
              :min="0"
              :max="99999"
              :precision="2"
              controls-position="right"
              placeholder="原价（可选）"
            />
            <span class="form-tip">元（用于显示划线价，可选）</span>
          </el-form-item>
        </template>

        <!-- 专题内容 -->
        <div class="section-title">
          专题内容
          <span class="section-desc">（共 {{ form.contents.length }} 个内容）</span>
        </div>

        <el-form-item label=" " class="contents-item">
          <!-- 已选内容列表 -->
          <div v-if="form.contents.length > 0" class="contents-list">
            <div
              v-for="(item, index) in form.contents"
              :key="item.article_id"
              class="content-item"
            >
              <div class="content-sort">
                <span class="sort-num">{{ index + 1 }}</span>
                <div class="sort-arrows">
                  <el-button
                    type="text"
                    :icon="Top"
                    :disabled="index === 0"
                    @click="moveContent(index, -1)"
                  />
                  <el-button
                    type="text"
                    :icon="Bottom"
                    :disabled="index === form.contents.length - 1"
                    @click="moveContent(index, 1)"
                  />
                </div>
              </div>
              <div class="content-cover">
                <el-image
                  v-if="item.cover"
                  :src="item.cover"
                  class="content-cover-img"
                  fit="cover"
                />
                <div v-else class="content-cover-placeholder">
                  <LegacyIcon name="el-icon-picture-outline" />
                </div>
              </div>
              <div class="content-info">
                <div class="content-title" :title="item.title">{{ item.title }}</div>
                <div class="content-meta">
                  <el-tag :type="getTypeTagColor(item.type)" size="mini">
                    {{ getTypeName(item.type) }}
                  </el-tag>
                  <span v-if="item.price > 0" class="meta-price">¥{{ item.price }}</span>
                  <span v-else class="meta-free">免费</span>
                </div>
              </div>
              <div class="content-sort-input">
                <el-input-number
                  v-model="item.sort"
                  :min="0"
                  size="mini"
                  controls-position="right"
                  style="width: 90px"
                />
              </div>
              <div class="content-actions">
                <el-button
                  type="text"
                  :icon="Delete"
                  class="remove-btn"
                  @click="removeContent(index)"
                >移除</el-button>
              </div>
            </div>
          </div>

          <div v-else class="contents-empty">
            <LegacyIcon name="el-icon-document" />
            <p>暂未添加内容，点击下方按钮选择内容</p>
          </div>

          <el-button
            type="primary"
            plain
            :icon="Plus"
            class="select-content-btn"
            @click="openContentSelector"
          >选择内容</el-button>
        </el-form-item>

        <!-- 关联商品 -->
        <div class="section-title">
          关联商品
          <span class="section-desc">（已选 {{ form.products.length }} 个商品）</span>
        </div>

        <el-form-item label=" " class="products-item">
          <div class="product-association">
            <div class="product-association-header">
              <el-button type="primary" plain :icon="Plus" @click="openProductPicker">选择商品</el-button>
              <el-button v-if="form.products.length" type="text" @click="clearProducts">清空</el-button>
            </div>

            <el-table
              v-if="form.products.length"
              :data="form.products"
              row-key="id"
              border
              class="product-table"
            >
              <el-table-column label="封面" width="90" align="center">
                <template #default="scope">
                  <el-image
                    v-if="scope.row.cover"
                    :src="scope.row.cover"
                    class="mini-cover"
                    fit="cover"
                  />
                  <div v-else class="table-cover-placeholder">暂无</div>
                </template>
              </el-table-column>
              <el-table-column prop="name" label="商品名称" min-width="220" show-overflow-tooltip />
              <el-table-column label="价格" width="110" align="center">
                <template #default="scope">
                  ¥{{ formatProductPrice(scope.row.price) }}
                </template>
              </el-table-column>
              <el-table-column prop="stock" label="库存" width="90" align="center" />
              <el-table-column label="状态" width="120" align="center">
                <template #default="scope">
                  <el-tag :type="getProductStatusType(scope.row)" size="mini">
                    {{ getProductStatusText(scope.row) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="操作" width="90" fixed="right" align="center">
                <template #default="scope">
                  <el-button type="text" :icon="Delete" class="remove-btn" @click="removeProduct(scope.$index)">移除</el-button>
                </template>
              </el-table-column>
            </el-table>

            <div v-else class="product-empty">
              暂未关联商品，选择后会在移动端专题详情页展示
            </div>

            <div class="form-tip">
              商品用于专题详情页导购，只展示已上架且审核通过的商品；已下架或已删除商品会保留在列表中，可手动移除。
            </div>
          </div>
        </el-form-item>

        <!-- 提交按钮 -->
        <el-form-item>
          <el-button
            type="primary"
            :loading="submitLoading"
            @click="handleSubmit"
          >{{ isEdit ? '保存修改' : '立即添加' }}</el-button>
          <el-button @click="$router.push('/merchant/content/special/manage')">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- AI润色结果弹窗 -->
    <el-dialog
      v-model="aiResultVisible"
      title="AI润色结果"
      width="760px"
      destroy-on-close
    >
      <div v-if="aiResult" class="ai-result-dialog">
        <el-alert
          title="建议标签可直接导入当前专题标签。"
          type="info"
          show-icon
          :closable="false"
          class="ai-result-alert"
        />
        <div class="ai-result-apply">
          <span class="ai-result-apply__label">应用字段</span>
          <el-checkbox v-model="aiApplyFields.description">专题介绍</el-checkbox>
          <el-checkbox v-model="aiApplyFields.keywords">关键词</el-checkbox>
          <el-checkbox v-model="aiApplyFields.price">售价</el-checkbox>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">专题介绍</div>
          <div class="ai-result-text">{{ aiResult.description || '暂无专题介绍建议' }}</div>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">关键词</div>
          <div class="ai-result-text">{{ aiResult.keywords || '暂无关键词建议' }}</div>
        </div>

        <div class="ai-result-section ai-result-section--inline">
          <div class="ai-result-section__title">售价建议</div>
          <div class="ai-result-price">¥{{ formatAiPrice(aiResult.price) }}</div>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">
            <span>建议标签</span>
            <el-button
              v-if="aiTagText"
              type="text"
              size="mini"
              :icon="DocumentCopy"
              @click="copyAiTags"
            >复制</el-button>
            <el-button
              v-if="aiTagSuggestions.length"
              type="text"
              size="mini"
              :icon="Download"
              :loading="aiTagImportLoading"
              @click="handleImportAiTags"
            >一键导入</el-button>
          </div>
          <div v-if="aiTagSuggestions.length" class="ai-tag-list">
            <div
              v-for="(item, index) in aiTagSuggestions"
              :key="item.name + index"
              class="ai-tag-item"
              :style="getAiTagStyle(item)"
            >
              <span class="ai-tag-item__name">{{ item.name }}</span>
              <span v-if="item.reason" class="ai-tag-item__reason">{{ item.reason }}</span>
            </div>
          </div>
          <div v-else class="ai-result-empty">暂无标签建议</div>
        </div>
      </div>
      <template #footer>
        <span>
          <el-button @click="aiResultVisible = false">取 消</el-button>
          <el-button type="primary" @click="applyAiResult">应用选中内容</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 内容选择器弹窗 -->
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
          ref="searchForm"
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

      <div class="acea-row row-right page">
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
          <el-button type="primary" @click="confirmContentSelection">确认添加</el-button>
        </div>
      </template>
    </el-dialog>

    <ProductMultiPicker
      v-model:visible="productPickerVisible"
      :selected-products="selectedAvailableProducts"
      @confirm="handleProductConfirm"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Bottom, Delete, Plus, Search, Top } from '@element-plus/icons-vue'
import { specialAdd, specialRead, specialSave, searchSpecialContent, getSpecialCategoryTree, aiGenerateSpecial } from '@/api/special'
import Upload from '@/components/Upload/index'
import TagSelector from '@/components/TagSelector/index'
import ProductMultiPicker from '@/components/ProductMultiPicker/index'
import { importAiTags } from '@/api/tag'

defineOptions({
  name: 'SpecialCreate'
})

const route = useRoute()
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

const formRef = ref(null)
const tagSelectorRef = ref(null)
const formLoading = ref(false)
const submitLoading = ref(false)
const aiGenerateLoading = ref(false)
const aiTagImportLoading = ref(false)
const aiResultVisible = ref(false)
const aiResult = ref(null)
const aiApplyFields = reactive({
  description: true,
  keywords: true,
  price: true
})
const form = reactive({
  title: '',
  cover: '',
  description: '',
  keywords: '',
  special_type: 0,
  price: 0,
  original_price: 0,
  is_hot: 0,
  sort: 0,
  status: 1,
  category_id: 0,
  contents: [],
  tag_ids: [],
  products: []
})

const productPickerVisible = ref(false)

const selectedAvailableProducts = computed(() => form.products.filter(
  (item) => item && item.is_available !== false && !item.is_deleted
))

function openProductPicker() {
  productPickerVisible.value = true
}

/**
 * 合并商品选择结果。
 *
 * 弹窗只展示在售商品，已下架或已删除的历史关联不在弹窗内，需要保留下来由用户手动移除。
 */
function handleProductConfirm(products) {
  const confirmed = Array.isArray(products) ? products : []
  const confirmedIds = new Set(confirmed.map((item) => Number(item.id)))
  const unavailable = form.products.filter(
    (item) => (item.is_available === false || item.is_deleted) && !confirmedIds.has(Number(item.id))
  )
  form.products = [...confirmed, ...unavailable]
}

function removeProduct(index) {
  form.products.splice(index, 1)
}

function clearProducts() {
  form.products = []
}

function formatProductPrice(value) {
  return Number(value || 0).toFixed(2)
}

function getProductStatusText(item) {
  if (item.is_deleted) return '已删除'
  if (Number(item.status) !== 1) return '已下架'
  if (Number(item.audit_status) !== 1) return '审核未通过'
  return '在售'
}

function getProductStatusType(item) {
  if (item.is_deleted) return 'danger'
  if (Number(item.status) !== 1) return 'info'
  if (Number(item.audit_status) !== 1) return 'warning'
  return 'success'
}

const rules = {
  title: [
    { required: true, message: '请输入专题标题', trigger: 'blur' },
    { min: 1, max: 100, message: '标题长度不超过100个字符', trigger: 'blur' }
  ],
  category_id: [
    {
      validator: (_rule, value, callback) => {
        if (Number(value) > 0) {
          callback()
          return
        }
        callback(new Error('请选择专题分类'))
      },
      trigger: 'change'
    }
  ],
  cover: [
    { required: true, message: '请上传专题封面', trigger: 'change' }
  ],
  special_type: [
    { required: true, message: '请选择专题类型', trigger: 'change' }
  ],
  price: [
    {
      validator: (rule, value, callback) => {
        if (form.special_type === 1 && (!value || value <= 0)) {
          callback(new Error('付费专题必须设置售价'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}
const categoryTree = ref([])
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

const isEdit = computed(() => {
  return !!route.query.id
})
const editId = computed(() => {
  return route.query.id ? Number(route.query.id) : null
})
const aiTagSuggestions = computed(() => {
  if (!aiResult.value || !Array.isArray(aiResult.value.tag_suggestions)) {
    return []
  }
  return aiResult.value.tag_suggestions
})
const aiTagText = computed(() => {
  return aiTagSuggestions.value.map(item => item.name).filter(Boolean).join('，')
})

function handleCoverChange(url) {
  form.cover = url
  nextTick(() => {
    if (formRef.value) {
      formRef.value.validateField('cover')
    }
  })
}

function loadCategoryTree() {
  return getSpecialCategoryTree().then(res => {
    categoryTree.value = res.data || []
  }).catch(() => {})
}

function handleAiGenerate() {
  if (!String(form.title || '').trim()) {
    ElMessage.warning('请先输入专题标题')
    return
  }
  aiGenerateLoading.value = true
  aiGenerateSpecial({
    title: form.title,
    keywords: form.keywords,
    description: form.description,
    price: form.price,
    special_type: form.special_type
  }).then(res => {
    aiResult.value = res.data || null
    Object.assign(aiApplyFields, {
      description: true,
      keywords: true,
      price: true
    })
    aiResultVisible.value = true
    ElMessage.success(res.message || 'AI生成成功')
  }).catch(err => {
    ElMessage.error((err && err.message) || 'AI生成失败')
  }).finally(() => {
    aiGenerateLoading.value = false
  })
}

function applyAiResult() {
  if (!aiResult.value) return
  if (aiApplyFields.description) {
    form.description = aiResult.value.description || form.description
  }
  if (aiApplyFields.keywords) {
    form.keywords = aiResult.value.keywords || form.keywords
  }
  if (aiApplyFields.price) {
    const price = Number(aiResult.value.price || 0)
    form.price = price > 0 ? price : 0
    form.special_type = form.price > 0 ? 1 : 0
    if (form.special_type === 0) {
      form.original_price = 0
    }
  }
  aiResultVisible.value = false
  ElMessage.success('已应用AI润色内容')
}

function handleImportAiTags() {
  if (!aiTagSuggestions.value.length) {
    ElMessage.warning('暂无可导入的建议标签')
    return
  }
  aiTagImportLoading.value = true
  importAiTags({
    tag_type: 1,
    tag_suggestions: aiTagSuggestions.value
  }).then(res => {
    const data = res.data || {}
    const ids = Array.isArray(data.tag_ids) ? data.tag_ids.map(item => Number(item)).filter(item => item > 0) : []
    const currentIds = Array.isArray(form.tag_ids) ? form.tag_ids.map(item => Number(item)) : []
    const mergedIds = currentIds.slice()
    ids.forEach(id => {
      if (mergedIds.indexOf(id) === -1) {
        mergedIds.push(id)
      }
    })
    form.tag_ids = mergedIds
    refreshTagSelector()
    ElMessage.success(formatAiTagImportMessage(data))
  }).finally(() => {
    aiTagImportLoading.value = false
  })
}

function refreshTagSelector() {
  nextTick(() => {
    if (tagSelectorRef.value && typeof tagSelectorRef.value.loadData === 'function') {
      tagSelectorRef.value.loadData()
    }
  })
}

function formatAiTagImportMessage(data) {
  const createdCount = Number(data.created_count || 0)
  const existsCount = Number(data.exists_count || 0)
  if (createdCount > 0 && existsCount > 0) {
    return `已新增${createdCount}个标签，复用${existsCount}个已有标签`
  }
  if (createdCount > 0) {
    return `已新增${createdCount}个标签`
  }
  return existsCount > 0 ? `已复用${existsCount}个已有标签` : '标签已导入'
}

function getAiTagStyle(item) {
  const style = {}
  if (item.text_color) {
    style.color = item.text_color
  }
  if (item.bg_color) {
    style.backgroundColor = item.bg_color
  }
  if (item.border_color) {
    style.borderColor = item.border_color
  }
  return style
}

function copyAiTags() {
  const text = aiTagText.value
  if (!text) {
    ElMessage.warning('暂无可复制的建议标签')
    return
  }
  if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      ElMessage.success('建议标签已复制')
    }).catch(() => {
      copyTextFallback(text)
    })
    return
  }
  copyTextFallback(text)
}

function copyTextFallback(text) {
  if (typeof document === 'undefined') {
    ElMessage.warning('当前环境不支持复制')
    return
  }
  const input = document.createElement('textarea')
  input.value = text
  input.style.position = 'fixed'
  input.style.left = '-9999px'
  document.body.appendChild(input)
  input.select()
  document.execCommand('copy')
  document.body.removeChild(input)
  ElMessage.success('建议标签已复制')
}

function formatAiPrice(price) {
  const value = Number(price || 0)
  return value.toFixed(2)
}

// 加载编辑详情
function loadDetail() {
  formLoading.value = true
  specialRead(editId.value)
    .then(res => {
      const data = res.data
      Object.assign(form, {
        title: data.title || '',
        cover: data.cover || '',
        description: data.description || '',
        keywords: data.keywords || '',
        special_type: Number(data.special_type) || 0,
        price: parseFloat(data.price) || 0,
        original_price: parseFloat(data.original_price) || 0,
        is_hot: Number(data.is_hot) || 0,
        sort: Number(data.sort) || 0,
        status: Number(data.status) !== undefined ? Number(data.status) : 1,
        category_id: Number(data.category_id) || 0,
        tag_ids: Array.isArray(data.tag_ids) ? data.tag_ids : [],
        products: Array.isArray(data.products) ? data.products : [],
        contents: (data.contents || []).map((item, index) => ({
          article_id: item.article_id || (item.article && item.article.id),
          title: (item.article && item.article.title) || item.title || '',
          cover: (item.article && item.article.cover) || item.cover || '',
          type: item.type || (item.article && item.article.content_type) || 1,
          price: parseFloat((item.article && item.article.price) || 0),
          sort: item.sort !== undefined ? item.sort : index
        }))
      })
    })
    .finally(() => {
      formLoading.value = false
    })
}

// 提交表单
function handleSubmit() {
  formRef.value.validate(valid => {
    if (!valid) return
    if (form.special_type === 1 && form.price <= 0) {
      ElMessage.error('付费专题必须设置售价大于0')
      return
    }
    submitLoading.value = true
    const submitData = {
      title: form.title,
      cover: form.cover,
      description: form.description,
      keywords: form.keywords,
      special_type: form.special_type,
      price: form.special_type === 1 ? form.price : 0,
      original_price: form.special_type === 1 ? form.original_price : 0,
      is_hot: form.is_hot,
      sort: form.sort,
      status: form.status,
      category_id: form.category_id || 0,
      tag_ids: form.tag_ids || [],
      product_ids: form.products.map((item) => Number(item.id)).filter((id) => id > 0),
      contents: form.contents.map((item, index) => ({
        article_id: item.article_id,
        type: item.type,
        sort: item.sort !== undefined ? item.sort : index
      }))
    }
    const request = isEdit.value
      ? specialSave(editId.value, submitData)
      : specialAdd(submitData)
    request
      .then(() => {
        ElMessage.success(isEdit.value ? '修改成功' : '添加成功')
        router.push('/merchant/content/special/manage')
      })
      .finally(() => {
        submitLoading.value = false
      })
  })
}

// 上移/下移内容
function moveContent(index, direction) {
  const arr = form.contents
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= arr.length) return
  const temp = arr[index]
  arr[index] = arr[targetIndex]
  arr[targetIndex] = temp
  arr.forEach((item, i) => { item.sort = i })
}

// 移除内容
function removeContent(index) {
  form.contents.splice(index, 1)
  form.contents.forEach((item, i) => { item.sort = i })
}

// 打开选择器
function openContentSelector() {
  selectorVisible.value = true
}

// 弹窗打开时加载列表
function onSelectorOpen() {
  Object.assign(selectorSearch, { title: '', content_type: '' })
  selectorPage.value = 1
  tempSelected.value = []
  loadSelectorList()
}

// 弹窗关闭清空
function onSelectorClose() {
  tempSelected.value = []
  selectorList.value = []
}

// 加载选择器内容列表
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
      selectorList.value = res.data.list || []
      selectorTotal.value = res.data.count || 0
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

// 表格多选
function handleSelectorSelection(selection) {
  tempSelected.value = selection
}

// 判断是否已选择
function isAlreadySelected(articleId) {
  return form.contents.some(item => item.article_id === articleId)
}

// 表格行是否可选
function isSelectable(row) {
  return !isAlreadySelected(row.id)
}

// 确认选择
function confirmContentSelection() {
  if (tempSelected.value.length === 0) {
    ElMessage.warning('请先选择内容')
    return
  }
  const currentIds = new Set(form.contents.map(item => item.article_id))
  const baseSort = form.contents.length
  let addCount = 0
  tempSelected.value.forEach((article, index) => {
    if (!currentIds.has(article.id)) {
      form.contents.push({
        article_id: article.id,
        title: article.title || '',
        cover: article.cover || '',
        type: article.content_type || 1,
        price: parseFloat(article.price) || 0,
        sort: baseSort + index
      })
      addCount++
    }
  })
  if (addCount > 0) {
    ElMessage.success(`已添加 ${addCount} 个内容`)
    selectorVisible.value = false
  } else {
    ElMessage.info('所选内容均已在专题中')
  }
}

// 内容类型标签颜色
function getTypeTagColor(type) {
  return (CONTENT_TYPE_MAP[type] || {}).color || ''
}

function getTypeName(type) {
  return (CONTENT_TYPE_MAP[type] || {}).label || '未知'
}

onMounted(() => {
  loadCategoryTree()
  if (isEdit.value) {
    loadDetail()
  }
})
</script>

<style lang="scss" scoped>
.special-create-container {
  min-height: 100%;

  .form-card {
    border-radius: 8px;

    .section-title {
      font-size: 14px;
      font-weight: 600;
      color: #303133;
      padding: 12px 0 16px;
      border-bottom: 1px solid #ebeef5;
      margin-bottom: 20px;

      .section-desc {
        font-size: 13px;
        font-weight: normal;
        color: #909399;
        margin-left: 8px;
      }
    }

    .form-input-lg {
      width: 500px;
    }

    .title-ai-row {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .ai-writing-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      width: 110px;
      height: 30px;
      padding: 0 18px;
      border: 0;
      border-radius: 22px;
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0;
      text-shadow: 0 1px 6px rgba(19, 28, 84, 0.35);
      background-image: url('@/assets/images/button_ai.jpg');
      background-repeat: no-repeat;
      background-position: center;
      background-size: cover;
      box-shadow: 0 8px 20px var(--brand-shadow);
      cursor: pointer;
      overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;

      i {
        margin-right: 6px;
        font-size: 15px;
      }

      &:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px var(--brand-shadow-hover);
      }

      &:disabled {
        cursor: not-allowed;
        opacity: 0.72;
      }
    }

    .upload-tip {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }

    .form-tip {
      margin-left: 8px;
      font-size: 12px;
      color: #909399;
    }

    .contents-item {
      :deep .el-form-item__content {
        margin-left: 0 !important;
      }
    }

    .products-item {
      :deep .el-form-item__content {
        margin-left: 0 !important;
      }
    }

    .product-association {
      width: 100%;

      .product-association-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
      }

      .product-table {
        margin-bottom: 12px;
      }

      .mini-cover {
        width: 54px;
        height: 38px;
        border-radius: 4px;
      }

      .table-cover-placeholder {
        color: #c0c4cc;
        font-size: 12px;
      }

      .product-empty {
        border: 1px dashed #dcdfe6;
        border-radius: 6px;
        padding: 30px;
        text-align: center;
        color: #c0c4cc;
        margin-bottom: 12px;
      }
    }

    .contents-list {
      border: 1px solid #ebeef5;
      border-radius: 6px;
      margin-bottom: 12px;
      overflow: hidden;

      .content-item {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        border-bottom: 1px solid #f2f6fc;
        background: #fff;
        transition: background 0.2s;

        &:last-child {
          border-bottom: none;
        }

        &:hover {
          background: #f5f7fa;
        }

        .content-sort {
          display: flex;
          flex-direction: column;
          align-items: center;
          width: 40px;
          flex-shrink: 0;

          .sort-num {
            font-size: 12px;
            color: #909399;
            line-height: 1;
            margin-bottom: 2px;
          }

          .sort-arrows {
            display: flex;
            flex-direction: column;

            .el-button {
              padding: 0;
              margin: 0;
            }
          }
        }

        .content-cover {
          width: 56px;
          height: 42px;
          margin: 0 10px;
          flex-shrink: 0;

          .content-cover-img {
            width: 56px;
            height: 42px;
            border-radius: 4px;
            display: block;
          }

          .content-cover-placeholder {
            width: 56px;
            height: 42px;
            border-radius: 4px;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c0c4cc;
            font-size: 20px;
          }
        }

        .content-info {
          flex: 1;
          min-width: 0;
          margin-right: 10px;

          .content-title {
            font-size: 13px;
            color: #303133;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 4px;
          }

          .content-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;

            .meta-price {
              color: var(--text-price);
              font-weight: 600;
            }
            .meta-free {
              color: #67c23a;
            }
          }
        }

        .content-sort-input {
          margin-right: 10px;
          flex-shrink: 0;
        }

        .content-actions {
          flex-shrink: 0;

          .remove-btn {
            color: #f56c6c;
            font-size: 12px;
          }
        }
      }
    }

    .contents-empty {
      border: 1px dashed #dcdfe6;
      border-radius: 6px;
      padding: 30px;
      text-align: center;
      color: #c0c4cc;
      margin-bottom: 12px;

      i {
        font-size: 36px;
        display: block;
        margin-bottom: 8px;
      }

      p {
        margin: 0;
        font-size: 13px;
      }
    }

    .select-content-btn {
      margin-top: 0;
    }
  }

  .selector-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;

    .selector-input {
      width: 240px;
    }

    .selector-select {
      width: 150px;
    }
  }

  .mini-cover {
    width: 40px;
    height: 32px;
    border-radius: 3px;
    display: block;
    margin: 0 auto;
  }

  .price-text {
    color: var(--text-price);
    font-weight: 600;
  }
}

.ai-result-dialog {
  color: var(--text-primary, #303133);
}

.ai-result-alert {
  margin-bottom: 14px;
}

.ai-result-apply {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  padding: 12px 14px;
  margin-bottom: 14px;
  border: 1px solid var(--prev-border-color-lighter, #ebeef5);
  border-radius: 6px;
  background: var(--prev-bg-color, #f5f7fa);

  &__label {
    color: var(--text-primary, #303133);
    font-weight: 600;
  }
}

.ai-result-section {
  margin-bottom: 14px;
  padding: 14px;
  border: 1px solid var(--prev-border-color-lighter, #ebeef5);
  border-radius: 6px;
  background: var(--prev-bg-white, #ffffff);

  &--inline {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--text-primary, #303133);
    font-weight: 600;
  }
}

.ai-result-text {
  color: var(--text-regular, #606266);
  line-height: 1.7;
}

.ai-result-price {
  color: var(--text-price, #0080FF);
  font-size: 18px;
  font-weight: 700;
}

.ai-tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.ai-tag-item {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 6px 10px;
  border: 1px solid var(--ds-outline-ghost);
  border-radius: 16px;
  background: var(--brand-primary-pale);

  &__name {
    flex-shrink: 0;
    color: inherit;
    font-weight: 600;
  }

  &__reason {
    margin-left: 8px;
    color: var(--text-secondary, #909399);
    font-size: 12px;
  }
}

.ai-result-empty {
  color: var(--text-secondary, #909399);
  font-size: 13px;
}

.el-dialog__footer {
  .selector-tip {
    flex: 1;
    font-size: 13px;
    color: #909399;
    padding-right: 10px;
  }
}
</style>
