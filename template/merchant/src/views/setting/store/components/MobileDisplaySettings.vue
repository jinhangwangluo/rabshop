<template>
  <div class="mobile-display-settings">
    <section class="display-section">
      <h2>商户首页</h2>
      <el-form label-width="110px" class="display-banner-form">
        <el-form-item label="首页轮播图">
          <Upload :model-value="banners" :limit="8" @update:model-value="value => emit('update:banners', value)" />
        </el-form-item>
      </el-form>

      <draggable
        v-model="sections"
        item-key="type"
        handle=".display-drag-handle"
        :animation="180"
        ghost-class="display-block-ghost"
        class="display-blocks"
      >
        <template #item="{ element }">
          <div class="display-block" :class="{ 'display-block-disabled': !element.enabled }">
            <div class="display-block-header">
              <el-tooltip content="调整顺序" placement="top">
                <el-button
                  class="display-drag-handle"
                  :icon="Rank"
                  text
                  :aria-label="`调整${getTypeLabel(element.type)}顺序`"
                />
              </el-tooltip>
              <h3>{{ getTypeLabel(element.type) }}</h3>
              <el-tag v-if="element.type === lastEnabledType" type="success" effect="plain" size="small">触底续载</el-tag>
              <el-switch
                :model-value="element.enabled"
                :aria-label="`展示${getTypeLabel(element.type)}`"
                @update:model-value="value => updateSection(element.type, { enabled: value })"
              />
            </div>
            <div class="display-block-body">
              <div class="display-field">
                <label>数据来源</label>
                <el-radio-group
                  :model-value="element.source"
                  @update:model-value="value => updateSection(element.type, { source: value })"
                >
                  <el-radio-button value="all">全部</el-radio-button>
                  <el-radio-button value="category">按分类</el-radio-button>
                  <el-radio-button value="manual">自选</el-radio-button>
                </el-radio-group>
              </div>
              <div class="display-field display-count-field">
                <label>展示数量</label>
                <el-input-number
                  :model-value="element.limit"
                  :min="1"
                  :max="50"
                  :precision="0"
                  controls-position="right"
                  :aria-label="`${getTypeLabel(element.type)}展示数量`"
                  @update:model-value="value => updateSection(element.type, { limit: value || 1 })"
                />
              </div>
              <div v-if="element.source === 'category'" class="display-field display-wide-field">
                <label>{{ element.type === 'product' ? '商户商品分类' : `${getTypeLabel(element.type)}分类` }}</label>
                <el-tree-select
                  :model-value="element.category_ids"
                  class="display-tree-select"
                  :data="categoryOptions[element.type]"
                  :props="treeProps"
                  node-key="id"
                  multiple
                  show-checkbox
                  check-strictly
                  filterable
                  clearable
                  collapse-tags
                  collapse-tags-tooltip
                  :loading="categoryLoading"
                  placeholder="选择分类"
                  @update:model-value="value => updateSection(element.type, { category_ids: normalizeDisplayIds(value) })"
                />
                <el-alert v-if="element.category_ids.length === 0" title="尚未选择分类" type="info" :closable="false" />
              </div>
              <div v-if="element.source === 'manual'" class="display-manual-items display-wide-field">
                <div class="display-selected-header">
                  <span>已选 {{ element.ids.length }} 项</span>
                  <div>
                    <el-button :icon="Plus" @click="openPicker(element.type)">选择{{ getTypeLabel(element.type) }}</el-button>
                    <el-tooltip content="清空选择" placement="top">
                      <el-button
                        :icon="Delete"
                        text
                        :disabled="element.ids.length === 0"
                        :aria-label="`清空${getTypeLabel(element.type)}选择`"
                        @click="updateSection(element.type, { ids: [] })"
                      />
                    </el-tooltip>
                  </div>
                </div>
                <el-table :data="getSelectedRows(element)" row-key="id" max-height="280" empty-text="尚未选择" class="display-selected-table">
                  <el-table-column label="名称" min-width="190" show-overflow-tooltip>
                    <template #default="{ row }">{{ getSelectedLabel(element.type, row) }}</template>
                  </el-table-column>
                  <el-table-column prop="id" label="ID" width="78" align="center" />
                  <el-table-column width="52" align="center">
                    <template #default="{ row }">
                      <el-tooltip content="移除" placement="top">
                        <el-button
                          :icon="Close"
                          text
                          :aria-label="`移除ID ${row.id}`"
                          @click="removeSelected(element.type, row.id)"
                        />
                      </el-tooltip>
                    </template>
                  </el-table-column>
                </el-table>
              </div>
            </div>
          </div>
        </template>
      </draggable>
    </section>

    <section class="display-section display-category-section">
      <h2>分类页</h2>
      <div class="display-field">
        <label>展示分类</label>
        <el-radio-group :model-value="config.category.mode" @update:model-value="value => updateCategory({ mode: value })">
          <el-radio-button value="special">专题分类</el-radio-button>
          <el-radio-button value="product">商户商品分类</el-radio-button>
        </el-radio-group>
      </div>
      <div class="display-field display-category-select">
        <label>分类范围</label>
        <el-tree-select
          :model-value="activeCategoryIds"
          class="display-tree-select"
          :data="categoryOptions[config.category.mode]"
          :props="treeProps"
          node-key="id"
          multiple
          show-checkbox
          check-strictly
          filterable
          clearable
          collapse-tags
          collapse-tags-tooltip
          :loading="categoryLoading"
          placeholder="全部分类"
          @update:model-value="handleCategoryIdsChange"
        />
      </div>
    </section>

    <section class="display-section display-menu-section">
      <h2>底部菜单</h2>
      <div class="display-field">
        <label>菜单内容</label>
        <el-radio-group :model-value="config.menu.mode" @update:model-value="handleMenuModeChange">
          <el-radio-button value="special">专题</el-radio-button>
          <el-radio-button value="product">商品</el-radio-button>
        </el-radio-group>
      </div>
    </section>

    <el-alert v-if="categoryError" :title="categoryError" type="error" :closable="false" show-icon>
      <el-button :icon="Refresh" text :loading="categoryLoading" @click="fetchCategories">重试</el-button>
    </el-alert>

    <MerchantDisplayPicker
      v-model="pickerVisible"
      :type="pickerType"
      :selected-ids="pickerSection?.ids || []"
      :selected-items="pickerItems"
      :category-options="categoryOptions[pickerType]"
      @confirm="handlePickerConfirm"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import draggable from 'vuedraggable'
