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
              @click="router.push('/special/manage')"
            >
              <el-icon><ArrowLeft /></el-icon>
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
          <el-input
            v-model="form.title"
            placeholder="请输入专题标题"
            maxlength="100"
            show-word-limit
            class="form-input-lg"
          />
        </el-form-item>

        <el-form-item label="所属分类" prop="category_id">
          <div class="category-select-row">
            <el-cascader
              v-model="form.category_id"
              :options="categoryTree"
              :props="{ checkStrictly: true, emitPath: false, value: 'id', label: 'label', children: 'children' }"
              placeholder="请选择分类"
              class="form-input-lg"
              clearable
              filterable
            />
            <el-button
              type="text"
              :icon="Plus"
              class="quick-add-category-btn"
              @click="openQuickAddCategory"
            >快捷添加分类</el-button>
          </div>
        </el-form-item>

        <el-form-item label="专题标签">
          <TagSelector v-model="form.tag_ids" :tag-type="1" />
        </el-form-item>

        <el-form-item label="专题主图" prop="cover">
          <Upload
            v-model="form.cover"
            :limit="1"
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
          <el-table
            v-if="form.contents.length > 0"
            :data="form.contents"

            border
            row-key="article_id"
            class="contents-table"
          >
            <el-table-column label="序号" width="70" align="center">
              <template #default="scope">
                {{ scope.$index + 1 }}
              </template>
            </el-table-column>
            <el-table-column label="封面" width="90" align="center">
              <template #default="scope">
                <el-image
                  v-if="scope.row.cover"
                  :src="scope.row.cover"
                  class="mini-cover"
                  fit="cover"
                />
                <div v-else class="table-cover-placeholder">
                  <el-icon><Picture /></el-icon>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="title" label="标题" min-width="220" show-overflow-tooltip />
            <el-table-column label="类型" width="90" align="center">
              <template #default="scope">
                <el-tag :type="getTypeTagColor(scope.row.type)" size="mini">
                  {{ getTypeName(scope.row.type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="价格" width="100" align="center">
              <template #default="scope">
                <span v-if="scope.row.price > 0" class="price-text">¥{{ scope.row.price }}</span>
                <el-tag v-else type="success" size="mini">免费</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="排序" width="120" align="center">
              <template #default="scope">
                <el-input-number
                  v-model="scope.row.sort"
                  :min="1"
                  :max="form.contents.length"
                  size="mini"
                  controls-position="right"
                  class="content-sort-input"
                  @change="handleContentSortChange(scope.row, $event)"
                />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100" fixed="right" align="center">
              <template #default="scope">
                <el-button
                  type="text"

                  :icon="Delete"
                  class="remove-btn"
                  @click="removeContent(scope.$index)"
                >移除</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div v-else class="contents-empty">
            <el-icon><Document /></el-icon>
            <p>暂未添加内容，点击下方按钮选择内容</p>
          </div>

          <el-button
            :icon="Plus"
            class="select-content-btn"
            @click="openContentSelector"
          >选择内容</el-button>
        </el-form-item>

        <!-- 提交按钮 -->
        <el-form-item>
          <el-button
            type="primary"
            :loading="submitLoading"
            @click="handleSubmit"
          >{{ isEdit ? '保存修改' : '立即添加' }}</el-button>
          <el-button @click="router.push('/special/manage')">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>

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
      <!-- 搜索条件 -->
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

      <!-- 内容列表 -->
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
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              class="mini-cover"
              fit="cover"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
        <el-table-column label="类型" width="75" align="center">
          <template #default="scope">
            <el-tag :type="getTypeTagColor(scope.row.content_type)" size="mini">
              {{ getTypeName(scope.row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="90" align="center">
          <template #default="scope">
            <span v-if="scope.row.price > 0" class="price-text">¥{{ scope.row.price }}</span>
            <el-tag v-else type="success" size="mini">免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="scope">
            <el-tag v-if="isAlreadySelected(scope.row.id)" type="info" size="mini">已选</el-tag>
            <el-tag v-else type="success" size="mini">可选</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="acea-row row-right page">
        <el-pagination
          :current-page="selectorPage"
          :page-sizes="[10, 20]"
          :page-size="selectorLimit"
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
    <!-- 快捷添加分类弹窗 -->
    <el-dialog
      v-model="quickCategoryDialogVisible"
      title="快捷添加分类"
      width="480px"
      :close-on-click-modal="false"
      append-to-body
      destroy-on-close
      @close="handleQuickCategoryClose"
    >
      <el-form
        ref="quickCategoryForm"
        :model="quickCategoryForm"
        :rules="quickCategoryRules"
        label-width="90px"
      >
        <el-form-item label="上级分类">
          <el-cascader
            v-model="quickCategoryForm.pid"
            :options="categoryTree"
            :props="{ checkStrictly: true, emitPath: false, value: 'id', label: 'label', children: 'children' }"
            placeholder="请选择上级分类（不选为一级）"
            style="width: 100%"
            clearable
            filterable
          />
        </el-form-item>
        <el-form-item label="分类名称" prop="name">
          <el-input
            v-model="quickCategoryForm.name"
            placeholder="请输入分类名称"
            maxlength="30"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="分类图片" prop="image">
          <Upload
            v-model="quickCategoryForm.image"
            :limit="1"
          />
          <div class="upload-tip">建议尺寸：400×300px，支持JPG/PNG格式</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="quickCategoryDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="quickCategoryLoading" @click="submitQuickAddCategory">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeMount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Delete, Document, Picture, Plus, Search } from '@element-plus/icons-vue'
import { specialAdd, specialRead, specialSave, searchSpecialContent, getSpecialCategoryTree, specialCategoryQuickAdd } from '@/api/special'
import Upload from '@/components/Upload/index'
import TagSelector from '@/components/TagSelector/index'

defineOptions({ name: 'SpecialCreate' })

const CONTENT_TYPE_MAP = {
  1: { label: '图文', color: 'primary' },
  2: { label: '文字', color: '' },
  3: { label: '小说', color: 'warning' },
  4: { label: '图片', color: 'success' },
  5: { label: '音频', color: 'danger' },
  6: { label: '视频', color: 'info' }
}

function normalizeSingleImage(value) {
  if (typeof value === 'string') {
    return value.trim()
  }

  if (Array.isArray(value)) {
    return normalizeSingleImage(value[0])
  }

  if (value && typeof value === 'object') {
    return String(value.url || value.path || value.src || '').trim()
  }

  return ''
}

const route = useRoute()
const router = useRouter()

const formRef = ref(null)
const quickCategoryFormRef = ref(null)

const getDefaultForm = () => ({
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
  tag_ids: []
})

const formLoading = ref(false)
const submitLoading = ref(false)
const form = reactive(getDefaultForm())
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
    {
      validator: (_rule, value, callback) => {
        if (normalizeSingleImage(value)) {
          callback()
          return
        }
        callback(new Error('请上传专题主图'))
      },
      trigger: 'change'
    }
  ],
  special_type: [
    { required: true, message: '请选择专题类型', trigger: 'change' }
  ],
  price: [
    {
      validator: (_rule, value, callback) => {
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
const contentTypeOptions = Object.entries(CONTENT_TYPE_MAP).map(([value, info]) => ({
  value: Number(value),
  label: info.label
}))

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

const categoryTree = ref([])

const quickCategoryDialogVisible = ref(false)
const quickCategoryLoading = ref(false)
const quickCategoryForm = reactive({
  pid: 0,
  name: '',
  image: ''
})
const quickCategoryRules = {
  name: [
    { required: true, message: '请输入分类名称', trigger: 'blur' },
    { max: 30, message: '分类名称最多30个字符', trigger: 'blur' }
  ],
  image: [
    { required: true, message: '请上传分类图片', trigger: 'change' }
  ]
}

const isEdit = computed(() => !!route.query.id)
const editId = computed(() => (route.query.id ? Number(route.query.id) : null))

function loadCategoryTree() {
  getSpecialCategoryTree().then(res => {
    categoryTree.value = res.data || []
  }).catch(() => {})
}

function openQuickAddCategory() {
  Object.assign(quickCategoryForm, { pid: 0, name: '', image: '' })
  quickCategoryDialogVisible.value = true
}

function handleQuickCategoryClose() {
  quickCategoryDialogVisible.value = false
  nextTick(() => {
    quickCategoryFormRef.value?.resetFields()
  })
}

function submitQuickAddCategory() {
  quickCategoryFormRef.value?.validate(valid => {
    if (!valid) return
    quickCategoryLoading.value = true
    specialCategoryQuickAdd(quickCategoryForm).then(res => {
      ElMessage.success(res.message || '添加成功')
      handleQuickCategoryClose()
      loadCategoryTree()
    }).catch(() => {}).finally(() => {
      quickCategoryLoading.value = false
    })
  })
}

function loadDetail() {
  if (!editId.value) return
  formLoading.value = true
  specialRead(editId.value)
    .then(res => {
      const data = res.data
      Object.assign(form, {
        title: data.title || '',
        cover: normalizeSingleImage(data.cover),
        description: data.description || '',
        keywords: data.keywords || '',
        special_type: Number(data.special_type) || 0,
        price: parseFloat(data.price) || 0,
        original_price: parseFloat(data.original_price) || 0,
        is_hot: Number(data.is_hot) || 0,
        sort: Number(data.sort) || 0,
        status: data.status !== undefined ? Number(data.status) : 1,
        category_id: Number(data.category_id) || 0,
        tag_ids: Array.isArray(data.tag_ids) ? data.tag_ids : [],
        contents: normalizeContentSort((data.contents || []).map((item, index) => ({
          article_id: item.article_id || (item.article && item.article.id),
          title: (item.article && item.article.title) || item.title || '',
          cover: (item.article && item.article.cover) || item.cover || '',
          type: item.type || (item.article && item.article.content_type) || 1,
          price: parseFloat((item.article && item.article.price) || 0),
          sort: item.sort !== undefined ? item.sort : index + 1
        })))
      })
    })
    .finally(() => {
      formLoading.value = false
    })
}

function handleSubmit() {
  formRef.value?.validate(valid => {
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
      contents: form.contents.map((item, index) => ({
        article_id: item.article_id,
        type: item.type,
        sort: item.sort !== undefined ? item.sort : index + 1
      }))
    }

    const request = isEdit.value
      ? specialSave(editId.value, submitData)
      : specialAdd(submitData)

    request
      .then(() => {
        ElMessage.success(isEdit.value ? '修改成功' : '添加成功')
        router.push('/special/manage')
      })
      .finally(() => {
        submitLoading.value = false
      })
  })
}

function normalizeContentSort(contents = form.contents) {
  const normalized = contents.map((item, index) => ({
    ...item,
    sort: index + 1
  }))
  if (contents === form.contents) {
    form.contents = normalized
  }
  return normalized
}

function handleContentSortChange(row, value) {
  const currentIndex = form.contents.findIndex(item => item.article_id === row.article_id)
  if (currentIndex === -1) return

  const targetSort = Number(value)
  const maxSort = form.contents.length
  const targetIndex = Math.min(Math.max(Number.isFinite(targetSort) ? Math.round(targetSort) : 1, 1), maxSort) - 1

  if (targetIndex !== currentIndex) {
    const contents = [...form.contents]
    const [currentRow] = contents.splice(currentIndex, 1)
    contents.splice(targetIndex, 0, currentRow)
    form.contents = contents
  }

  normalizeContentSort()
}

function removeContent(index) {
  form.contents.splice(index, 1)
  normalizeContentSort()
}

function openContentSelector() {
  selectorVisible.value = true
}

function resetSelectorSearch() {
  Object.assign(selectorSearch, {
    title: '',
    content_type: ''
  })
}

function onSelectorOpen() {
  resetSelectorSearch()
  selectorPage.value = 1
  tempSelected.value = []
  loadSelectorList()
}

function onSelectorClose() {
  tempSelected.value = []
  selectorList.value = []
}

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
      selectorTotal.value = res.data.total || res.data.count || 0
    })
    .finally(() => {
      selectorLoading.value = false
    })
}

function handleSelectorSizeChange(val) {
  selectorLimit.value = val
  selectorPage.value = 1
  loadSelectorList()
}

function handleSelectorPageChange(val) {
  selectorPage.value = val
  loadSelectorList()
}

function handleSelectorSelection(selection) {
  tempSelected.value = selection
}

function isAlreadySelected(articleId) {
  return form.contents.some(item => item.article_id === articleId)
}

function isSelectable(row) {
  return !isAlreadySelected(row.id)
}

function confirmContentSelection() {
  if (tempSelected.value.length === 0) {
    ElMessage.warning('请先选择内容')
    return
  }
  const currentIds = new Set(form.contents.map(item => item.article_id))
  const baseSort = form.contents.length + 1
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
    normalizeContentSort()
    ElMessage.success(`已添加 ${addCount} 个内容`)
    selectorVisible.value = false
  } else {
    ElMessage.info('所选内容均已在专题中')
  }
}

function getTypeTagColor(type) {
  return (CONTENT_TYPE_MAP[type] || {}).color || ''
}

function getTypeName(type) {
  return (CONTENT_TYPE_MAP[type] || {}).label || '未知'
}

onBeforeMount(() => {
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

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 16px;
      font-weight: 600;
    }

    .section-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--ds-on-surface, #303133);
      padding: 12px 0 16px;
      border-bottom: 1px solid var(--ds-outline-variant, #ebeef5);
      margin-bottom: 20px;

      .section-desc {
        font-size: 13px;
        font-weight: normal;
        color: var(--ds-on-surface-variant, #909399);
        margin-left: 8px;
      }
    }

    .form-input-lg {
      width: 500px;
    }

    .category-select-row {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .quick-add-category-btn {
      flex-shrink: 0;
      font-size: 13px;
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

    // 内容列表区域
    .contents-item {
      :deep(.el-form-item__content) {
        margin-left: 0 !important;
      }
    }

    .contents-table {
      margin-bottom: 12px;

      :deep(.el-table__body-wrapper) {
        overflow-x: auto;
      }

      :deep(.el-table__cell) {
        padding: 8px 0;
      }

      :deep(.content-sort-input) {
        width: 88px;
      }
    }

    .table-cover-placeholder {
      width: 40px;
      height: 32px;
      border-radius: 3px;
      background: #f5f7fa;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #c0c4cc;
      font-size: 18px;
      margin: 0 auto;
    }

    .remove-btn {
      color: #f56c6c;
      font-size: 12px;
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

    :deep(.select-content-btn.el-button) {
      border-radius: 6px;
      background: var(--ds-surface-card);
      color: var(--ds-primary) !important;
      border: 1px solid var(--ds-primary);
      font-weight: 500;
      box-shadow: none;

      i,
      span {
        color: inherit;
      }

      &:hover,
      &:focus {
        background: rgba(var(--ds-surface-low-rgb), 0.6);
        color: var(--ds-primary-container) !important;
        border-color: var(--ds-primary-container);
      }

      &:active {
        background: rgba(var(--ds-surface-low-rgb), 0.85);
        color: var(--ds-primary) !important;
        border-color: var(--ds-primary);
      }
    }
  }

  // 内容选择器弹窗
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

  .selector-pagination {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
  }

  .mini-cover {
    width: 40px;
    height: 32px;
    border-radius: 3px;
    display: block;
    margin: 0 auto;
  }

  .price-text {
    color: #f56c6c;
    font-weight: 600;
  }
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
