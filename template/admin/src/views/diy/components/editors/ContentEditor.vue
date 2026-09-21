<template>
  <div class="content-editor">
    <el-form label-width="100px">
      <el-form-item v-if="!isCategoryTabsStyle" label="标题">
        <el-input v-model="localConfig.title" placeholder="最新课程" />
      </el-form-item>
      <el-form-item label="内容类型">
        <el-radio-group v-model="localConfig.content_type">
          <el-radio label="all">全部</el-radio>
          <el-radio label="article">图文</el-radio>
          <el-radio label="text">文字</el-radio>
          <el-radio label="novel">小说</el-radio>
          <el-radio label="image">图片</el-radio>
          <el-radio label="video">视频</el-radio>
          <el-radio label="audio">音频</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="展示样式">
        <el-radio-group v-model="localConfig.display_style">
          <el-radio label="list">列表</el-radio>
          <el-radio label="card">卡片</el-radio>
          <el-radio label="waterfall">瀑布流</el-radio>
          <el-radio label="category_tabs">分类标签</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item :label="isCategoryTabsStyle ? '每类数量' : '显示数量'">
        <template v-if="isLast && !isCategoryTabsStyle">
          <span class="tip-text">当前组件在页面末尾，将启用触底加载更多</span>
        </template>
        <template v-else>
          <el-input-number
            v-model="localConfig.show_count"
            :min="1"
            :max="50"
            :step="1"
            controls-position="right"
            style="width: 120px"
          />
        </template>
      </el-form-item>
      <el-form-item v-if="!isCategoryTabsStyle" label="数据来源">
        <el-radio-group v-model="localConfig.data_source">
          <el-radio label="auto">自动获取</el-radio>
          <el-radio label="category">按分类</el-radio>
          <el-radio label="manual">手动指定</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item v-if="isCategoryTabsStyle" label="一级分类">
        <el-select
          v-model="localConfig.category_ids"
          multiple
          collapse-tags
          clearable
          placeholder="请选择一级启用分类"
          style="width: 100%"
          @change="handleCategoryTabsChange"
        >
          <el-option
            v-for="item in topCategoryOptions"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item v-if="!isCategoryTabsStyle && localConfig.data_source === 'category'" label="选择分类">
        <el-cascader
          v-model="categoryPath"
          :options="categoryOptions"
          :props="{ checkStrictly: true, value: 'id', label: 'name', children: 'children' }"
          clearable
          placeholder="请选择分类"
          style="width: 100%"
          @change="handleCategoryChange"
        />
      </el-form-item>
      <el-form-item v-if="!isCategoryTabsStyle && localConfig.data_source === 'manual'" label="选择内容">
        <el-button type="primary" @click="openSelector">+ 添加内容</el-button>
        <div v-if="localConfig.manual_ids && localConfig.manual_ids.length > 0" class="selected-list">
          <div v-for="(item, index) in localConfig.manual_ids" :key="item.id || index" class="selected-item">
            <span class="selected-title">{{ item.title || ('内容 #' + item.id) }}</span>
            <el-button type="text" size="mini" @click="removeManual(index)">
              <LegacyIcon name="el-icon-close" />
            </el-button>
          </div>
        </div>
      </el-form-item>
      <el-form-item label="排序方式">
        <el-select v-model="localConfig.sort_by" placeholder="排序方式" style="width: 120px">
          <el-option label="创建时间" value="create_time" />
          <el-option label="浏览量" value="view_count" />
          <el-option label="销量" value="sales" />
        </el-select>
        <el-select v-model="localConfig.sort_order" placeholder="排序方向" style="width: 100px; margin-left: 10px">
          <el-option label="降序" value="desc" />
          <el-option label="升序" value="asc" />
        </el-select>
      </el-form-item>
      <el-form-item label="背景颜色">
        <el-color-picker v-model="localConfig.bg_color" show-alpha />
        <el-input v-model="localConfig.bg_color" style="width: 150px; margin-left: 10px" />
      </el-form-item>
    </el-form>

    <el-dialog
      v-model="selectorVisible"
      title="选择内容"
      width="680px"
      append-to-body
      @open="loadContentList"
    >
      <div class="selector-search">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索标题"
          style="width: 200px"
          clearable
          @change="loadContentList"
        />
        <el-button type="primary" style="margin-left: 8px" @click="loadContentList">搜索</el-button>
      </div>
      <el-table
        ref="contentTable"
        v-loading="contentLoading"
        :data="contentList"
        style="margin-top: 12px"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="45" />
        <el-table-column label="封面" width="70">
          <template #default="{ row }">
            <img
              v-if="row.cover || row.image"
              :src="row.cover || row.image"
              style="width:50px;height:35px;object-fit:cover;border-radius:3px"
            >
            <span v-else style="color:#ccc;font-size:11px">无封面</span>
          </template>
        </el-table-column>
        <el-table-column label="标题" prop="title" show-overflow-tooltip />
        <el-table-column label="类型" width="70">
          <template #default="{ row }">{{ contentTypeLabel(row.content_type) }}</template>
        </el-table-column>
      </el-table>
      <el-pagination
        small
        layout="prev, pager, next"
        :total="contentTotal"
        :page-size="10"
        :current-page="contentPage"
        style="margin-top: 10px; text-align: right"
        @current-change="onPageChange"
      />
      <template #footer>
        <span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmSelection">
            确认（已选 {{ tempSelected.length }} 个）
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { getArticleCategoryTree, getArticleList } from '@/api/article'