import { Close, Delete, Plus, Rank, Refresh } from '@element-plus/icons-vue'
import Upload from '@/components/Upload'
import { getArticleCategoryTree } from '@/api/article'
import { getSpecialCategoryTree } from '@/api/special'
import { getProductCategoryTree } from '@/api/product'
import MerchantDisplayPicker from './MerchantDisplayPicker.vue'
import { DISPLAY_TYPES, normalizeDisplayIds, normalizeMobileHomeConfig } from '../mobileDisplayConfig'

defineOptions({ name: 'MobileDisplaySettings' })

const props = defineProps({
  modelValue: { type: Object, required: true },
  banners: { type: Array, default: () => [] }
})
const emit = defineEmits(['update:modelValue', 'update:banners'])
const treeProps = { value: 'id', label: 'name', children: 'children', disabled: 'disabled' }
const categoryOptions = ref({ content: [], special: [], product: [] })
const categoryLoading = ref(false)
const categoryError = ref('')
const pickerVisible = ref(false)
const pickerType = ref('content')
const selectedItems = ref({ content: {}, special: {}, product: {}})

const config = computed(() => normalizeMobileHomeConfig(props.modelValue))
const sections = computed({
  get: () => config.value.sections,
  set: value => emit('update:modelValue', { ...config.value, sections: value })
})
const lastEnabledType = computed(() => sections.value.filter(item => item.enabled).at(-1)?.type || '')
const pickerSection = computed(() => sections.value.find(item => item.type === pickerType.value))
const pickerItems = computed(() => Object.values(selectedItems.value[pickerType.value]))
const activeCategoryIds = computed(() => config.value.category[`${config.value.category.mode}_category_ids`])

onMounted(fetchCategories)

