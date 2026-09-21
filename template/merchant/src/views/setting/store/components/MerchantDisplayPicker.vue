<template>
  <el-dialog
    :model-value="modelValue"
    :title="`选择${typeLabel}`"
    width="880px"
    class="merchant-display-picker"
    :close-on-click-modal="false"
    append-to-body
    @open="handleOpen"
    @update:model-value="value => emit('update:modelValue', value)"
  >
    <el-form class="picker-query" @submit.prevent="handleSearch">
      <el-input
        v-model.trim="query.keyword"
        :placeholder="`${typeLabel}名称`"
        clearable
        :prefix-icon="Search"
        @keyup.enter="handleSearch"
      />
      <el-tree-select
        v-model="query.categoryId"
        class="picker-category-select"
        :data="categoryOptions"
        :props="treeProps"
        node-key="id"
        check-strictly
        filterable
        clearable
        placeholder="全部分类"
        @change="handleSearch"
      />
      <el-button type="primary" :icon="Search" :loading="loading" @click="handleSearch">查询</el-button>
    </el-form>

    <div class="picker-selection-header">
      <span>已选 {{ draftIds.length }} / 100</span>
      <el-tooltip content="清空选择" placement="top">
        <el-button
          :icon="Delete"
          text
          :disabled="draftIds.length === 0"
          aria-label="清空选择"
          @click="draftIds = []"
        />
      </el-tooltip>
    </div>
    <div v-if="draftIds.length" class="picker-selected-items">
      <el-tag v-for="id in draftIds" :key="id" closable @close="handleToggle(id, false)">
        <span :title="getItemLabel(id)">{{ getItemLabel(id) }}</span>
      </el-tag>
    </div>

    <el-alert v-if="errorMessage" :title="errorMessage" type="error" :closable="false" show-icon class="picker-error">
      <el-button text :icon="Refresh" @click="fetchList">重试</el-button>
    </el-alert>

    <el-table v-loading="loading" :data="list" row-key="id" height="360px" empty-text="暂无可选数据">
      <el-table-column width="48" align="center">
        <template #header>
          <el-checkbox
            :model-value="allPageSelected"
            :indeterminate="somePageSelected && !allPageSelected"
            :disabled="loading || list.length === 0"
            aria-label="选择本页"
            @change="handleTogglePage"
          />
        </template>
        <template #default="{ row }">
          <el-checkbox
            :model-value="draftIds.includes(Number(row.id))"
            :disabled="draftIds.length >= 100 && !draftIds.includes(Number(row.id))"
            :aria-label="`选择${getRowLabel(row)}`"
            @change="value => handleToggle(Number(row.id), value)"
          />
        </template>
      </el-table-column>
      <el-table-column label="封面" width="76" align="center">
        <template #default="{ row }">
          <el-image v-if="row.cover" :src="row.cover" class="picker-cover" fit="cover" />
          <span v-else class="picker-muted">-</span>
        </template>
      </el-table-column>
      <el-table-column :label="`${typeLabel}名称`" min-width="190" show-overflow-tooltip>
        <template #default="{ row }">{{ getRowLabel(row) }}</template>
      </el-table-column>
      <el-table-column prop="id" label="ID" width="78" align="center" />
      <el-table-column label="价格" width="100" align="right">
        <template #default="{ row }">{{ formatPrice(row.price) }}</template>
      </el-table-column>
    </el-table>

    <div class="picker-pagination">
      <el-pagination
        v-model:current-page="query.page"
        :page-size="query.limit"
        :total="total"
        layout="total, prev, pager, next"
        small
        @current-change="fetchList"
      />
    </div>

    <template #footer>
      <el-button @click="emit('update:modelValue', false)">取消</el-button>
      <el-button type="primary" :icon="Check" @click="handleConfirm">确定（{{ draftIds.length }}）</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Check, Delete, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getArticleList } from '@/api/article'
import { getSpecialList } from '@/api/special'
import { getStoreProductList } from '@/api/product'
import { DISPLAY_TYPES, normalizeDisplayIds } from '../mobileDisplayConfig'

defineOptions({ name: 'MerchantDisplayPicker' })

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  type: { type: String, default: 'content' },
  selectedIds: { type: Array, default: () => [] },
  selectedItems: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue', 'confirm'])
const treeProps = { value: 'id', label: 'name', children: 'children', disabled: 'disabled' }
const query = reactive({ keyword: '', categoryId: null, page: 1, limit: 15 })
const draftIds = ref([])
const itemMap = ref({})
const list = ref([])
const total = ref(0)
const loading = ref(false)
const errorMessage = ref('')
let requestVersion = 0