defineOptions({
  name: 'ContentEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  isLast: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['change'])
const categoryOptions = ref([])
const categoryPath = ref([])
const selectorVisible = ref(false)
const contentList = ref([])
const contentTotal = ref(0)
const contentPage = ref(1)
const contentLoading = ref(false)
const searchKeyword = ref('')
const tempSelected = ref([])
const fromProp = ref(false)
const lastEmit = ref('')
const localConfig = reactive({
  title: '最新课程',
  content_type: 'all',
  display_style: 'list',
  layout: 'list',
  show_count: 3,
  load_more: false,
  data_source: 'auto',
  category_id: 0,
  category_ids: [],
  category_tabs: [],
  manual_ids: [],
  sort_by: 'create_time',
  sort_order: 'desc',
  show_cover: true,
  show_desc: true,
  show_price: true,
  bg_color: '#ffffff'
})

const isCategoryTabsStyle = computed(() => localConfig.display_style === 'category_tabs')
const topCategoryOptions = computed(() => categoryOptions.value
  .filter(item => Number(item.pid || 0) === 0 && Number(item.status) === 1)
  .map(item => ({
    id: Number(item.id),
    name: item.name || item.label || ('分类 #' + item.id)
  })))

function normalizeCategoryIds(value) {
  if (typeof value === 'string') {
    value = value.split(',')
  }
  if (!Array.isArray(value)) {
    value = [value]
  }
  const ids = value.map(item => {
    const id = item && typeof item === 'object' ? item.id : item
    return Number(id || 0)
  }).filter(id => id > 0)
  return Array.from(new Set(ids))
}

function normalizeCategoryTabs(tabs, fallbackIds) {
  const source = Array.isArray(tabs) && tabs.length ? tabs : normalizeCategoryIds(fallbackIds)
  return source.map(item => {
    if (item && typeof item === 'object') {
      return {
        id: Number(item.id || item.value || 0),
        name: item.name || item.label || ''
      }
    }
    const id = Number(item || 0)
    const option = topCategoryOptions.value.find(row => row.id === id)
    return {
      id,
      name: option ? option.name : ''
    }
  }).filter(item => item.id > 0)
}

function isSameNumberArray(left, right) {
  const leftIds = normalizeCategoryIds(left)
  const rightIds = normalizeCategoryIds(right)
  return JSON.stringify(leftIds) === JSON.stringify(rightIds)
}

function syncCategoryTabs() {
  const allowMap = topCategoryOptions.value.reduce((map, item) => {
    map[item.id] = item.name
    return map
  }, {})
  const ids = normalizeCategoryIds(localConfig.category_ids)
    .filter(id => !topCategoryOptions.value.length || allowMap[id])
  const tabs = ids.map(id => ({
    id,
    name: allowMap[id] || ''
  }))
  if (!isSameNumberArray(localConfig.category_ids, ids)) {
    localConfig.category_ids = ids
  }
  if (JSON.stringify(localConfig.category_tabs || []) !== JSON.stringify(tabs)) {
    localConfig.category_tabs = tabs
  }
}

function emitConfig() {
  const cfg = { ...localConfig, load_more: props.isLast }
  if (cfg.display_style === 'category_tabs') {
    cfg.data_source = 'category_tabs'
    cfg.load_more = false
    cfg.show_count = Number(cfg.show_count || 4)
    cfg.category_ids = normalizeCategoryIds(cfg.category_ids)
    cfg.category_tabs = normalizeCategoryTabs(cfg.category_tabs, cfg.category_ids)
  }
  lastEmit.value = JSON.stringify(cfg)
  emit('change', cfg)
}

function getConfig() {
  const cfg = { ...localConfig, load_more: props.isLast }
  if (cfg.display_style === 'category_tabs') {
    cfg.data_source = 'category_tabs'
    cfg.load_more = false
    cfg.show_count = Number(cfg.show_count || 4)
    cfg.category_ids = normalizeCategoryIds(cfg.category_ids)
    cfg.category_tabs = normalizeCategoryTabs(cfg.category_tabs, cfg.category_ids)
  }
  return cfg
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  if (lastEmit.value && JSON.stringify(val) === lastEmit.value) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined ? val.title : '最新课程',
    content_type: val.content_type || 'all',
    display_style: val.display_style || 'list',
    layout: 'list',
    show_count: val.show_count !== undefined ? val.show_count : 3,
    load_more: val.load_more !== undefined ? val.load_more : false,
    data_source: val.data_source || 'auto',
    category_id: val.category_id !== undefined ? val.category_id : 0,
    category_ids: normalizeCategoryIds(val.category_ids || val.category_tabs || []),
    category_tabs: normalizeCategoryTabs(val.category_tabs || [], val.category_ids || []),
    manual_ids: Array.isArray(val.manual_ids) ? val.manual_ids : [],
    sort_by: val.sort_by || 'create_time',
    sort_order: val.sort_order || 'desc',
    show_cover: true,
    show_desc: true,
    show_price: true,
    bg_color: val.bg_color || '#ffffff'
  })
  if (localConfig.display_style === 'category_tabs') {
    localConfig.data_source = 'category_tabs'
    localConfig.show_count = Number(localConfig.show_count || 4)
    syncCategoryTabs()
  }
  categoryPath.value = val.category_id ? [val.category_id] : []
  nextTick(() => {
    fromProp.value = false
  })
}