async function fetchCategories() {
  categoryLoading.value = true
  categoryError.value = ''
  const requests = { content: getArticleCategoryTree, special: getSpecialCategoryTree, product: getProductCategoryTree }
  const failed = []
  await Promise.all(Object.entries(requests).map(async([type, request]) => {
    try {
      const response = await request()
      categoryOptions.value[type] = normalizeCategoryTree(response.data)
    } catch (error) {
      failed.push(getTypeLabel(type))
    }
  }))
  if (failed.length) categoryError.value = `${failed.join('、')}分类获取失败`
  categoryLoading.value = false
}

function normalizeCategoryTree(value, parentDisabled = false) {
  if (!Array.isArray(value)) return []
  return value.map(item => {
    const disabled = parentDisabled || Number(item.status) !== 1
    return {
      ...item,
      disabled,
      children: normalizeCategoryTree(item.children, disabled)
    }
  })
}

function getTypeLabel(type) {
  return DISPLAY_TYPES.find(item => item.type === type)?.label || ''
}

function updateSection(type, fields) {
  emit('update:modelValue', {
    ...config.value,
    sections: sections.value.map(item => item.type === type ? { ...item, ...fields } : item)
  })
}

function updateCategory(fields) {
  // 两种分类模式的选择独立保存，切换模式不清除另一种配置。
  emit('update:modelValue', { ...config.value, category: { ...config.value.category, ...fields }})
}

/**
 * 切换底部菜单内容，不影响首页板块和分类页配置。
 * @param {'special'|'product'} mode 菜单内容类型
 * @returns {void}
 */
function handleMenuModeChange(mode) {
  emit('update:modelValue', { ...config.value, menu: { mode }})
}

function handleCategoryIdsChange(value) {
  updateCategory({ [`${config.value.category.mode}_category_ids`]: normalizeDisplayIds(value) })
}

function openPicker(type) {
  pickerType.value = type
  pickerVisible.value = true
}

function handlePickerConfirm({ ids, items }) {
  for (const item of items) selectedItems.value[pickerType.value][Number(item.id)] = item
  updateSection(pickerType.value, { ids: normalizeDisplayIds(ids) })
}

function getSelectedRows(section) {
  return section.ids.map(id => selectedItems.value[section.type][id] || { id })
}

function getSelectedLabel(type, row) {
  return String(row[type === 'product' ? 'name' : 'title'] || `ID ${row.id}`)
}

function removeSelected(type, id) {
  const section = sections.value.find(item => item.type === type)
  updateSection(type, { ids: section.ids.filter(value => value !== Number(id)) })
}
</script>

<style lang="scss" scoped>
.mobile-display-settings {
  max-width: 980px;
  min-width: 0;
  color: var(--text-primary);
}

.display-section {
  padding: 24px 0;

  h2 {
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
  }
}

.display-banner-form {
  margin-bottom: 12px;
}

.display-block {
  padding: 16px 0 24px;
  border-top: 1px solid var(--ds-outline-ghost);
}

.display-block-header {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 36px;
  margin-bottom: 16px;

  h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
  }

  .el-switch {
    margin-left: auto;
  }
}

.display-drag-handle {
  width: 32px;
  height: 32px;
  padding: 0;
  cursor: grab;
  flex-shrink: 0;
}

.display-block-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(160px, 230px);
  gap: 20px;
  padding-left: 44px;
}

.display-field {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;

  label {
    font-size: 13px;
    color: var(--text-secondary);
  }

  .display-tree-select {
    width: 100%;
  }
}

.display-count-field .el-input-number {
  width: 160px;
}

.display-wide-field {
  grid-column: 1 / -1;
}

.display-block-disabled h3 {
  color: var(--text-secondary);
}

.display-block-ghost {
  background: var(--ds-surface-low);
}

.display-selected-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
  font-size: 13px;
  color: var(--text-secondary);
}

.display-category-section,
.display-menu-section {
  border-top: 1px solid var(--ds-outline-ghost);
}

.display-category-select {
  max-width: 620px;
  margin-top: 20px;
}

@media (max-width: 640px) {
  .display-block-body {
    grid-template-columns: minmax(0, 1fr);
    padding-left: 0;
  }

  .display-block-header {
    gap: 8px;
  }

  .display-selected-header > div {
    display: flex;
    align-items: center;
  }
}
</style>
