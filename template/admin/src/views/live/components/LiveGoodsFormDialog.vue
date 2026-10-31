<template>
  <div>
    <!-- 专题选择弹窗 -->
    <el-dialog
      v-model="specialPickerVisible"
      title="选择专题"
      :width="props.consoleMode ? 'min(780px, calc(100vw - 32px))' : '780px'"
      :class="{ 'live-goods-console-dialog': props.consoleMode }"
      :style="consoleDialogStyle"
      :close-on-click-modal="false"
      append-to-body
    >
      <div class="picker-dialog" :class="{ 'picker-dialog--console': props.consoleMode }">
        <div class="picker-search">
          <el-input
            v-model="specialSearchKeyword"
            placeholder="搜索专题标题"
            clearable
            class="picker-search-input"
            @keyup.enter="searchSpecials"
          >
            <template #append>
              <el-button :icon="Search" aria-label="搜索专题" title="搜索专题" @click="searchSpecials" />
            </template>
          </el-input>
        </div>

        <el-table
          v-loading="specialPickerLoading"
          :data="specialPickerList"
          highlight-current-row
          class="picker-table"
          @row-click="handleSpecialRowClick"
        >
          <el-table-column label="封面" width="70" align="center">
            <template #default="scope">
              <el-image
                v-if="scope.row.cover"
                :src="scope.row.cover"
                class="picker-cover"
                fit="cover"
              />
              <span v-else class="no-cover">暂无</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
          <el-table-column label="金额" width="90" align="center">
            <template #default="scope">
              <span class="price-text">¥{{ scope.row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="scope">
              <el-button type="primary" link :icon="Check" @click="confirmSelectSpecial(scope.row)">选择</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="picker-pagination">
          <el-pagination
            :current-page="specialPickerPage"
            :page-size="specialPickerLimit"
            layout="total, prev, pager, next"
            :total="specialPickerTotal"
            small
            @current-change="handleSpecialPageChange"
          />
        </div>
      </div>
    </el-dialog>

    <!-- 单篇内容选择弹窗 -->
    <el-dialog
      v-model="articlePickerVisible"
      title="选择单篇内容"
      :width="props.consoleMode ? 'min(780px, calc(100vw - 32px))' : '780px'"
      :class="{ 'live-goods-console-dialog': props.consoleMode }"
      :style="consoleDialogStyle"
      :close-on-click-modal="false"
      append-to-body
    >
      <div class="picker-dialog" :class="{ 'picker-dialog--console': props.consoleMode }">
        <div class="picker-search">
          <el-input
            v-model="articleSearchKeyword"
            placeholder="搜索内容标题"
            clearable
            class="picker-search-input"
            @keyup.enter="searchArticles"
          >
            <template #append>
              <el-button :icon="Search" aria-label="搜索内容" title="搜索内容" @click="searchArticles" />
            </template>
          </el-input>
          <el-select
            v-model="articleSearchType"
            placeholder="内容类型"
            clearable
            class="picker-type-select"
            :popper-style="consoleThemeStyle"
            @change="searchArticles"
          >
            <el-option label="图文" value="graphic" />
            <el-option label="音频" value="audio" />
            <el-option label="视频" value="video" />
            <el-option label="文字" value="text" />
          </el-select>
        </div>

        <el-table
          v-loading="articlePickerLoading"
          :data="articlePickerList"
          highlight-current-row
          class="picker-table"
          @row-click="handleArticleRowClick"
        >
          <el-table-column label="封面" width="70" align="center">
            <template #default="scope">
              <el-image
                v-if="scope.row.cover"
                :src="scope.row.cover"
                class="picker-cover"
                fit="cover"
              />
              <span v-else class="no-cover">暂无</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
          <el-table-column label="类型" width="80" align="center">
            <template #default="scope">
              <el-tag>{{ scope.row.content_type_text || scope.row.content_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="价格" width="90" align="center">
            <template #default="scope">
              <span class="price-text">¥{{ scope.row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="scope">
              <el-button type="primary" link :icon="Check" @click="confirmSelectArticle(scope.row)">选择</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="picker-pagination">
          <el-pagination
            :current-page="articlePickerPage"
            :page-size="articlePickerLimit"
            layout="total, prev, pager, next"
            :total="articlePickerTotal"
            small
            @current-change="handleArticlePageChange"
          />
        </div>
      </div>
    </el-dialog>

    <!-- 新增/编辑挂载商品 -->
    <el-dialog
      v-model="localVisible"
      :title="props.title"
      :width="props.consoleMode ? 'min(580px, calc(100vw - 32px))' : '580px'"
      :class="{ 'live-goods-console-dialog': props.consoleMode }"
      :style="consoleDialogStyle"
      :close-on-click-modal="false"
      append-to-body
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        :class="{ 'live-goods-console-form': props.consoleMode }"
        label-width="110px"
      >
        <el-form-item label="商品类型" prop="goods_type">
          <el-radio-group v-model="form.goods_type" @change="handleGoodsTypeChange">
            <el-radio value="special">付费专题</el-radio>
            <el-radio value="article">单篇内容</el-radio>
            <el-radio value="product">商品</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="form.goods_type === 'special'" label="选择专题" prop="goods_id">
          <div class="picker-input-wrap">
            <el-input
              v-model="selectedSpecialTitle"
              placeholder="请点击右侧按钮选择专题"
              readonly
              class="picker-input"
            />
            <el-button type="primary" plain :icon="Search" @click="openSpecialPicker">选择</el-button>
          </div>
        </el-form-item>

        <el-form-item v-if="form.goods_type === 'article'" label="选择内容" prop="goods_id">
          <div class="picker-input-wrap">
            <el-input
              v-model="selectedArticleTitle"
              placeholder="请点击右侧按钮选择单篇内容"
              readonly
              class="picker-input"
            />
            <el-button type="primary" plain :icon="Search" @click="openArticlePicker">选择</el-button>
          </div>
        </el-form-item>

        <el-form-item v-if="form.goods_type === 'product'" label="选择商品" prop="goods_id">
          <div class="picker-input-wrap">
            <el-input
              v-model="selectedProductTitle"
              placeholder="请点击右侧按钮选择商品"
              readonly
              class="picker-input"
            />
            <el-button type="primary" plain :icon="Search" @click="openProductPicker">选择</el-button>
          </div>
        </el-form-item>

        <el-form-item label="商品标题" prop="goods_title">
          <el-input
            v-model="form.goods_title"
            placeholder="展示名称，可自定义修改"
            class="form-input"
          />
        </el-form-item>

        <el-form-item label="商品封面">
          <Upload v-model="form.goods_cover" :limit="1" />
        </el-form-item>

        <el-form-item label="展示价格" prop="goods_price">
          <el-input-number v-model="form.goods_price" :min="0" :precision="2" :step="0.01" />
          <span class="tips-text">可在原价基础上修改展示价格</span>
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="9999" />
        </el-form-item>

        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">上架</el-radio>
            <el-radio :value="0">下架</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer" :class="{ 'dialog-footer--console': props.consoleMode }">
          <el-button :icon="Close" @click="localVisible = false">取 消</el-button>
          <el-button type="primary" :icon="Check" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 商品选择弹窗（单选，仅平台商品） -->
    <product-picker
      v-model:visible="productPickerVisible"
      :width="props.consoleMode ? 'min(900px, calc(100vw - 32px))' : '900px'"
      :class="{ 'live-goods-console-dialog': props.consoleMode }"
      :style="consoleDialogStyle"
      @select="confirmSelectProduct"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import { Check, Close, Search } from '@element-plus/icons-vue'
import Upload from '@/components/Upload/index'
import ProductPicker from '@/components/ProductPicker/index.vue'
import { getLiveGoodsDetail, createLiveGoods, updateLiveGoods } from '@/api/live'
import { searchContentList } from '@/api/article'
import { getSpecialList } from '@/api/special'

defineOptions({
  name: 'LiveGoodsFormDialog'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: '添加挂载商品'
  },
  liveRoomId: {
    type: Number,
    default: 0
  },
  editId: {
    type: Number,
    default: 0
  },
  // 仅直播中台启用白色响应式弹层，普通后台沿用原布局。
  consoleMode: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:visible', 'success'])
const { proxy } = getCurrentInstance() || {}

// append-to-body 不继承中台容器变量，弹窗与下拉层必须各自携带局部主题。
const consoleThemeStyle = computed(() => props.consoleMode ? {
  '--dashboard-surface': '#ffffff',
  '--dashboard-surface-muted': '#f6f7f8',
  '--dashboard-border': '#e8eaed',
  '--dashboard-border-soft': '#eef0f2',
  '--dashboard-text-primary': '#1a1c1c',
  '--dashboard-text-secondary': '#4c4546',
  '--dashboard-text-muted': '#848484',
  '--dashboard-on-accent': '#ffffff',
  '--prev-color-text-primary': 'var(--dashboard-text-primary)',
  '--ds-primary': 'var(--dashboard-accent, #f34100)',
  '--ds-primary-container': 'var(--dashboard-accent-light, #ff8a5c)',
  '--ds-on-primary': 'var(--dashboard-on-accent)',
  '--el-color-primary': 'var(--dashboard-accent, #f34100)',
  '--el-color-primary-dark-2': 'var(--dashboard-accent-hover, #d93a00)',
  '--el-color-primary-light-3': 'var(--dashboard-accent-light, #ff8a5c)',
  '--el-color-primary-light-5': 'color-mix(in srgb, var(--dashboard-accent, #f34100) 50%, #ffffff)',
  '--el-color-primary-light-7': 'color-mix(in srgb, var(--dashboard-accent, #f34100) 30%, #ffffff)',
  '--el-color-primary-light-8': 'color-mix(in srgb, var(--dashboard-accent, #f34100) 20%, #ffffff)',
  '--el-color-primary-light-9': 'var(--dashboard-accent-soft, #fff0ea)',
  '--el-text-color-primary': 'var(--dashboard-text-primary)',
  '--el-text-color-regular': 'var(--dashboard-text-secondary)',
  '--el-text-color-secondary': 'var(--dashboard-text-muted)',
  '--el-text-color-placeholder': '#a4a7ad',
  '--el-border-color': 'var(--dashboard-border)',
  '--el-border-color-light': 'var(--dashboard-border)',
  '--el-border-color-lighter': 'var(--dashboard-border-soft)',
  '--el-bg-color': '#ffffff',
  '--el-bg-color-overlay': '#ffffff',
  '--el-bg-color-page': 'var(--dashboard-surface-muted)',
  '--el-fill-color-blank': '#ffffff',
  '--el-fill-color': 'var(--dashboard-surface-muted)',
  '--el-fill-color-light': 'var(--dashboard-surface-muted)',
  '--el-fill-color-lighter': '#fafafa',
  '--el-mask-color': 'rgba(255, 255, 255, 0.82)',
  color: 'var(--dashboard-text-primary)',
  colorScheme: 'light'
} : undefined)

const consoleDialogStyle = computed(() => props.consoleMode ? {
  ...consoleThemeStyle.value,
  background: '#ffffff',
  border: '1px solid var(--dashboard-border)',
  borderRadius: '8px',
  margin: '32px auto',
  maxHeight: 'calc(100vh - 64px)',
  overflow: 'auto'
} : undefined)

const EMPTY_FORM = () => ({
  goods_type: 'special',
  goods_id: 0,
  goods_title: '',
  goods_cover: '',
  goods_price: 0,
  sort: 0,
  status: 1
})

const formRef = ref(null)
const localVisible = ref(false)
const submitLoading = ref(false)
const form = reactive(EMPTY_FORM())
const selectedArticleTitle = ref('')
const selectedSpecialTitle = ref('')
const selectedProductTitle = ref('')
const productPickerVisible = ref(false)
const articlePickerVisible = ref(false)
const articlePickerLoading = ref(false)
const articlePickerList = ref([])
const articlePickerTotal = ref(0)
const articlePickerPage = ref(1)
const articlePickerLimit = ref(10)
const articleSearchKeyword = ref('')
const articleSearchType = ref('')
const specialPickerVisible = ref(false)
const specialPickerLoading = ref(false)
const specialPickerList = ref([])
const specialPickerTotal = ref(0)
const specialPickerPage = ref(1)
const specialPickerLimit = ref(10)
const specialSearchKeyword = ref('')

const rules = {
  goods_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
  goods_id: [{
    validator: (rule, value, callback) => {
      if (form.goods_type === 'special' && !value) {
        callback(new Error('请选择专题'))
      } else if (form.goods_type === 'article' && !value) {
        callback(new Error('请选择单篇内容'))
      } else if (form.goods_type === 'product' && !value) {
        callback(new Error('请选择商品'))
      } else {
        callback()
      }
    },
    trigger: 'change'
  }],
  goods_title: [{ required: true, message: '请输入商品标题', trigger: 'blur' }],
  goods_price: [{ required: true, message: '请输入展示价格', trigger: 'blur' }]
}

watch(() => props.visible, val => {
  localVisible.value = val
  if (val) {
    initWhenOpen()
  }
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

watch(() => props.editId, () => {
  if (localVisible.value) {
    initWhenOpen()
  }
})

function initWhenOpen() {
  if (props.editId) {
    loadDetail(props.editId)
  } else {
    resetForm()
  }
}

function resetForm() {
  Object.assign(form, EMPTY_FORM())
  selectedArticleTitle.value = ''
  selectedSpecialTitle.value = ''
  selectedProductTitle.value = ''
  nextTick(() => {
    formRef.value?.clearValidate?.()
  })
}

async function loadDetail(id) {
  const res = await getLiveGoodsDetail(id)
  const d = res.data || {}
  Object.assign(form, {
    goods_type: d.goods_type || 'special',
    goods_id: d.goods_id || 0,
    goods_title: d.goods_title || '',
    goods_cover: d.goods_cover || '',
    goods_price: d.goods_price || 0,
    sort: d.sort || 0,
    status: d.status
  })
  selectedArticleTitle.value = form.goods_type === 'article' ? form.goods_title : ''
  selectedSpecialTitle.value = form.goods_type === 'special' ? form.goods_title : ''
  selectedProductTitle.value = form.goods_type === 'product' ? form.goods_title : ''
  nextTick(() => {
    formRef.value?.clearValidate?.()
  })
}

function handleGoodsTypeChange() {
  form.goods_id = 0
  form.goods_title = ''
  form.goods_cover = ''
  form.goods_price = 0
  selectedArticleTitle.value = ''
  selectedSpecialTitle.value = ''
  selectedProductTitle.value = ''
}

function openProductPicker() {
  productPickerVisible.value = true
}

/**
 * 回填选中的商品。
 *
 * 商品来源由 ProductPicker 限定为平台商品（platform_only=1）；
 * 标题、封面、价格默认取商品自身数据，标题仍允许在表单内自定义修改。
 *
 * @param {Object} row 商品选项行数据。
 * @returns {void}
 */
function confirmSelectProduct(row) {
  form.goods_id = row.id
  form.goods_title = row.name
  form.goods_cover = row.cover || ''
  form.goods_price = row.price || 0
  selectedProductTitle.value = row.name
  productPickerVisible.value = false
  nextTick(() => {
    formRef.value?.validateField?.('goods_id')
  })
}

function openSpecialPicker() {
  specialPickerVisible.value = true
  specialPickerPage.value = 1
  specialSearchKeyword.value = ''
  loadSpecialPickerList()
}

async function loadSpecialPickerList() {
  specialPickerLoading.value = true
  try {
    const res = await getSpecialList({
      title: specialSearchKeyword.value,
      status: 1,
      page: specialPickerPage.value,
      page_size: specialPickerLimit.value,
      live_room_id: props.liveRoomId || 0
    })
    specialPickerList.value = res.data.list || res.data || []
    specialPickerTotal.value = res.data.count || res.data.total || 0
  } finally {
    specialPickerLoading.value = false
  }
}

function searchSpecials() {
  specialPickerPage.value = 1
  loadSpecialPickerList()
}

function handleSpecialRowClick(row) {
  confirmSelectSpecial(row)
}

function confirmSelectSpecial(row) {
  form.goods_id = row.id
  form.goods_title = row.title
  form.goods_cover = row.cover || ''
  form.goods_price = row.price || 0
  selectedSpecialTitle.value = row.title
  specialPickerVisible.value = false
  nextTick(() => {
    formRef.value?.validateField?.('goods_id')
  })
}

function handleSpecialPageChange(val) {
  specialPickerPage.value = val
  loadSpecialPickerList()
}

function openArticlePicker() {
  articlePickerVisible.value = true
  articlePickerPage.value = 1
  articleSearchKeyword.value = ''
  articleSearchType.value = ''
  loadArticlePickerList()
}

async function loadArticlePickerList() {
  articlePickerLoading.value = true
  try {
    const res = await searchContentList({
      title: articleSearchKeyword.value,
      content_type: articleSearchType.value,
      page: articlePickerPage.value,
      page_size: articlePickerLimit.value,
      live_room_id: props.liveRoomId || 0
    })
    articlePickerList.value = res.data.list || res.data || []
    articlePickerTotal.value = res.data.total || 0
  } finally {
    articlePickerLoading.value = false
  }
}

function searchArticles() {
  articlePickerPage.value = 1
  loadArticlePickerList()
}

function handleArticleRowClick(row) {
  confirmSelectArticle(row)
}

function confirmSelectArticle(row) {
  form.goods_id = row.id
  form.goods_title = row.title
  form.goods_cover = row.cover || ''
  form.goods_price = row.price || 0
  selectedArticleTitle.value = row.title
  articlePickerVisible.value = false
  nextTick(() => {
    formRef.value?.validateField?.('goods_id')
  })
}

function handleArticlePageChange(val) {
  articlePickerPage.value = val
  loadArticlePickerList()
}

async function handleSubmit() {
  if (!props.liveRoomId) {
    proxy?.$message?.error('直播间 ID 无效')
    return
  }
  const valid = await Promise.resolve(formRef.value?.validate?.()).catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    const data = { ...form, live_room_id: props.liveRoomId }
    const api = props.editId ? updateLiveGoods(props.editId, data) : createLiveGoods(data)
    await api
    proxy?.$message?.success(props.editId ? '编辑成功' : '添加成功')
    localVisible.value = false
    emit('success')
  } finally {
    submitLoading.value = false
  }
}

function handleDialogClose() {
  formRef.value?.resetFields?.()
}
</script>

<style lang="scss" scoped>
.form-input {
  width: 320px;
}

.picker-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;

  // 选择按钮点击后会保留焦点态，全局样式在 :focus 下只改了文字色，
  // 会出现“白字 + 浅底”看不清的情况，这里把各状态的颜色写明确。
  :deep(.el-button.is-plain) {
    color: var(--dashboard-accent, #f34100) !important;
    background: var(--dashboard-accent-soft, #fff0ea) !important;
    border-color: var(--dashboard-accent-outline, rgba(243, 65, 0, 0.12)) !important;
  }

  :deep(.el-button.is-plain:hover),
  :deep(.el-button.is-plain:focus) {
    color: var(--dashboard-on-accent, #ffffff) !important;
    background: var(--dashboard-accent, #f34100) !important;
    border-color: var(--dashboard-accent, #f34100) !important;
  }
}

.picker-input {
  width: 260px;
}

.tips-text {
  margin-left: 8px;
  color: #909399;
  font-size: 12px;
}

.price-text {
  color: #e6a23c;
  font-weight: 600;
}

.no-cover {
  color: #c0c4cc;
  font-size: 12px;
}

.picker-dialog {
  .picker-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    .picker-search-input {
      flex: 1;
    }
    .picker-type-select {
      width: 130px;
    }
  }
  .picker-table {
    width: 100%;
    .picker-cover {
      width: 50px;
      height: 36px;
      border-radius: 4px;
    }
  }
  .picker-pagination {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
  }
}

.live-goods-console-form {
  .form-input,
  .picker-input-wrap {
    width: 100%;
    min-width: 0;
  }

  .picker-input {
    flex: 1;
    width: auto;
    min-width: 0;
  }

  .tips-text {
    width: 100%;
    margin: 8px 0 0;
    color: var(--dashboard-text-muted);
    line-height: 18px;
  }

  :deep(.el-form-item__content) {
    min-width: 0;
  }

  :deep(.el-radio-group) {
    display: flex;
    flex-wrap: wrap;
  }

  :deep(.el-radio) {
    margin-right: 18px;
  }

  :deep(.el-radio:last-child) {
    margin-right: 0;
  }

  :deep(.el-button) {
    flex-shrink: 0;
  }
}

.picker-dialog--console {
  min-width: 0;

  .picker-search {
    flex-wrap: wrap;
  }

  .picker-search-input {
    min-width: 160px;
  }

  .picker-type-select {
    flex-shrink: 0;
  }

  .picker-pagination {
    overflow-x: auto;
    justify-content: flex-start;
  }

  .price-text {
    color: var(--dashboard-accent, #f34100);
  }
}

.dialog-footer--console {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}

// 共享选择器传送到 body，只有中台弹窗的唯一类名允许跨作用域覆盖。
:global(.live-goods-console-dialog .product-picker .picker-search) {
  flex-wrap: wrap;
}

:global(.live-goods-console-dialog .product-picker .picker-search-input) {
  flex: 1;
  min-width: 160px;
  width: auto;
}

:global(.live-goods-console-dialog .product-picker .price-text) {
  color: var(--dashboard-accent, #f34100);
}

@media (max-width: 520px) {
  .live-goods-console-form {
    :deep(.el-form-item) {
      display: block;
    }

    :deep(.el-form-item__label) {
      justify-content: flex-start;
      width: auto !important;
      height: auto;
      padding: 0 0 8px;
      line-height: 20px;
    }

    :deep(.el-form-item__content) {
      margin-left: 0 !important;
    }
  }
}
</style>