async function fetchCategoryOptions() {
  try {
    const res = await getArticleCategoryTree()
    categoryOptions.value = res.data || []
    if (isCategoryTabsStyle.value) {
      syncCategoryTabs()
      emitConfig()
    }
  } catch (error) {
    categoryOptions.value = []
  }
}

function handleCategoryChange(val) {
  if (val && val.length) {
    localConfig.category_id = val[val.length - 1]
  } else {
    localConfig.category_id = 0
  }
}

function handleCategoryTabsChange() {
  syncCategoryTabs()
}

function openSelector() {
  tempSelected.value = [...(localConfig.manual_ids || [])]
  contentPage.value = 1
  searchKeyword.value = ''
  selectorVisible.value = true
}

async function loadContentList() {
  contentLoading.value = true
  try {
    const res = await getArticleList({
      page: contentPage.value,
      limit: 10,
      title: searchKeyword.value || undefined
    })
    const data = res.data || {}
    contentList.value = data.list || []
    contentTotal.value = data.total || 0
  } catch (error) {
    contentList.value = []
    contentTotal.value = 0
  } finally {
    contentLoading.value = false
  }
}

function onPageChange(page) {
  contentPage.value = page
  loadContentList()
}

function handleSelectionChange(rows) {
  tempSelected.value = rows.map(r => ({
    id: r.id,
    title: r.title,
    cover: r.cover || r.image || ''
  }))
}

function confirmSelection() {
  localConfig.manual_ids = [...tempSelected.value]
  selectorVisible.value = false
}

function removeManual(index) {
  const list = [...localConfig.manual_ids]
  list.splice(index, 1)
  localConfig.manual_ids = list
}

function contentTypeLabel(type) {
  const map = { article: '图文', text: '文字', novel: '小说', image: '图片', video: '视频', audio: '音频' }
  return map[type] || '全部'
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(() => props.isLast, val => {
  if (fromProp.value) return
  localConfig.load_more = val
  emitConfig()
})

watch(localConfig, newVal => {
  if (fromProp.value) return
  if (newVal.display_style === 'category_tabs') {
    const enteringCategoryTabs = localConfig.data_source !== 'category_tabs'
    localConfig.data_source = 'category_tabs'
    if (enteringCategoryTabs || !Number(localConfig.show_count)) {
      localConfig.show_count = 4
    }
    syncCategoryTabs()
  } else if (localConfig.data_source === 'category_tabs') {
    localConfig.data_source = 'auto'
  }
  emitConfig()
}, {
  deep: true
})

fetchCategoryOptions()

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.content-editor {
  .tip-text {
    font-size: 12px;
    color: #909399;
    line-height: 32px;
  }

  .selector-search {
    display: flex;
    align-items: center;
  }

  .selected-list {
    margin-top: 8px;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    padding: 4px 0;
    max-height: 160px;
    overflow-y: auto;
  }

  .selected-item {
    display: flex;
    align-items: center;
    padding: 4px 10px;
    font-size: 12px;

    &:hover {
      background: #f5f7fa;
    }

    .selected-title {
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: #333;
    }
  }
}
</style>
