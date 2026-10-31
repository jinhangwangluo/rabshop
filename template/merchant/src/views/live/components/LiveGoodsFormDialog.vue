<template>
  <div>
    <!-- 专题选择弹窗 -->
    <el-dialog
      v-model="specialPickerVisible"
      title="选择专题"
      width="780px"
      :close-on-click-modal="false"
      append-to-body
    >
      <div class="picker-dialog">
        <div class="picker-search">
          <el-input
            v-model="specialSearchKeyword"
            placeholder="搜索专题标题"
            clearable
            class="picker-search-input"
            @keyup.enter="searchSpecials"
          >
            <el-button #append :icon="Search" @click="searchSpecials" />
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
            <template #default="{ row }">
              <el-image
                v-if="row.cover"
                :src="row.cover"
                class="picker-cover"
                fit="cover"
              />
              <span v-else class="no-cover">暂无</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
          <el-table-column label="金额" width="90" align="center">
            <template #default="{ row }">
              <span class="price-text">¥{{ row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ row }">
              <el-button type="text" @click="confirmSelectSpecial(row)">选择</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="picker-pagination">
          <el-pagination
            v-model:current-page="specialPickerPage"
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
      width="780px"
      :close-on-click-modal="false"
      append-to-body
    >
      <div class="picker-dialog">
        <div class="picker-search">
          <el-input
            v-model="articleSearchKeyword"
            placeholder="搜索内容标题"
            clearable
            class="picker-search-input"
            @keyup.enter="searchArticles"
          >
            <el-button #append :icon="Search" @click="searchArticles" />
          </el-input>
          <el-select
            v-model="articleSearchType"
            placeholder="内容类型"
            clearable
            class="picker-type-select"
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
            <template #default="{ row }">
              <el-image
                v-if="row.cover"
                :src="row.cover"
                class="picker-cover"
                fit="cover"
              />
              <span v-else class="no-cover">暂无</span>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
          <el-table-column label="类型" width="80" align="center">
            <template #default="{ row }">
              <el-tag >{{ row.content_type_text || row.content_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="价格" width="90" align="center">
            <template #default="{ row }">
              <span class="price-text">¥{{ row.price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ row }">
              <el-button type="text" @click="confirmSelectArticle(row)">选择</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="picker-pagination">
          <el-pagination
            v-model:current-page="articlePickerPage"
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
      :title="title"
      width="580px"
      :close-on-click-modal="false"
      append-to-body
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="110px"
      >
        <el-form-item label="商品类型" prop="goods_type">
          <el-radio-group v-model="form.goods_type" @change="handleGoodsTypeChange">
            <el-radio label="special">付费专题</el-radio>
            <el-radio label="article">单篇内容</el-radio>
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
            <el-radio :label="1">上架</el-radio>
            <el-radio :label="0">下架</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="localVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getLiveGoodsDetail, createLiveGoods, updateLiveGoods } from '@/api/live'
import { searchContentList } from '@/api/article'
import { getSpecialList } from '@/api/special'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { nextTick, reactive, ref, watch } from 'vue'

defineOptions({
  name: 'LiveGoodsFormDialog'
})

const props = defineProps({
  modelValue: {
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
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const EMPTY_FORM = () => ({
  goods_type: 'special',
  goods_id: 0,
  goods_title: '',
  goods_cover: '',
  goods_price: 0,
  sort: 0,
  status: 1
})

const localVisible = ref(false)
const submitLoading = ref(false)
const form = reactive(EMPTY_FORM())
const rules = {
  goods_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
  goods_id: [{
    validator: (rule, value, callback) => {
      if (form.goods_type === 'special' && !value) {
        callback(new Error('请选择专题'))
      } else if (form.goods_type === 'article' && !value) {
        callback(new Error('请选择单篇内容'))
      } else {
        callback()
      }
    },
    trigger: 'change'
  }],
  goods_title: [{ required: true, message: '请输入商品标题', trigger: 'blur' }],
  goods_price: [{ required: true, message: '请输入展示价格', trigger: 'blur' }]
}
const selectedArticleTitle = ref('')
const selectedSpecialTitle = ref('')
// 单篇内容选择器
const articlePickerVisible = ref(false)
const articlePickerLoading = ref(false)
const articlePickerList = ref([])
const articlePickerTotal = ref(0)
const articlePickerPage = ref(1)
const articlePickerLimit = ref(10)
const articleSearchKeyword = ref('')
const articleSearchType = ref('')
// 专题选择器
const specialPickerVisible = ref(false)
const specialPickerLoading = ref(false)
const specialPickerList = ref([])
const specialPickerTotal = ref(0)
const specialPickerPage = ref(1)
const specialPickerLimit = ref(10)
const specialSearchKeyword = ref('')

const formRef = ref(null)

watch(() => props.modelValue, (val) => {
  localVisible.value = val
  if (val) initWhenOpen()
}, { immediate: true })

watch(localVisible, (val) => {
  emit('update:modelValue', val)
})

watch(() => props.editId, () => {
  if (localVisible.value) initWhenOpen()
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
  nextTick(() => {
    formRef.value && formRef.value.clearValidate()
  })
}

function loadDetail(id) {
  getLiveGoodsDetail(id).then(res => {
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
    nextTick(() => {
      formRef.value && formRef.value.clearValidate()
    })
  })
}

function handleGoodsTypeChange() {
  form.goods_id = 0
  form.goods_title = ''
  form.goods_cover = ''
  form.goods_price = 0
  selectedArticleTitle.value = ''
  selectedSpecialTitle.value = ''
}

// 专题选择器
function openSpecialPicker() {
  specialPickerVisible.value = true
  specialPickerPage.value = 1
  specialSearchKeyword.value = ''
  loadSpecialPickerList()
}

function loadSpecialPickerList() {
  specialPickerLoading.value = true
  getSpecialList({
    title: specialSearchKeyword.value,
    status: 1,
    page: specialPickerPage.value,
    page_size: specialPickerLimit.value,
    live_room_id: props.liveRoomId || 0
  }).then(res => {
    specialPickerList.value = res.data.list || res.data || []
    specialPickerTotal.value = res.data.count || res.data.total || 0
  }).finally(() => {
    specialPickerLoading.value = false
  })
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
    formRef.value && formRef.value.validateField('goods_id')
  })
}

function handleSpecialPageChange(val) {
  specialPickerPage.value = val
  loadSpecialPickerList()
}

// 单篇内容选择器
function openArticlePicker() {
  articlePickerVisible.value = true
  articlePickerPage.value = 1
  articleSearchKeyword.value = ''
  articleSearchType.value = ''
  loadArticlePickerList()
}

function loadArticlePickerList() {
  articlePickerLoading.value = true
  searchContentList({
    title: articleSearchKeyword.value,
    content_type: articleSearchType.value,
    page: articlePickerPage.value,
    page_size: articlePickerLimit.value,
    live_room_id: props.liveRoomId || 0
  }).then(res => {
    articlePickerList.value = res.data.list || res.data || []
    articlePickerTotal.value = res.data.total || 0
  }).finally(() => {
    articlePickerLoading.value = false
  })
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
    formRef.value && formRef.value.validateField('goods_id')
  })
}

function handleArticlePageChange(val) {
  articlePickerPage.value = val
  loadArticlePickerList()
}

function handleSubmit() {
  if (!props.liveRoomId) {
    ElMessage.error('直播间 ID 无效')
    return
  }
  formRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const data = { ...form, live_room_id: props.liveRoomId }
    const api = props.editId ? updateLiveGoods(props.editId, data) : createLiveGoods(data)
    api.then(() => {
      ElMessage.success(props.editId ? '编辑成功' : '添加成功')
      localVisible.value = false
      emit('success')
    }).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleDialogClose() {
  formRef.value && formRef.value.resetFields()
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
  color: var(--text-price);
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
</style>