const typeLabel = computed(() => DISPLAY_TYPES.find(item => item.type === props.type)?.label || '')
const allPageSelected = computed(() => list.value.length > 0 && list.value.every(row => draftIds.value.includes(Number(row.id))))
const somePageSelected = computed(() => list.value.some(row => draftIds.value.includes(Number(row.id))))

function handleOpen() {
  Object.assign(query, { keyword: '', categoryId: null, page: 1 })
  draftIds.value = normalizeDisplayIds(props.selectedIds)
  itemMap.value = Object.fromEntries(props.selectedItems.map(item => [Number(item.id), item]))
  list.value = []
  total.value = 0
  fetchList()
}

async function fetchList() {
  const version = ++requestVersion
  loading.value = true
  errorMessage.value = ''
  const params = { page: query.page, limit: query.limit, status: 1, audit_status: 1 }
  if (query.keyword) params[props.type === 'product' ? 'name' : 'title'] = query.keyword
  if (query.categoryId) {
    const field = { content: 'article_category_id', special: 'category_id', product: 'merchant_category_id' }[props.type]
    params[field] = Number(query.categoryId)
  }
  const request = { content: getArticleList, special: getSpecialList, product: getStoreProductList }[props.type]

  try {
    const response = await request(params)
    if (version !== requestVersion) return
    const data = response.data || {}
    list.value = Array.isArray(data.list) ? data.list : []
    total.value = Number(data.total) || 0
    for (const row of list.value) itemMap.value[Number(row.id)] = row
  } catch (error) {
    if (version !== requestVersion) return
    list.value = []
    total.value = 0
    errorMessage.value = error.message || `获取${typeLabel.value}失败`
  } finally {
    if (version === requestVersion) loading.value = false
  }
}

function handleSearch() {
  query.page = 1
  fetchList()
}

function handleToggle(id, selected) {
  if (!selected) {
    draftIds.value = draftIds.value.filter(value => value !== id)
  } else if (!draftIds.value.includes(id)) {
    if (draftIds.value.length >= 100) {
      ElMessage.warning('最多选择 100 项')
      return
    }
    draftIds.value.push(id)
  }
}

function handleTogglePage(selected) {
  const pageIds = list.value.map(row => Number(row.id))
  if (!selected) {
    draftIds.value = draftIds.value.filter(id => !pageIds.includes(id))
    return
  }
  const nextIds = [...new Set([...draftIds.value, ...pageIds])]
  if (nextIds.length > 100) ElMessage.warning('最多选择 100 项')
  draftIds.value = nextIds.slice(0, 100)
}

function getRowLabel(row) {
  return String(row[props.type === 'product' ? 'name' : 'title'] || `ID ${row.id}`)
}

function getItemLabel(id) {
  return itemMap.value[id] ? getRowLabel(itemMap.value[id]) : `ID ${id}`
}

function formatPrice(value) {
  const price = Number(value)
  return Number.isFinite(price) ? (price > 0 ? `¥${price.toFixed(2)}` : '免费') : '-'
}

function handleConfirm() {
  // 未在当前页查到的已选 ID 也必须保留，避免编辑或接口失败时误清配置。
  emit('confirm', {
    ids: [...draftIds.value],
    items: draftIds.value.map(id => itemMap.value[id]).filter(Boolean)
  })
  emit('update:modelValue', false)
}
</script>

<style lang="scss" scoped>
.picker-query {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(150px, 210px) auto;
  gap: 12px;
  margin-bottom: 16px;
}

.picker-category-select {
  width: 100%;
  min-width: 0;
}

.picker-selection-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 32px;
  color: var(--text-secondary);
  font-size: 13px;
}

.picker-selected-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-height: 120px;
  overflow-y: auto;
  margin-bottom: 14px;

  .el-tag {
    max-width: 200px;
  }

  :deep(.el-tag__content) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.picker-cover {
  display: block;
  width: 52px;
  height: 44px;
  border-radius: 4px;
}

.picker-muted {
  color: var(--text-secondary);
}

.picker-error {
  margin: 12px 0;
}

.picker-pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
  overflow-x: auto;
}

@media (max-width: 640px) {
  .picker-query {
    grid-template-columns: minmax(0, 1fr) auto;

    .el-input {
      grid-column: 1 / -1;
    }
  }
}
</style>

<style lang="scss">
.merchant-display-picker.el-dialog {
  max-width: calc(100vw - 32px);
  border-radius: 8px;
}

@media (max-width: 640px) {
  .merchant-display-picker.el-dialog {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 48px);
    margin-top: 24px;

    .el-dialog__body {
      min-height: 0;
      overflow-y: auto;
    }

    .el-dialog__footer {
      flex-shrink: 0;
    }
  }
}
</style>
