<template>
  <div class="special-editor">
    <el-form label-width="100px">
      <el-form-item v-if="!isCategoryTabsStyle" label="标题">
        <el-input v-model="localConfig.title" placeholder="热门专题" />
      </el-form-item>
      <el-form-item label="展示样式">
        <el-radio-group v-model="localConfig.display_style">
          <el-radio label="scroll">横向滑动</el-radio>
          <el-radio label="row">一排一个</el-radio>
          <el-radio label="card">卡片</el-radio>
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
          <el-radio label="auto">自动热门</el-radio>
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
          placeholder="请选择专题分类"
          style="width: 100%"
          @change="handleCategoryChange"
        />
      </el-form-item>
      <el-form-item v-if="!isCategoryTabsStyle && localConfig.data_source === 'manual'" label="选择专题">
        <el-button type="primary" @click="openSelector">+ 添加专题</el-button>
        <div v-if="localConfig.manual_ids && localConfig.manual_ids.length > 0" class="selected-list">
          <div v-for="(item, index) in localConfig.manual_ids" :key="item.id || index" class="selected-item">
            <span class="selected-title">{{ item.title || ('专题 #' + item.id) }}</span>
            <el-button type="text" size="mini" @click="removeManual(index)">
              <LegacyIcon name="el-icon-close" />
            </el-button>
          </div>
        </div>
      </el-form-item>
      <el-form-item label="显示描述">
        <el-switch v-model="localConfig.show_desc" />
      </el-form-item>
      <el-form-item label="显示关键字">
        <el-switch v-model="localConfig.show_keywords" />
      </el-form-item>
      <el-form-item label="显示统计">
        <el-switch v-model="localConfig.show_count_info" />
      </el-form-item>
      <el-form-item v-if="!isCategoryTabsStyle" label="更多文字">
        <el-input v-model="localConfig.more_text" placeholder="查看全部" />
      </el-form-item>
      <el-form-item label="背景颜色">
        <el-color-picker v-model="localConfig.bg_color" show-alpha />
        <el-input v-model="localConfig.bg_color" style="width: 150px; margin-left: 10px" />
      </el-form-item>
    </el-form>

    <el-dialog
      v-model="selectorVisible"
      title="选择专题"
      width="680px"
      append-to-body
      @open="loadSpecialList"
    >
      <div class="selector-search">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索专题标题"
          style="width: 200px"
          clearable
          @change="loadSpecialList"
        />
        <el-button type="primary" style="margin-left: 8px" @click="loadSpecialList">搜索</el-button>
      </div>
      <el-table
        ref="specialTable"
        v-loading="specialLoading"
        :data="specialList"
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
        <el-table-column label="专题标题" prop="title" show-overflow-tooltip />
        <el-table-column label="内容数" width="70" prop="content_count" />
      </el-table>
      <el-pagination
        small
        layout="prev, pager, next"
        :total="specialTotal"
        :page-size="10"
        :current-page="specialPage"
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
import { getSpecialList, getSpecialCategoryTree } from '@/api/special'

defineOptions({
  name: 'SpecialEditor'
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
const localConfig = reactive({
  title: '热门专题',
  display_style: 'scroll',
  show_count: 6,
  load_more: false,
  data_source: 'auto',
  category_id: 0,
  category_ids: [],
  category_tabs: [],
  manual_ids: [],
  show_desc: true,
  show_keywords: false,
  show_count_info: true,
  more_text: '查看全部',
  bg_color: '#ffffff'
})
const selectorVisible = ref(false)
const specialList = ref([])
const specialTotal = ref(0)
const specialPage = ref(1)
const specialLoading = ref(false)
const searchKeyword = ref('')
const tempSelected = ref([])
const fromProp = ref(false)
const lastEmit = ref('')

const isCategoryTabsStyle = computed(() => localConfig.display_style === 'category_tabs')
const topCategoryOptions = computed(() => categoryOptions.value
  .filter(item => Number(item.pid || 0) === 0 && Number(item.status) === 1)
  .map(item => ({
    id: Number(item.id),
    name: item.name || item.label || ('分类 #' + item.id),
    image: item.image || ''
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
        name: item.name || item.label || '',
        image: item.image || ''
      }
    }
    const id = Number(item || 0)
    const option = topCategoryOptions.value.find(row => row.id === id)
    return {
      id,
      name: option ? option.name : '',
      image: option ? option.image : ''
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
    name: allowMap[id] || '',
    image: (topCategoryOptions.value.find(item => item.id === id) || {}).image || ''
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
    title: val.title !== undefined ? val.title : '热门专题',
    display_style: val.display_style || 'scroll',
    show_count: val.show_count !== undefined ? val.show_count : 6,
    load_more: val.load_more !== undefined ? val.load_more : false,
    data_source: val.data_source || 'auto',
    category_id: val.category_id !== undefined ? val.category_id : 0,
    category_ids: normalizeCategoryIds(val.category_ids || val.category_tabs || []),
    category_tabs: normalizeCategoryTabs(val.category_tabs || [], val.category_ids || []),
    manual_ids: Array.isArray(val.manual_ids) ? val.manual_ids : [],
    show_desc: val.show_desc !== undefined ? val.show_desc : true,
    show_keywords: val.show_keywords === true,
    show_count_info: val.show_count_info !== undefined ? val.show_count_info : true,
    more_text: val.more_text || '查看全部',
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
    const res = await getSpecialCategoryTree()
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
  specialPage.value = 1
  searchKeyword.value = ''
  selectorVisible.value = true
}

async function loadSpecialList() {
  specialLoading.value = true
  try {
    const res = await getSpecialList({
      page: specialPage.value,
      limit: 10,
      title: searchKeyword.value || undefined,
      status: 1
    })
    const data = res.data || {}
    specialList.value = data.list || []
    specialTotal.value = data.total || 0
  } catch (error) {
    specialList.value = []
    specialTotal.value = 0
  } finally {
    specialLoading.value = false
  }
}

function onPageChange(page) {
  specialPage.value = page
  loadSpecialList()
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
.special-editor {
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
